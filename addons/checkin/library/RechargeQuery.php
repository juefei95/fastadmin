<?php
namespace addons\checkin\library;

use addons\epay\library\Service;
use Yansongda\Pay\Pay;

class RechargeQuery
{
    public static function reconcile(array $order, $force = false)
    {
        if (($order['status'] ?? '') !== 'pending' || ($order['pay_type'] ?? '') !== 'wechat') return;
        $key = 'checkin_recharge_query_' . hash('sha256', $order['order_no']);
        if (!$force && cache($key)) return 'throttled';
        // Cache is a best-effort throttle; settlement remains idempotent if requests race.
        cache($key, 1, 15);
        $epay = get_addon_config('epay') ?: [];
        $wechat = $epay['wechat'] ?? [];
        $merchant = (string)($order['merchant_id'] ?? '');
        $appid = (string)($order['payment_appid'] ?? '');
        if (!$merchant || !$appid || $merchant !== (string)($wechat['mch_id'] ?? '') || ($wechat['mode'] ?? 'normal') !== 'normal') {
            throw new \RuntimeException('Payment identity unavailable');
        }
        $plugin = get_addon_instance('epay');
        if (!$plugin || !(get_addon_info('epay')['state'] ?? false)) throw new \RuntimeException('Payment service unavailable');
        $version = Service::getSdkVersion();
        if (!in_array($version, ['v2', 'v3'], true)) throw new \RuntimeException('Payment version unavailable');
        $plugin->epayConfigInit();
        $custom = $version === 'v3' ? ['mp_app_id' => $appid] : ['app_id' => $appid];
        $pay = Pay::wechat(Service::getConfig('wechat', $custom));
        $result = $version === 'v3' ? $pay->find(['out_trade_no' => $order['order_no']]) : $pay->find($order['order_no'], 'scan');
        if (is_object($result) && method_exists($result, 'all')) $result = $result->all();
        if (!is_array($result)) throw new \RuntimeException('Invalid payment query response');
        if ($version === 'v2' && (($result['return_code'] ?? '') !== 'SUCCESS' || ($result['result_code'] ?? '') !== 'SUCCESS')) throw new \RuntimeException('Payment query rejected');
        if (empty($result['trade_state'])) throw new \RuntimeException('Payment query state unavailable');
        if (($result['out_trade_no'] ?? '') !== $order['order_no'] || ($result['appid'] ?? '') !== $appid || ($result[$version === 'v3' ? 'mchid' : 'mch_id'] ?? '') !== $merchant) throw new \RuntimeException('Payment identity mismatch');
        if (($result['trade_state'] ?? '') === 'CLOSED') {
            \think\Db::name('checkin_recharge_order')->where('order_no', $order['order_no'])->where('status', 'pending')->update(['status' => 'closed']);
            return 'closed';
        }
        if (($result['trade_state'] ?? '') !== 'SUCCESS') return 'unpaid';
        $payment = RechargeSettlement::normalizeWechat($result, $version, $appid, $merchant);
        RechargeSettlement::settleWechat($payment);
        return 'paid';
    }

    public static function close(array $order)
    {
        if (($order['status'] ?? '') !== 'pending' || ($order['pay_type'] ?? '') !== 'wechat') throw new \RuntimeException('Order cannot be closed');
        $wechat = (get_addon_config('epay') ?: [])['wechat'] ?? [];
        $appid = (string)($order['payment_appid'] ?? '');
        if (!$appid || empty($order['merchant_id']) || $order['merchant_id'] !== (string)($wechat['mch_id'] ?? '') || ($wechat['mode'] ?? 'normal') !== 'normal') throw new \RuntimeException('Payment identity unavailable');
        $plugin = get_addon_instance('epay');
        if (!$plugin || !(get_addon_info('epay')['state'] ?? false)) throw new \RuntimeException('Payment service unavailable');
        $version = Service::getSdkVersion();
        if (!in_array($version, ['v2', 'v3'], true)) throw new \RuntimeException('Payment version unavailable');
        $plugin->epayConfigInit();
        $pay = Pay::wechat(Service::getConfig('wechat', $version === 'v3' ? ['mp_app_id'=>$appid] : ['app_id'=>$appid]));
        try {
            $result = $pay->close(['out_trade_no' => $order['order_no']]);
            if ($version === 'v2') {
                if (is_object($result) && method_exists($result, 'all')) $result = $result->all();
                if (!is_array($result) || ($result['return_code'] ?? '') !== 'SUCCESS' || ($result['result_code'] ?? '') !== 'SUCCESS') throw new \RuntimeException('Close not confirmed');
            }
            $state = 'closed';
        } catch (\Throwable $e) {
            $state = self::reconcile($order, true);
        }
        if ($state === 'closed') {
            \think\Db::name('checkin_recharge_order')->where('order_no', $order['order_no'])->where('status', 'pending')->update(['status' => 'closed']);
        }
        if (!in_array($state, ['paid', 'closed'], true)) throw new \RuntimeException('Payment status uncertain');
        return $state;
    }
}
