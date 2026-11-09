<?php
require dirname(__DIR__) . '/library/RechargeSettlement.php';
use addons\checkin\library\RechargeSettlement;
$valid = ['appid' => 'wx-test', 'mchid' => 'm-test', 'trade_state' => 'SUCCESS', 'amount' => ['total' => 99, 'currency' => 'CNY'], 'out_trade_no' => 'ORDER-1', 'transaction_id' => 'TX-1'];
$result = RechargeSettlement::normalizeWechat($valid, 'v3', 'wx-test', 'm-test');
if ($result['price_fen'] !== 99 || $result['order_no'] !== 'ORDER-1') throw new RuntimeException('valid notification rejected');
$v2 = ['appid' => 'wx-test', 'mch_id' => 'm-test', 'return_code' => 'SUCCESS', 'result_code' => 'SUCCESS', 'total_fee' => '99', 'out_trade_no' => 'ORDER-1', 'transaction_id' => 'TX-1'];
if (RechargeSettlement::normalizeWechat($v2, 'v2', 'wx-test', 'm-test')['price_fen'] !== 99) throw new RuntimeException('valid v2 notification rejected');
foreach (['appid', 'mchid', 'trade_state', 'out_trade_no', 'transaction_id'] as $field) {
    $invalid = $valid;
    $invalid[$field] = '';
    try { RechargeSettlement::normalizeWechat($invalid, 'v3', 'wx-test', 'm-test'); throw new RuntimeException('accepted invalid ' . $field); }
    catch (InvalidArgumentException $expected) {}
}
foreach ([['total' => 0, 'currency' => 'CNY'], ['total' => 99, 'currency' => 'USD'], ['total' => '1.1', 'currency' => 'CNY']] as $amount) {
    $invalid = $valid; $invalid['amount'] = $amount;
    try { RechargeSettlement::normalizeWechat($invalid, 'v3', 'wx-test', 'm-test'); throw new RuntimeException('accepted invalid amount'); }
    catch (InvalidArgumentException $expected) {}
}
echo "PASS: WeChat notification validation\n";
