<?php
namespace addons\legacycheckin;

use think\Addons;
use app\common\library\Menu;
use app\admin\model\AuthRule;

class Legacycheckin extends Addons
{
    private static function legacyActions()
    {
        return [
            'index' => 'index',
            'login' => 'accountlogin',
            'wechatregister' => 'wechatregister',
            'checkin' => 'checkin',
            'todayrewards' => 'todayrewards',
            'claiminvitation' => 'claiminvitation',
            'adreward' => 'adreward',
            'virtuallocation' => 'virtuallocation',
            'changeusername' => 'changeusername',
            'changepwdwithoutold' => 'changepwdwithoutold',
        ];
    }

    public function appInit()
    {
        foreach (self::legacyActions() as $path => $action) {
            \think\Route::rule('api/user/' . $path . '$',
                '\\think\\addons\\Route@execute?addon=legacycheckin&controller=api&action=' . $action,
                $path === 'index' ? 'GET' : 'POST');
        }
    }

    public function appDispatch()
    {
        $path = trim(\think\Request::instance()->pathinfo(), '/');
        if (strpos($path, 'api/user/') !== 0) return;
        $action = substr($path, strlen('api/user/'));
        if (isset(self::legacyActions()[$action])) \think\App::route(true);
    }

    private static function ensureMenu()
    {
        $root = AuthRule::getByName('legacycheckin');
        if (!$root) {
            Menu::create([['name' => 'legacycheckin', 'title' => '旧版打卡签到管理', 'icon' => 'fa fa-history', 'ismenu' => 1]]);
        } else {
            $changes = [];
            if (!(int)$root['ismenu']) $changes['ismenu'] = 1;
            if ($root['title'] === '旧版打卡管理') $changes['title'] = '旧版打卡签到管理';
            if ($changes) $root->save($changes);
        }
        if (!AuthRule::getByName('legacycheckin/user/index')) {
            Menu::create([['name' => 'legacycheckin/user/index', 'title' => '旧版打卡用户', 'ismenu' => 1]], 'legacycheckin');
        }
        Menu::refresh('legacycheckin');
    }

    public function install() { self::ensureMenu(); return true; }
    public function uninstall() { Menu::delete('legacycheckin'); return true; }
    public function enable() { self::ensureMenu(); Menu::enable('legacycheckin'); return true; }
    public function disable() { Menu::disable('legacycheckin'); return true; }
}
