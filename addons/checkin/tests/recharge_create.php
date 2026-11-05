<?php
namespace think {
    class Db {
        public static $orders = [];
        public static function transaction($f) { return $f(); }
        public static function name($table) { return new Query($table); }
        public static function execute($sql, $params) {}
    }
    class Query {
        private $table;
        public function __construct($table) { $this->table = $table; }
        public function where($key, $value) { return $this; }
        public function lock($value) { return $this; }
        public function find() { return ['enabled'=>1, 'price_fen'=>99, 'coins'=>50]; }
        public function insert($data) { Db::$orders[] = $data; }
    }
}
namespace addons\third\model { class Third { public static function where($key,$value) { return new self; } public function __call($name,$args) { return $name === 'find' ? true : $this; } } }
namespace addons\epay\library {
    class Service {
        public static $args; public static $version = 'v3';
        public static function getSdkVersion() { return self::$version; }
        public static function submitOrder(...$args) { self::$args = $args; return self::$version === 'v2' ? new V2Result : new Result; }
    }
    class Result { public function all() { return ['code_url'=>'weixin://wxpay/bizpayurl?pr=test']; } }
    class V2Result { public function getContent() { return json_encode((new Result)->all()); } }
}
namespace app\common\controller {
    class Api {
        public $request; public $auth;
        public function __construct() { $this->request = new \TestRequest; $this->auth = (object)['id'=>5]; }
        protected function error($message, $data = null) { throw new \TestError($message); }
        protected function success($message, $data = null) { throw new \TestSuccess($data); }
    }
}
namespace {
    class TestRequest { public function isPost() { return true; } public function post($key, $default = null) { return $key === 'package_id' ? '1' : $default; } }
    class TestError extends \Exception {}
    class TestSuccess extends \Exception { public $data; public function __construct($data) { $this->data = $data; } }
    class TestPlugin { public function epayConfigInit() {} }
    function config($key) { return 'fa_'; }
    function get_addon_config($name) { return $name === 'checkin' ? ['payment_appid'=>'wx1234567890abcdef', 'payment_wechat_enabled'=>'1', 'payment_notify_url'=>'https://example.com/addons/checkin/payment/wechatnotify'] : ['wechat'=>['mch_id'=>'123', 'mode'=>'normal']]; }
    function get_addon_instance($name) { return new TestPlugin; }
    function get_addon_info($name) { return ['state'=>1]; }
    require dirname(__DIR__) . '/controller/Api.php';
    foreach (['v2', 'v3'] as $version) {
      \addons\epay\library\Service::$version = $version;
      try { (new \addons\checkin\controller\Api)->createrechargeorder(); throw new \Exception('no response'); }
      catch (TestSuccess $e) {
        $order = end(\think\Db::$orders); $args = \addons\epay\library\Service::$args;
        if ($order['user_id'] !== 5 || $order['price_fen'] !== 99 || $order['coins'] !== 50 || $args[0] !== '0.99'
            || $args[1] !== $order['order_no'] || $args[6] !== 'scan' || $args[8][$version === 'v3' ? 'mp_app_id' : 'app_id'] !== $order['payment_appid']
            || $e->data['code_url'] !== 'weixin://wxpay/bizpayurl?pr=test') throw new \Exception('order or payment parameters incorrect');
      }
    }
    echo "PASS: server-priced order and WeChat Native parameters (v2 and v3)\n";
}
