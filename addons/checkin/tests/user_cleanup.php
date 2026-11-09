<?php
namespace think {
    class Addons {}
    class Db {
        public static $deleted = [];
        public static function name($table) { if ($table !== 'checkin_user') throw new \Exception('wrong table'); return new self; }
        public function where($field, $id) { if ($field !== 'user_id') throw new \Exception('wrong field'); $this->id = $id; return $this; }
        public function delete() { self::$deleted[] = $this->id; }
    }
}
namespace app\common\model { class User { public $id; public static $events = []; public static function event($name, $callback) { self::$events[$name] = $callback; } } }
namespace app\admin\model { class User { public $id; public static $events = []; public static function event($name, $callback) { self::$events[$name] = $callback; } } }
namespace {
    require dirname(__DIR__) . '/Checkin.php';
    $plugin = new \addons\checkin\Checkin();
    $plugin->appInit();
    $a = new \app\admin\model\User(); $a->id = 11;
    $c = new \app\common\model\User(); $c->id = 22;
    (\app\admin\model\User::$events['after_delete'])($a);
    (\app\common\model\User::$events['after_delete'])($c);
    $plugin->userDeleteSuccessed($c);
    if (\think\Db::$deleted !== [11, 22, 22]) throw new \RuntimeException('unexpected cleanup');
    echo "PASS: admin and common member deletion clean up only checkin relations\n";
}
