<?php

namespace addons\remotecontrol;

use app\common\library\Menu;
use think\Addons;
use think\Db;

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
        $this->ensureDeviceTable();
        if (!$this->installApiFiles() || !$this->registerHeartbeatRoutes()) {
            return false;
        }
        if (!$this->registerConsoleCommand()) {
            return false;
        }
        Menu::create($this->getMenu());
        return true;
    }

    /**
     * 插件卸载方法
     * @return bool
     */
    public function uninstall()
    {
        $this->unregisterConsoleCommand();
        Menu::delete('remotecontrol');
        return true;
    }

    /**
     * 插件启用方法
     * @return bool
     */
    public function enable()
    {
        $this->ensureDeviceTable();
        if (!Menu::upgrade('remotecontrol', $this->getMenu())) {
            throw new \think\Exception('更新远控菜单失败');
        }
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

    public function upgrade()
    {
        if (!$this->registerHeartbeatRoutes()) {
            return false;
        }
        $table = config('database.prefix') . 'remote_package';
        $columns = Db::query("SHOW COLUMNS FROM `{$table}` LIKE 'original_price'");
        if (!$columns) {
            Db::execute("ALTER TABLE `{$table}` ADD `original_price` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '原价' AFTER `price`");
            Db::execute("UPDATE `{$table}` SET `original_price` = `price` * 2");
        }
        $this->ensureDeviceTable();
        if (!Menu::upgrade('remotecontrol', $this->getMenu())) {
            throw new \think\Exception('更新远控设备状态菜单失败');
        }
        return true;
    }

    protected function ensureDeviceTable()
    {
        $deviceTable = config('database.prefix') . 'remote_device';
        Db::execute("CREATE TABLE IF NOT EXISTS `{$deviceTable}` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
            `device_id` varchar(64) NOT NULL DEFAULT '' COMMENT 'RustDesk设备ID',
            `device_uuid` varchar(128) NOT NULL DEFAULT '' COMMENT '设备UUID',
            `client_version` varchar(64) NOT NULL DEFAULT '' COMMENT '客户端版本',
            `hostname` varchar(255) NOT NULL DEFAULT '' COMMENT '主机名',
            `username` varchar(255) NOT NULL DEFAULT '' COMMENT '用户名',
            `connection_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '活动连接数',
            `last_seen_at` bigint(16) DEFAULT NULL COMMENT '最后心跳时间',
            `sysinfo` mediumtext COMMENT '客户端系统信息',
            `sysinfo_updated_at` bigint(16) DEFAULT NULL COMMENT '系统信息更新时间',
            `created_at` bigint(16) DEFAULT NULL COMMENT '创建时间',
            `updated_at` bigint(16) DEFAULT NULL COMMENT '更新时间',
            PRIMARY KEY (`id`),
            UNIQUE KEY `device_id` (`device_id`),
            KEY `last_seen_at` (`last_seen_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='远控客户端设备心跳表'");
    }

    protected function installApiFiles()
    {
        $files = [
            'api' . DS . 'controller' . DS . 'Remote.php',
            'api' . DS . 'controller' . DS . 'Heartbeat.php',
            'api' . DS . 'controller' . DS . 'Sysinfo.php',
            'api' . DS . 'controller' . DS . 'SysinfoVer.php',
            'api' . DS . 'controller' . DS . 'remote' . DS . 'Index.php',
        ];
        foreach ($files as $file) {
            $target = APP_PATH . $file;
            if (is_file($target)) {
                continue;
            }
            $directory = dirname($target);
            if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
                return false;
            }
            if (!copy(__DIR__ . DS . 'application' . DS . $file, $target)) {
                return false;
            }
        }
        return true;
    }

    protected function registerHeartbeatRoutes()
    {
        $routeFile = APP_PATH . 'route.php';
        $content = @file_get_contents($routeFile);
        if ($content === false) {
            return false;
        }
        if (strpos($content, "Route::post('api/heartbeat'") !== false) {
            return true;
        }
        if (strpos($content, 'use think\\Route;') === false) {
            $content = preg_replace('/^<\?php\R?/', "<?php\n\nuse think\\Route;\n", $content, 1);
        }
        $routes = "\nRoute::post('api/heartbeat', 'api/remote/heartbeat');\n"
            . "Route::post('api/sysinfo', 'api/remote/sysinfo');\n"
            . "Route::post('api/sysinfo_ver', 'api/remote/sysinfover');\n";
        $updated = preg_replace('/return\s*\[/', $routes . "\nreturn [", $content, 1);
        return $updated !== null && $updated !== $content && file_put_contents($routeFile, $updated) !== false;
    }

    protected function registerConsoleCommand()
    {
        $commandFile = APP_PATH . 'command.php';
        $command = "    'app\\admin\\command\\RemoteOrder',";
        $content = @file_get_contents($commandFile);
        if ($content === false || strpos($content, $command) !== false) {
            return $content !== false;
        }

        $position = strrpos($content, '];');
        if ($position === false) {
            return false;
        }
        $lineEnding = strpos($content, "\r\n") === false ? "\n" : "\r\n";
        $updated = substr($content, 0, $position) . $command . $lineEnding . substr($content, $position);

        return file_put_contents($commandFile, $updated) !== false;
    }

    protected function unregisterConsoleCommand()
    {
        $commandFile = APP_PATH . 'command.php';
        $command = "    'app\\admin\\command\\RemoteOrder',";
        $content = @file_get_contents($commandFile);
        if ($content === false) {
            return;
        }

        $updated = str_replace([$command . "\r\n", $command . "\n"], '', $content);
        if ($updated !== $content) {
            file_put_contents($commandFile, $updated);
        }
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
                        'name'    => 'remotecontrol/device',
                        'title'   => '设备状态',
                        'icon'    => 'fa fa-heartbeat',
                        'ismenu'  => 1,
                        'weigh'   => 15,
                        'sublist' => [
                            ['name' => 'remotecontrol/device/index', 'title' => '查看'],
                        ],
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
