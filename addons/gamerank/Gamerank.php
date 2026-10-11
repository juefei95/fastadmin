<?php

namespace addons\gamerank;

use think\Addons;
use app\common\library\Menu;

/**
 * 游戏排行榜
 */
class Gamerank extends Addons
{
    public function install()
    {
        $third = get_addon_info('third');
        if (!$third || empty($third['state'])) {
            throw new \think\Exception('请先安装并启用 third 第三方登录插件');
        }
        Menu::create($this->menu());
        return true;
    }

    public function uninstall()
    {
        Menu::delete('gamerank');
        return true;
    }

    public function enable()
    {
        $third = get_addon_info('third');
        if (!$third || empty($third['state'])) {
            throw new \think\Exception('请先启用 third 第三方登录插件');
        }
        Menu::enable('gamerank');
        return true;
    }

    public function disable()
    {
        Menu::disable('gamerank');
        return true;
    }

    protected function menu()
    {
        return [[
            'name'    => 'gamerank',
            'title'   => '游戏排行榜',
            'icon'    => 'fa fa-trophy',
            'sublist' => [
                ['name' => 'gamerank/player', 'title' => '游戏玩家'],
                ['name' => 'gamerank/pointlog', 'title' => '积分流水'],
            ],
        ]];
    }
}
