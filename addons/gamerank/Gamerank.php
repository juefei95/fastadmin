<?php

namespace addons\gamerank;

use think\Addons;
use think\Config;
use app\common\library\Menu;

/**
 * 游戏排行榜
 */
class Gamerank extends Addons
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

    public function upgrade()
    {
        $prefix = \think\Config::get('database.prefix');
        \think\Db::execute("CREATE TABLE IF NOT EXISTS `{$prefix}game_rank_record` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` int(10) unsigned NOT NULL,
            `game_code` varchar(32) NOT NULL DEFAULT 'paw_beat',
            `song_id` varchar(64) NOT NULL,
            `difficulty` varchar(32) NOT NULL,
            `best_score` int(11) NOT NULL DEFAULT '0',
            `total_score` bigint(20) unsigned NOT NULL DEFAULT '0',
            `best_accuracy` int(11) NOT NULL DEFAULT '0',
            `max_combo` int(11) NOT NULL DEFAULT '0',
            `last_score` int(11) NOT NULL DEFAULT '0',
            `play_count` int(11) unsigned NOT NULL DEFAULT '0',
            `last_played_at` int(10) unsigned NOT NULL DEFAULT '0',
            `createtime` int(10) unsigned NOT NULL DEFAULT '0',
            `updatetime` int(10) unsigned NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_player_chart` (`user_id`,`game_code`,`song_id`,`difficulty`),
            KEY `idx_chart_rank` (`game_code`,`song_id`,`difficulty`,`best_score`,`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='游戏曲目难度成绩'");
        $recordTable = $prefix . 'game_rank_record';
        $columns = \think\Db::query("SHOW COLUMNS FROM `{$recordTable}` LIKE 'total_score'");
        if (!$columns) {
            \think\Db::execute("ALTER TABLE `{$recordTable}` ADD `total_score` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '累计成绩' AFTER `best_score`");
        }
        Menu::create($this->menu());
        return true;
    }

    protected function menu()
    {
        return [[
            'name'    => 'gamerank',
            'title'   => '游戏排行榜',
            'icon'    => 'fa fa-trophy',
            'sublist' => [
                ['name' => 'gamerank/player', 'title' => '游戏玩家', 'ismenu' => 1],
                ['name' => 'gamerank/record', 'title' => '曲目成绩', 'ismenu' => 1],
                ['name' => 'gamerank/pointlog', 'title' => '积分流水', 'ismenu' => 1],
            ],
        ]];
    }
}
