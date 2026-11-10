<?php
namespace addons\checkin\library;

use addons\epay\library\Service;
use Yansongda\Pay\Pay;

class RechargeQuery
{
    public static function reconcile(array $order)
    {
        if (($order['status'] ?? '') !== 'pending' || ($order['pay_type'] ?? '') !== 'wechat') return;
        $key = 'checkin_recharge_query_' . hash('sha256', $order['order_no']);
        if (cache($key)) return;
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
        if (($result['trade_state'] ?? '') !== 'SUCCESS') return;
        if (($result['out_trade_no'] ?? '') !== $order['order_no']) throw new \RuntimeException('Payment order mismatch');
        $payment = RechargeSettlement::normalizeWechat($result, $version, $appid, $merchant);
        RechargeSettlement::settleWechat($payment);
    }
}
