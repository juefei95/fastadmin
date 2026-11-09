<?php
namespace addons\wallet;

use app\common\library\Menu;
use app\admin\model\AuthRule;
use think\Addons;
use think\Cache;
use think\Db;

class Wallet extends Addons
{
    public function install()
    {
        $menu = self::menu();
        $root = AuthRule::getByName('wallet') ?: AuthRule::getByName('wallet/account');
        if (!$root) {
            Menu::create($menu);
            return true;
        }
        Db::transaction(function () use ($root) {
            Db::name('auth_rule')->where('id', $root->id)->update(['name' => 'wallet', 'title' => '钱包管理', 'icon' => 'fa fa-database', 'ismenu' => 1]);
            foreach ([
                'wallet/account/index' => ['会员钱包', 1],
                'wallet/account/ledger' => ['钱包流水', 1],
                'wallet/account/adjust' => ['调整金币', 0],
            ] as $name => $settings) {
                $rule = AuthRule::getByName($name);
                if ($rule) {
                    Db::name('auth_rule')->where('id', $rule->id)->update(['pid' => $root->id, 'title' => $settings[0], 'ismenu' => $settings[1]]);
                } else {
                    AuthRule::create(['pid' => $root->id, 'name' => $name, 'title' => $settings[0], 'ismenu' => $settings[1], 'status' => 'normal']);
                }
            }
        });
        Menu::refresh('wallet', $menu);
        Cache::rm('__menu__');
        return true;
    }

    public function uninstall() { Menu::delete('wallet'); return true; }
    public function enable() { Menu::enable('wallet'); return true; }
    public function disable() { Menu::disable('wallet'); return true; }

    private static function menu()
    {
        return [[
            'name' => 'wallet', 'title' => '钱包管理', 'icon' => 'fa fa-database',
            'sublist' => [
                ['name' => 'wallet/account/index', 'title' => '会员钱包', 'ismenu' => 1],
                ['name' => 'wallet/account/ledger', 'title' => '钱包流水', 'ismenu' => 1],
                ['name' => 'wallet/account/adjust', 'title' => '调整金币', 'ismenu' => 0],
            ],
        ]];
    }

    /** Register model events so admin-created members also receive a wallet. */
    public function appInit()
    {
        foreach ([\app\common\model\User::class, \app\admin\model\User::class] as $userModel) {
            $userModel::event('after_insert', function ($user) { self::createWallet($user->id); });
            $userModel::event('after_delete', function ($user) { self::deleteWallet($user->id); });
        }
    }

    public function userRegisterSuccessed(\app\common\model\User $user)
    {
        self::createWallet($user->id);
    }

    public function userDeleteSuccessed(\app\common\model\User $user)
    {
        self::deleteWallet($user->id);
    }

    private static function createWallet($userId)
    {
        $now = time();
        Db::execute('INSERT IGNORE INTO `' . config('database.prefix') . 'wallet_account` (`user_id`,`currency`,`balance`,`createtime`,`updatetime`) VALUES (?,"coin",0,?,?)', [(int)$userId, $now, $now]);
    }

    private static function deleteWallet($userId)
    {
        Db::name('wallet_ledger')->where('user_id', $userId)->delete();
        Db::name('wallet_account')->where('user_id', $userId)->delete();
    }
}
