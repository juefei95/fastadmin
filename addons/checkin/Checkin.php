<?php
namespace addons\checkin;

use think\Addons;
use addons\wallet\library\Wallet;
use app\common\library\Menu;
use app\admin\model\AuthRule;
use think\Db;

class Checkin extends Addons
{
    public function config($name, $config)
    {
        foreach ($config as $item) {
            if ($item['name'] === 'payment_notify_url' && is_string($item['value']) && trim($item['value']) !== '') {
                set_addon_fullconfig($name, $config);
                \think\addons\Service::refresh();
                return;
            }
        }
        throw new \Exception('微信支付回调地址不能为空');
    }

    public function appInit()
    {
        foreach ([\app\common\model\User::class, \app\admin\model\User::class] as $userModel) {
            $userModel::event('after_delete', function ($user) { self::deleteCheckinUser($user->id); });
        }
    }

    public function userDeleteSuccessed(\app\common\model\User $user)
    {
        self::deleteCheckinUser($user->id);
    }

    private static function deleteCheckinUser($userId)
    {
        if ((int)$userId > 0) Db::name('checkin_user')->where('user_id', (int)$userId)->delete();
    }

    public function userRegisterSuccessed(\app\common\model\User $user)
    {
        $config = get_addon_config('checkin') ?: [];
        $value = isset($config['initial_coin']) ? (string)$config['initial_coin'] : '0';
        $amount = ctype_digit($value) ? (int)$value : 0;
        if ($amount > 0) {
            Wallet::change($user->id, $amount, 'registration', '新会员初始金币');
        }
    }

    public function install() { Menu::create(self::menu()); return true; }
    public function uninstall() { Menu::delete('checkin'); return true; }
    public function enable()
    {
        $root = AuthRule::getByName('checkin');
        if (!$root) {
            Menu::create(self::menu());
        } else {
            if (($root['title'] ?? '') === '打卡管理') $root->save(['title' => '打卡签到管理']);
            $missing = [];
            foreach ([['name' => 'checkin/user/index', 'title' => '打卡用户', 'ismenu' => 1], ['name' => 'checkin/recharge/createpackage', 'title' => '添加套餐', 'ismenu' => 0], ['name' => 'checkin/recharge/deletepackage', 'title' => '删除套餐', 'ismenu' => 0], self::promotionMenu(), self::promotionSaveRule(), self::announcementMenu(), self::announcementSaveRule(), self::announcementResetRule(), self::configMenu()] as $rule) {
                if (!AuthRule::getByName($rule['name'])) $missing[] = $rule;
            }
            if ($missing) { Menu::create($missing, 'checkin'); Menu::refresh('checkin'); }
        }
        Menu::enable('checkin');
        return true;
    }
    public function disable() { Menu::disable('checkin'); return true; }

    private static function menu()
    {
        return [[
            'name' => 'checkin', 'title' => '打卡签到管理', 'icon' => 'fa fa-calendar',
            'sublist' => [
                ['name' => 'checkin/user/index', 'title' => '打卡用户', 'ismenu' => 1],
                ['name' => 'checkin/recharge/index', 'title' => '充值套餐', 'ismenu' => 1],
                ['name' => 'checkin/recharge/orders', 'title' => '充值订单', 'ismenu' => 1],
                ['name' => 'checkin/recharge/edit', 'title' => '编辑套餐', 'ismenu' => 0],
                ['name' => 'checkin/recharge/createpackage', 'title' => '添加套餐', 'ismenu' => 0],
                ['name' => 'checkin/recharge/deletepackage', 'title' => '删除套餐', 'ismenu' => 0],
                self::promotionMenu(),
                self::promotionSaveRule(),
                self::announcementMenu(),
                self::announcementSaveRule(),
                self::announcementResetRule(),
                self::configMenu(),
            ],
        ]];
    }

    private static function promotionMenu() { return ['name' => 'checkin/promotion/index', 'title' => '推广管理', 'ismenu' => 1]; }
    private static function promotionSaveRule() { return ['name' => 'checkin/promotion/save', 'title' => '保存推广', 'ismenu' => 0]; }

    private static function announcementMenu() { return ['name' => 'checkin/announcement/index', 'title' => '公告管理', 'ismenu' => 1]; }
    private static function announcementSaveRule() { return ['name' => 'checkin/announcement/save', 'title' => '保存公告', 'ismenu' => 0]; }
    private static function announcementResetRule() { return ['name' => 'checkin/announcement/reset', 'title' => '重新弹出公告', 'ismenu' => 0]; }

    private static function configMenu()
    {
        return ['name' => 'checkin/config', 'title' => '插件配置', 'icon' => 'fa fa-cog', 'url' => 'addon/config?name=checkin', 'menutype' => 'dialog', 'ismenu' => 1];
    }
}
