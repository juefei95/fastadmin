<?php
namespace addons\code2yellow;

use app\common\library\Menu;
use think\Addons;
use think\Db;

class Code2yellow extends Addons
{
    protected function setup()
    {
        $table = config('database.prefix') . 'code2yellow_user';
        Db::execute("CREATE TABLE IF NOT EXISTS `{$table}` (`id` int unsigned NOT NULL AUTO_INCREMENT, `user_id` int unsigned NOT NULL, `created_at` int unsigned NOT NULL, `last_login_at` int unsigned NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `user_id` (`user_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Code2Yellow用户'");
        $announcementTable = config('database.prefix') . 'code2yellow_announcement';
        Db::execute("CREATE TABLE IF NOT EXISTS `{$announcementTable}` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `title` varchar(120) NOT NULL,
            `content` text NOT NULL,
            `status` varchar(16) NOT NULL DEFAULT 'draft',
            `mode` varchar(16) NOT NULL DEFAULT 'all',
            `min_version` varchar(32) NOT NULL DEFAULT '',
            `max_version` varchar(32) NOT NULL DEFAULT '',
            `is_pinned` tinyint unsigned NOT NULL DEFAULT 0,
            `is_urgent` tinyint unsigned NOT NULL DEFAULT 0,
            `revision` int unsigned NOT NULL DEFAULT 1,
            `start_at` int unsigned NOT NULL DEFAULT 0,
            `end_at` int unsigned NOT NULL DEFAULT 0,
            `published_at` int unsigned NOT NULL DEFAULT 0,
            `created_at` int unsigned NOT NULL DEFAULT 0,
            `updated_at` int unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`), KEY `visible` (`status`, `start_at`, `end_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='跨设备传输工具公告'");
    }
    protected function menu()
    {
        return [['name' => 'code2yellow', 'title' => '跨设备传输工具', 'icon' => 'fa fa-code', 'ismenu' => 1, 'sublist' => [
            ['name' => 'code2yellow/user', 'title' => '用户管理', 'icon' => 'fa fa-user', 'ismenu' => 1, 'sublist' => [
                ['name' => 'code2yellow/user/index', 'title' => '查看']
            ]],
            ['name' => 'code2yellow/announcement', 'title' => '公告管理', 'icon' => 'fa fa-bullhorn', 'ismenu' => 1, 'sublist' => [
                ['name' => 'code2yellow/announcement/index', 'title' => '查看'],
                ['name' => 'code2yellow/announcement/add', 'title' => '添加'],
                ['name' => 'code2yellow/announcement/edit', 'title' => '编辑'],
                ['name' => 'code2yellow/announcement/del', 'title' => '删除']
            ]],
            ['name' => 'code2yellow/config', 'title' => '插件设置', 'icon' => 'fa fa-cog', 'url' => 'addon/config?name=code2yellow', 'ismenu' => 1, 'menutype' => 'addtabs']
        ]]];
    }
    public function install() { $this->setup(); Menu::create($this->menu()); return true; }
    public function enable() { $this->setup(); Menu::upgrade('code2yellow', $this->menu()); Menu::enable('code2yellow'); return true; }
    public function upgrade() { $this->setup(); if (!Menu::upgrade('code2yellow', $this->menu())) { throw new \think\Exception('更新 Code2Yellow 菜单失败'); } return true; }
    public function disable() { Menu::disable('code2yellow'); return true; }
    public function uninstall() { Menu::delete('code2yellow'); return true; }
}
