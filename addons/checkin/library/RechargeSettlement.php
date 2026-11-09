<?php
namespace addons\checkin\library;

use addons\wallet\library\Wallet;
use think\Db;

/** Apply payment data only after the payment provider's signature has been verified. */
class RechargeSettlement
{
    public static function normalizeWechat(array $data, $version, $expectedAppId, $expectedMerchantId)
    {
        if (!in_array($version, ['v2', 'v3'], true) || !$expectedAppId || !$expectedMerchantId) {
            throw new \InvalidArgumentException('支付配置不完整');
        }
        if ($version === 'v3') {
            $paid = ($data['trade_state'] ?? '') === 'SUCCESS';
            $amount = $data['amount']['total'] ?? null;
            $currency = $data['amount']['currency'] ?? '';
            $merchantId = $data['mchid'] ?? '';
        } else {
            $paid = ($data['return_code'] ?? '') === 'SUCCESS' && ($data['result_code'] ?? '') === 'SUCCESS';
            $amount = $data['total_fee'] ?? null;
            $currency = $data['fee_type'] ?? 'CNY';
            $merchantId = $data['mch_id'] ?? '';
        }
        $order = (string)($data['out_trade_no'] ?? '');
        $transaction = (string)($data['transaction_id'] ?? '');
        if (!$paid || ($data['appid'] ?? '') !== $expectedAppId || $merchantId !== $expectedMerchantId
            || $currency !== 'CNY' || !is_scalar($amount) || !ctype_digit((string)$amount) || (int)$amount < 1
            || !preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $order)
            || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $transaction)) {
            throw new \InvalidArgumentException('支付通知数据不匹配');
        }
        return ['order_no' => $order, 'transaction_no' => $transaction, 'price_fen' => (int)$amount];
    }

    public static function settleWechat(array $payment)
    {
        return Db::transaction(function () use ($payment) {
            $order = Db::name('checkin_recharge_order')->where('order_no', $payment['order_no'])->lock(true)->find();
            if (!$order || $order['pay_type'] !== 'wechat' || (int)$order['price_fen'] !== $payment['price_fen']) {
                throw new \RuntimeException('订单或金额不匹配');
            }
            if ($order['status'] === 'paid') {
                if ($order['transaction_no'] !== $payment['transaction_no']) throw new \RuntimeException('支付流水不匹配');
                return false;
            }
            if ($order['status'] !== 'pending' || (int)$order['coins'] < 1 || (int)$order['user_id'] < 1) {
                throw new \RuntimeException('订单状态不可入账');
            }
            Wallet::change((int)$order['user_id'], (int)$order['coins'], 'purchase', '充值到账', 'coin', 'checkin_recharge', (int)$order['id']);
            Db::name('checkin_recharge_order')->where('id', $order['id'])->update([
                'status' => 'paid', 'transaction_no' => $payment['transaction_no'], 'paidtime' => time(),
            ]);
            return true;
        });
    }
}
