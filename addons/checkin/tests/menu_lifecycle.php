<?php
namespace think { class Addons {} }
namespace app\admin\model { class AuthRule { public static function getByName($name) { return isset(\app\common\library\Menu::$rules[$name]) ? ['name' => $name] : null; } } }
namespace app\common\library {
    class Menu {
        public static $rules = [];
        public static function create($menu, $parent = 0) {
            foreach ($menu as $item) {
                if (isset(self::$rules[$item['name']])) throw new \RuntimeException('duplicate menu: ' . $item['name']);
                self::$rules[$item['name']] = true;
                if (!empty($item['sublist'])) self::create($item['sublist']);
            }
        }
        public static function upgrade($name, $menu) {
            foreach ($menu as $item) {
                self::$rules[$item['name']] = true;
                if (!empty($item['sublist'])) self::upgrade($name, $item['sublist']);
            }
            return true;
        }
        public static function enable($name) { foreach (self::$rules as $key => $value) if ($key === $name || strpos($key, $name . '/') === 0) self::$rules[$key] = true; return true; }
        public static function disable($name) { foreach (self::$rules as $key => $value) if ($key === $name || strpos($key, $name . '/') === 0) self::$rules[$key] = false; return true; }
        public static function refresh($name) { return true; }
    }
}
namespace {
    require dirname(__DIR__) . '/Checkin.php';
    $plugin = new \addons\checkin\Checkin();
    try {
        $plugin->install();
        $plugin->disable();
        $plugin->enable();
        unset(\app\common\library\Menu::$rules['checkin/user/index']);
        $plugin->disable();
        $plugin->enable();
        if (empty(\app\common\library\Menu::$rules['checkin/user/index'])) throw new \RuntimeException('missing user menu');
        unset(\app\common\library\Menu::$rules['checkin/config']);
        $plugin->enable();
        if (empty(\app\common\library\Menu::$rules['checkin/config'])) throw new \RuntimeException('missing config menu');
        foreach (\app\common\library\Menu::$rules as $enabled) if (!$enabled) throw new \RuntimeException('menu remained disabled');
        $plugin->disable();
        $plugin->enable();
        echo "PASS: repeated disable/enable\n";
    } catch (\Throwable $e) {
        fwrite(STDERR, "FAIL: " . $e->getMessage() . "\n");
        exit(1);
    }
}
