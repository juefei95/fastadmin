<?php

namespace addons\remotecontrol;

use app\common\library\Menu;
use think\Addons;

/**
 * 插件
 */
class Remotecontrol extends Addons
{

    /**
     * 插件安装方法
     * @return bool
     */
    public function install()
    {
        Menu::create($this->getMenu());
        return true;
    }

    /**
     * 插件卸载方法
     * @return bool
     */
    public function uninstall()
    {
        Menu::delete('remotecontrol');
        return true;
    }

    /**
     * 插件启用方法
     * @return bool
     */
    public function enable()
    {
        Menu::enable('remotecontrol');
        return true;
    }

    /**
     * 插件禁用方法
     * @return bool
     */
    public function disable()
    {
        Menu::disable('remotecontrol');
        return true;
    }

    protected function getMenu()
    {
        return [
            [
                'name'    => 'remotecontrol',
                'title'   => '远控管理',
                'icon'    => 'fa fa-desktop',
                'ismenu'  => 1,
                'weigh'   => 100,
                'sublist' => [
                    [
                        'name'    => 'remotecontrol/member',
                        'title'   => '用户权益',
                        'icon'    => 'fa fa-user',
                        'ismenu'  => 1,
                        'weigh'   => 40,
                        'sublist' => array_merge($this->getCrudMenu('remotecontrol/member'), [
                            ['name' => 'remotecontrol/member/adddays', 'title' => '增加有效期'],
                            ['name' => 'remotecontrol/member/setexpire', 'title' => '设置到期时间'],
                            ['name' => 'remotecontrol/member/enable', 'title' => '启用控制权限'],
                            ['name' => 'remotecontrol/member/disable', 'title' => '禁用控制权限'],
                        ]),
                    ],
                    [
                        'name'    => 'remotecontrol/package',
                        'title'   => '套餐管理',
                        'icon'    => 'fa fa-diamond',
                        'ismenu'  => 1,
                        'weigh'   => 30,
                        'sublist' => $this->getCrudMenu('remotecontrol/package'),
                    ],
                    [
                        'name'    => 'remotecontrol/order',
                        'title'   => '订单管理',
                        'icon'    => 'fa fa-list-alt',
                        'ismenu'  => 1,
                        'weigh'   => 20,
                        'sublist' => $this->getCrudMenu('remotecontrol/order'),
                    ],
                    [
                        'name'     => 'remotecontrol/config',
                        'title'    => '插件设置',
                        'icon'     => 'fa fa-cog',
                        'url'      => 'addon/config?name=remotecontrol',
                        'ismenu'   => 1,
                        'menutype' => 'addtabs',
                        'weigh'    => 10,
                    ],
                ],
            ],
        ];
    }

    protected function getCrudMenu($name)
    {
        return [
            ['name' => $name . '/index', 'title' => '查看'],
            ['name' => $name . '/add', 'title' => '添加'],
            ['name' => $name . '/edit', 'title' => '编辑'],
            ['name' => $name . '/del', 'title' => '删除'],
            ['name' => $name . '/multi', 'title' => '批量更新'],
            ['name' => $name . '/dragsort', 'title' => '排序'],
        ];
    }

}
