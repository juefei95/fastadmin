<?php

namespace addons\remotecontrol;

use app\common\library\Menu;
use think\Addons;
use think\Config;
use think\Db;

/**
 * 插件
 */
class Remotecontrol extends Addons
{
    protected function _initialize()
    {
        if (!Config::get('app_debug')) {
            return;
        }
        $localFile = $this->addonPath . 'config.local.php';
        if (!is_file($localFile)) {
            return;
        }
        $localConfig = include $localFile;
        if (is_array($localConfig)) {
            $localConfig = $this->normalizeLocalConfig($localConfig);
            $config = array_replace_recursive($this->getConfig($this->getName(), true), $localConfig);
            $this->setConfig($this->getName(), $config);
        }
    }

    protected function normalizeLocalConfig(array $localConfig)
    {
        $normalized = [];
        foreach ($localConfig as $key => $value) {
            if (is_array($value) && isset($value['name']) && array_key_exists('value', $value)) {
                $normalized[(string)$value['name']] = $value['value'];
            } elseif (is_string($key)) {
                $normalized[$key] = $value;
            }
        }
        return $normalized;
    }

    /**
     * 插件安装方法
     * @return bool
     */
    public function install()
    {
        $this->ensureDeviceTable();
        $this->ensureRdpTunnelTable();
        if (!$this->installApiFiles() || !$this->installAssets() || !$this->registerHeartbeatRoutes()) {
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
        $this->ensureRdpTunnelTable();
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
        if (!$this->installApiFiles() || !$this->installAssets() || !$this->registerHeartbeatRoutes() || !$this->registerConsoleCommand()) {
            return false;
        }
        $table = config('database.prefix') . 'remote_package';
        $columns = Db::query("SHOW COLUMNS FROM `{$table}` LIKE 'original_price'");
        if (!$columns) {
            Db::execute("ALTER TABLE `{$table}` ADD `original_price` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '原价' AFTER `price`");
            Db::execute("UPDATE `{$table}` SET `original_price` = `price` * 2");
        }
        $this->ensureDeviceTable();
        $this->ensureRdpTunnelTable();
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

    protected function ensureRdpTunnelTable()
    {
        $tunnelTable = config('database.prefix') . 'remote_rdp_tunnel';
        Db::execute("CREATE TABLE IF NOT EXISTS `{$tunnelTable}` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
            `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
            `device_id` varchar(64) NOT NULL DEFAULT '' COMMENT 'RustDesk设备ID',
            `proxy_name` varchar(128) NOT NULL DEFAULT '' COMMENT 'FRP代理名称',
            `public_port` int(10) unsigned DEFAULT NULL COMMENT 'FRP公网端口',
            `status` varchar(20) NOT NULL DEFAULT 'inactive' COMMENT '状态:inactive=未启用,allocated=已分配,active=运行中,stopping=等待客户端停止',
            `last_error` varchar(255) NOT NULL DEFAULT '' COMMENT '最近错误',
            `created_at` bigint(16) DEFAULT NULL COMMENT '创建时间',
            `updated_at` bigint(16) DEFAULT NULL COMMENT '更新时间',
            `activated_at` bigint(16) DEFAULT NULL COMMENT '启用时间',
            `deactivated_at` bigint(16) DEFAULT NULL COMMENT '停用时间',
            PRIMARY KEY (`id`),
            UNIQUE KEY `user_device` (`user_id`,`device_id`),
            KEY `proxy_name` (`proxy_name`),
            UNIQUE KEY `public_port` (`public_port`),
            KEY `status` (`status`),
            KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Windows远程桌面FRP映射表'");
        $columns = Db::query("SHOW COLUMNS FROM `{$tunnelTable}` LIKE 'proxy_name'");
        if (!$columns) {
            Db::execute("ALTER TABLE `{$tunnelTable}` ADD `proxy_name` varchar(128) NOT NULL DEFAULT '' COMMENT 'FRP代理名称' AFTER `device_id`, ADD KEY `proxy_name` (`proxy_name`)");
        }
        Db::name('remote_rdp_tunnel')->where('status', 'pending')->update(['status' => 'allocated']);
        Db::name('remote_rdp_tunnel')->where('status', 'disable_failed')->update(['status' => 'stopping']);
    }

    protected function installApiFiles()
    {
        $files = [
            'api' . DS . 'controller' . DS . 'Remote.php',
            'api' . DS . 'controller' . DS . 'Heartbeat.php',
            'api' . DS . 'controller' . DS . 'Sysinfo.php',
            'api' . DS . 'controller' . DS . 'SysinfoVer.php',
            'api' . DS . 'controller' . DS . 'remote' . DS . 'Index.php',
            'admin' . DS . 'command' . DS . 'RemoteOrder.php',
            'admin' . DS . 'command' . DS . 'RemoteRdpTunnel.php',
            'admin' . DS . 'controller' . DS . 'remotecontrol' . DS . 'RdpTunnel.php',
            'admin' . DS . 'model' . DS . 'remotecontrol' . DS . 'RdpTunnel.php',
            'admin' . DS . 'view' . DS . 'remotecontrol' . DS . 'rdp_tunnel' . DS . 'index.html',
            'admin' . DS . 'controller' . DS . 'remotecontrol' . DS . 'RdpPortPool.php',
            'admin' . DS . 'view' . DS . 'remotecontrol' . DS . 'rdp_port_pool' . DS . 'index.html',
        ];
        $overwrite = [
            'api' . DS . 'controller' . DS . 'Remote.php',
            'admin' . DS . 'command' . DS . 'RemoteRdpTunnel.php',
            'admin' . DS . 'controller' . DS . 'remotecontrol' . DS . 'RdpTunnel.php',
            'admin' . DS . 'model' . DS . 'remotecontrol' . DS . 'RdpTunnel.php',
            'admin' . DS . 'view' . DS . 'remotecontrol' . DS . 'rdp_tunnel' . DS . 'index.html',
            'admin' . DS . 'controller' . DS . 'remotecontrol' . DS . 'RdpPortPool.php',
            'admin' . DS . 'view' . DS . 'remotecontrol' . DS . 'rdp_port_pool' . DS . 'index.html',
        ];
        foreach ($files as $file) {
            $target = APP_PATH . $file;
            if (is_file($target) && !in_array($file, $overwrite, true)) {
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

    protected function installAssets()
    {
        foreach (['rdp_tunnel.js', 'rdp_port_pool.js'] as $file) {
            $source = __DIR__ . DS . 'public' . DS . 'assets' . DS . 'js' . DS . 'backend' . DS . 'remotecontrol' . DS . $file;
            $target = ROOT_PATH . 'public' . DS . 'assets' . DS . 'js' . DS . 'backend' . DS . 'remotecontrol' . DS . $file;
            $directory = dirname($target);
            if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
                return false;
            }
            if (!is_file($source) || !copy($source, $target)) {
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
        $content = @file_get_contents($commandFile);
        if ($content === false) {
            return false;
        }

        $position = strrpos($content, '];');
        if ($position === false) {
            return false;
        }
        $lineEnding = strpos($content, "\r\n") === false ? "\n" : "\r\n";
        $commands = [
            "    'app\\admin\\command\\RemoteOrder',",
            "    'app\\admin\\command\\RemoteRdpTunnel',",
        ];
        $missing = [];
        foreach ($commands as $command) {
            if (strpos($content, $command) === false) {
                $missing[] = $command;
            }
        }
        if (!$missing) {
            return true;
        }
        $updated = substr($content, 0, $position) . implode($lineEnding, $missing) . $lineEnding . substr($content, $position);

        return file_put_contents($commandFile, $updated) !== false;
    }

    protected function unregisterConsoleCommand()
    {
        $commandFile = APP_PATH . 'command.php';
        $content = @file_get_contents($commandFile);
        if ($content === false) {
            return;
        }

        $commands = [
            "    'app\\admin\\command\\RemoteOrder',",
            "    'app\\admin\\command\\RemoteRdpTunnel',",
        ];
        $updated = str_replace([
            $commands[0] . "\r\n", $commands[0] . "\n",
            $commands[1] . "\r\n", $commands[1] . "\n",
        ], '', $content);
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
                        'name'    => 'remotecontrol/rdp_tunnel',
                        'title'   => '远程桌面映射',
                        'icon'    => 'fa fa-exchange',
                        'ismenu'  => 1,
                        'weigh'   => 14,
                        'sublist' => [
                            ['name' => 'remotecontrol/rdp_tunnel/index', 'title' => '查看'],
                            ['name' => 'remotecontrol/rdp_tunnel/close', 'title' => '停用映射'],
                        ],
                    ],
                    [
                        'name'    => 'remotecontrol/rdp_port_pool',
                        'title'   => '远程桌面端口池',
                        'icon'    => 'fa fa-list-ol',
                        'ismenu'  => 1,
                        'weigh'   => 13,
                        'sublist' => [
                            ['name' => 'remotecontrol/rdp_port_pool/index', 'title' => '查看'],
                            ['name' => 'remotecontrol/rdp_port_pool/openaccess', 'title' => '开放公网访问'],
                            ['name' => 'remotecontrol/rdp_port_pool/closeaccess', 'title' => '关闭公网访问'],
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
