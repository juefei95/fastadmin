<?php
namespace think { class Db { public static $relations = []; public static function execute($sql, $params) { self::$relations[] = $params[0]; } } }
namespace addons\third\model {
    class Third { public static $bindings = []; public static function where($key, $value) { return (new ThirdQuery)->where($key, $value); } }
    class ThirdQuery {
        private $conditions = [];
        public function where($key, $value) { $this->conditions[$key] = $value; return $this; }
        public function find() { return ($this->conditions['user_id'] ?? 0) === 5 && ($this->conditions['apptype'] ?? '') === 'miniapp' && in_array($this->conditions['platform'] ?? '', Third::$bindings, true); }
    }
}
namespace addons\checkin\library {
    class RewardService {
        public static $calls = [];
        public static function virtualLocation($userId, $legacy = false) { self::$calls[] = [$userId, $legacy]; return ['score' => 8, 'deducted' => 2]; }
    }
}
namespace app\common\controller {
    class Api {
        public $request; public $auth;
        public function __construct() { $this->request = new \TestRequest; $this->auth = (object)['id' => 5]; }
        protected function error($message, $data = null) { throw new \TestError($message); }
        protected function success($message, $data = null) { throw new \TestSuccess; }
    }
}
namespace {
    class TestRequest { public function isPost() { return true; } }
    class TestError extends \Exception {}
    class TestSuccess extends \Exception {}
    function config($key) { return 'fa_'; }
    require dirname(__DIR__) . '/controller/Api.php';
    foreach ([['wechat_legacy', true], ['wechat', false]] as [$binding, $legacy]) {
        \addons\third\model\Third::$bindings = [$binding];
        \addons\checkin\library\RewardService::$calls = [];
        \think\Db::$relations = [];
        try { (new \addons\checkin\controller\Api)->virtuallocation(); throw new \Exception('missing response'); }
        catch (TestSuccess $e) {}
        catch (TestError $e) { fwrite(STDERR, "$binding failed: {$e->getMessage()}\n"); exit(1); }
        if (\addons\checkin\library\RewardService::$calls !== [[5, $legacy]] || \think\Db::$relations !== ($legacy ? [] : [5])) throw new \Exception('incorrect identity or relation');
    }
    \addons\third\model\Third::$bindings = [];
    \addons\checkin\library\RewardService::$calls = [];
    try { (new \addons\checkin\controller\Api)->virtuallocation(); throw new \Exception('unbound user accepted'); }
    catch (TestError $e) { if (\addons\checkin\library\RewardService::$calls) throw new \Exception('unbound user charged'); }
    echo "PASS: legacy, new, and unbound identities\n";
}
