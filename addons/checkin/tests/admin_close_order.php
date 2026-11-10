<?php
namespace think {
    class Db { public static $order; public static function name($name) { return new Query; } }
    class Query { public function where($key, $value) { return $this; } public function find() { return Db::$order; } }
    class Log { public static function record($message, $level) {} }
}
namespace app\common\controller {
    class Backend {
        public $request;
        public function __construct() { $this->request = new \TestRequest; }
        protected function token() {}
        protected function error($message) { throw new \TestError($message); }
        protected function success($message) { throw new \TestSuccess($message); }
    }
}
namespace addons\checkin\library {
    class RechargeQuery {
        public static $outcome;
        public static function close($order) {
            if (self::$outcome === 'unknown') throw new \RuntimeException;
            \think\Db::$order['status'] = self::$outcome;
        }
    }
}
namespace {
    class TestRequest { public function isPost() { return true; } public function post($key, $default = null) { return 'test-order'; } }
    class TestError extends \Exception {}
    class TestSuccess extends \Exception {}
    require dirname(__DIR__) . '/application/admin/controller/checkin/Recharge.php';
    foreach (['closed', 'paid', 'unknown'] as $outcome) {
        \think\Db::$order = ['status'=>'pending', 'pay_type'=>'wechat'];
        \addons\checkin\library\RechargeQuery::$outcome = $outcome;
        try { (new \app\admin\controller\checkin\Recharge)->closeorder(); throw new \LogicException('no response'); }
        catch (TestSuccess $e) { if ($outcome === 'unknown' || \think\Db::$order['status'] !== $outcome) throw new \LogicException('incorrect success'); }
        catch (TestError $e) { if ($outcome !== 'unknown' || \think\Db::$order['status'] !== 'pending') throw new \LogicException('incorrect error'); }
    }
    echo "PASS: admin closed, paid, and uncertain outcomes\n";
}
