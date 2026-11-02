<?php
namespace addons\legacycheckin;

use think\Addons;

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

    public function install() { return true; }
    public function uninstall() { return true; }
    public function enable() { return true; }
    public function disable() { return true; }
}
