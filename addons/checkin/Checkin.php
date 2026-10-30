<?php
namespace addons\checkin;

use think\Addons;
use addons\wallet\library\Wallet;

class Checkin extends Addons
{
    public function userRegisterSuccessed(\app\common\model\User $user)
    {
        $config = get_addon_config('checkin') ?: [];
        $value = isset($config['initial_coin']) ? (string)$config['initial_coin'] : '0';
        $amount = ctype_digit($value) ? (int)$value : 0;
        if ($amount > 0) {
            Wallet::change($user->id, $amount, 'registration', '新会员初始金币');
        }
    }

    public function install() { return true; }
    public function uninstall() { return true; }
    public function enable() { return true; }
    public function disable() { return true; }
}
