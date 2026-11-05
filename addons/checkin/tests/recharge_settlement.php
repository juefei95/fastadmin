<?php
namespace think {
    class Db {
        public static $order; public static function transaction($callback) { return $callback(); }
        public static function name($table) { return new Query; }
    }
    class Query {
        public function where($key, $value) { return $this; }
        public function lock($lock) { return $this; }
        public function find() { return Db::$order; }
        public function update($values) { Db::$order = array_merge(Db::$order, $values); }
    }
}
namespace addons\wallet\library {
    class Wallet { public static $credits = 0; public static function change($id, $amount, $type, $memo, $currency, $sourceType, $sourceId) { self::$credits += $amount; } }
}
namespace {
    require dirname(__DIR__) . '/library/RechargeSettlement.php';
    $pay = ['order_no' => 'ORDER-1', 'transaction_no' => 'TX-1', 'price_fen' => 99];
    \think\Db::$order = ['id' => 1, 'order_no' => 'ORDER-1', 'user_id' => 2, 'pay_type' => 'wechat', 'price_fen' => 99, 'coins' => 50, 'status' => 'pending', 'transaction_no' => null];
    if (!\addons\checkin\library\RechargeSettlement::settleWechat($pay)) throw new \RuntimeException('first notification failed');
    if (\addons\checkin\library\RechargeSettlement::settleWechat($pay)) throw new \RuntimeException('duplicate was processed');
    if (\addons\wallet\library\Wallet::$credits !== 50) throw new \RuntimeException('duplicate credit');
    $pay['price_fen'] = 1;
    try { \addons\checkin\library\RechargeSettlement::settleWechat($pay); throw new \Exception('wrong amount accepted'); }
    catch (\RuntimeException $expected) {}
    echo "PASS: first payment, duplicate notification, incorrect amount\n";
}
