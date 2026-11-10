<?php
namespace think {
    class Db {
        public static $order; public static function transaction($f) { return $f(); }
        public static function name($name) { return new Query; }
    }
    class Query {
        private $conditions = [];
        public function where($k, $v) { $this->conditions[$k] = $v; return $this; }
        public function lock($v) { return $this; }
        public function find() { return Db::$order; }
        public function update($data) { foreach ($this->conditions as $key => $value) if ((Db::$order[$key] ?? null) !== $value) return 0; Db::$order = array_merge(Db::$order, $data); return 1; }
    }
}
namespace addons\wallet\library { class Wallet { public static $count = 0; public static function change(...$args) { self::$count++; } } }
namespace addons\epay\library {
    class Service {
        public static $version = 'v3';
        public static function getSdkVersion() { return self::$version; }
        public static function getConfig($type, $custom) { return $custom; }
    }
}
namespace Yansongda\Pay {
    class Pay { public static $data; public static $closeResult; public static function wechat($config) { return new self; } public function find(...$args) { if (self::$data === false) throw new \RuntimeException('query failed'); return self::$data; } public function close($order) { if (self::$closeResult === false) throw new \RuntimeException('close failed'); return self::$closeResult; } }
}
namespace {
    function cache($key, $value = null, $ttl = null) { if ($value === null) return $GLOBALS['cache'][$key] ?? null; $GLOBALS['cache'][$key] = $value; }
    function get_addon_config($name) { return ['wechat' => ['mch_id' => 'm-test', 'mode' => 'normal']]; }
    function get_addon_info($name) { return ['state' => 1]; }
    function get_addon_instance($name) { return new class { public function epayConfigInit() {} }; }
    require dirname(__DIR__) . '/library/RechargeSettlement.php';
    require dirname(__DIR__) . '/library/RechargeQuery.php';
    foreach (['v2', 'v3'] as $version) {
        \addons\epay\library\Service::$version = $version;
        $order = ['id'=>1, 'order_no'=>'test-order', 'user_id'=>1, 'coins'=>10, 'price_fen'=>99, 'status'=>'pending', 'pay_type'=>'wechat', 'payment_appid'=>'wx-test', 'merchant_id'=>'m-test'];
        \think\Db::$order = $order; \addons\wallet\library\Wallet::$count = 0;
        $response = $version === 'v3' ? ['appid'=>'wx-test','mchid'=>'m-test','amount'=>['total'=>99,'currency'=>'CNY']] : ['appid'=>'wx-test','mch_id'=>'m-test','total_fee'=>99,'return_code'=>'SUCCESS','result_code'=>'SUCCESS'];
        $response += ['out_trade_no'=>'test-order','transaction_id'=>'tx-test','trade_state'=>'NOTPAY'];
        \Yansongda\Pay\Pay::$data = $response; $GLOBALS['cache'] = [];
        \addons\checkin\library\RechargeQuery::reconcile($order);
        if (\think\Db::$order['status'] !== 'pending') throw new \RuntimeException('unpaid settled');
        $response['trade_state'] = 'CLOSED'; $GLOBALS['cache'] = []; \Yansongda\Pay\Pay::$data = $response;
        \addons\checkin\library\RechargeQuery::reconcile($order);
        if (\think\Db::$order['status'] !== 'closed') throw new \RuntimeException('closed order not synchronized');
        $GLOBALS['cache'] = []; \addons\checkin\library\RechargeQuery::reconcile($order);
        if (\think\Db::$order['status'] !== 'closed') throw new \RuntimeException('repeated closed query changed order');
        $GLOBALS['cache'] = []; \think\Db::$order['status'] = 'paid';
        \addons\checkin\library\RechargeQuery::reconcile($order);
        if (\think\Db::$order['status'] !== 'paid') throw new \RuntimeException('closed query overwrote paid order');
        \think\Db::$order = $order;
        $response['trade_state'] = 'SUCCESS'; $GLOBALS['cache'] = []; $response['out_trade_no'] = 'other'; \Yansongda\Pay\Pay::$data = $response;
        try { \addons\checkin\library\RechargeQuery::reconcile($order); throw new \LogicException('wrong order accepted'); } catch (\RuntimeException $e) {}
        $response['out_trade_no'] = 'test-order'; $GLOBALS['cache'] = []; \Yansongda\Pay\Pay::$data = $response;
        \addons\checkin\library\RechargeQuery::reconcile($order);
        $GLOBALS['cache'] = []; \addons\checkin\library\RechargeQuery::reconcile($order);
        if (\think\Db::$order['status'] !== 'paid' || \addons\wallet\library\Wallet::$count !== 1) throw new \RuntimeException('settlement not idempotent');
        \think\Db::$order = $order; $GLOBALS['cache'] = []; \Yansongda\Pay\Pay::$data = false;
        try { \addons\checkin\library\RechargeQuery::reconcile($order); throw new \LogicException('failed query accepted'); } catch (\RuntimeException $e) {}
        if (\think\Db::$order['status'] !== 'pending') throw new \RuntimeException('failed query settled');
        \Yansongda\Pay\Pay::$closeResult = $version === 'v2' ? ['return_code'=>'SUCCESS','result_code'=>'SUCCESS'] : null;
        \addons\checkin\library\RechargeQuery::close($order);
        if (\think\Db::$order['status'] !== 'closed') throw new \RuntimeException('close not persisted');
        \think\Db::$order = $order; \Yansongda\Pay\Pay::$closeResult = false; \Yansongda\Pay\Pay::$data = false;
        try { \addons\checkin\library\RechargeQuery::close($order); throw new \LogicException('uncertain close accepted'); } catch (\RuntimeException $e) {}
        if (\think\Db::$order['status'] !== 'pending') throw new \RuntimeException('uncertain close changed order');
        \Yansongda\Pay\Pay::$data = $response; $GLOBALS['cache'] = []; \addons\wallet\library\Wallet::$count = 0;
        \addons\checkin\library\RechargeQuery::close($order);
        if (\think\Db::$order['status'] !== 'paid' || \addons\wallet\library\Wallet::$count !== 1) throw new \RuntimeException('paid close not settled');
    }
    echo "PASS: v2/v3 pending, mismatch, settlement, duplicate, query failure and close outcomes\n";
}
