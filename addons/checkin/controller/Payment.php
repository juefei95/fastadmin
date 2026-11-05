<?php
namespace addons\checkin\controller;

use addons\checkin\library\RechargeSettlement;
use addons\epay\library\Service as PayService;
use think\addons\Controller;
use think\Log;
use think\Db;
use Yansongda\Pay\Pay;

class Payment extends Controller
{
    /** WeChat server-to-server payment notification; no user login is required. */
    public function wechatnotify()
    {
        $version = null;
        try {
            if (!$this->request->isPost()) throw new \RuntimeException('Invalid method');
            $epay = get_addon_config('epay') ?: [];
            $wechat = $epay['wechat'] ?? [];
            $merchant = trim((string)($wechat['mch_id'] ?? ''));
            $version = PayService::getSdkVersion();
            // This integration currently supports ordinary merchants only.
            if (($wechat['mode'] ?? 'normal') !== 'normal' || !$merchant || !in_array($version, ['v2', 'v3'], true)) {
                throw new \RuntimeException('Payment configuration unavailable');
            }
            $plugin = get_addon_instance('epay');
            if (!$plugin || !(get_addon_info('epay')['state'] ?? false)) throw new \RuntimeException('Payment plugin unavailable');
            $plugin->epayConfigInit();
            $pay = Pay::wechat(PayService::getConfig('wechat'));
            // callback()/verify() verifies the provider signature; never trust request data directly.
            $verified = $version === 'v3' ? $pay->callback() : $pay->verify();
            $data = $verified->all();
            if ($version === 'v3') {
                if (($data['event_type'] ?? '') !== 'TRANSACTION.SUCCESS') throw new \RuntimeException('Unexpected event');
                $data = $data['resource']['ciphertext'] ?? null;
            }
            if (!is_array($data)) throw new \RuntimeException('Invalid notification');
            $orderNo = (string)($data['out_trade_no'] ?? '');
            if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $orderNo)) throw new \RuntimeException('Invalid order');
            $order = Db::name('checkin_recharge_order')->where('order_no', $orderNo)->field('payment_appid,merchant_id')->find();
            if (!$order || empty($order['payment_appid']) || empty($order['merchant_id'])) throw new \RuntimeException('Order payment identity unavailable');
            $payment = RechargeSettlement::normalizeWechat($data, $version, $order['payment_appid'], $order['merchant_id']);
            RechargeSettlement::settleWechat($payment);
            $body = $version === 'v3' ? $pay->success()->getBody()->getContents() : $pay->success()->getContent();
            return response($body, 200, ['Content-Type' => $version === 'v3' ? 'application/json' : 'application/xml']);
        } catch (\Throwable $e) {
            // Do not return success on signature or settlement errors: WeChat must retry.
            Log::record('checkin WeChat payment notification failed: ' . get_class($e), 'error');
            if ($version === 'v2') {
                return response('<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[FAIL]]></return_msg></xml>', 500, ['Content-Type' => 'application/xml']);
            }
            return response('{"code":"FAIL","message":"处理失败"}', 500, ['Content-Type' => 'application/json']);
        }
    }
}
