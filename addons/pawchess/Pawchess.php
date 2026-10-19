<?php
namespace addons\pawchess;

class Pawchess extends \think\Addons
{
    /**
     * Keep chess-owned data in sync with FastAdmin member deletion.
     * This hook runs inside Auth::delete()'s transaction.
     */
    public function userDeleteSuccessed(\app\common\model\User $user)
    {
        $player = \think\Db::name('paw_chess_player')->where('member_id', $user->id)->find();
        if (!$player) {
            return;
        }

        $playerId = (int)$player['id'];
        \think\Db::name('paw_chess_dev_identity')->where('player_id', $playerId)->delete();
        \think\Db::name('paw_chess_rating_log')->where('player_id', $playerId)->delete();
        \think\Db::name('paw_chess_match')->where(function ($query) use ($playerId) {
            $query->where('red_id', $playerId)->whereOr('blue_id', $playerId);
        })->delete();
        \think\Db::name('paw_chess_room')->where(function ($query) use ($playerId) {
            $query->where('red_id', $playerId)->whereOr('blue_id', $playerId);
        })->delete();
        \think\Db::name('paw_chess_session')->where('player_id', $playerId)->delete();
        \think\Db::name('paw_chess_player')->where('id', $playerId)->delete();
    }

    public function install() {
        \app\common\library\Menu::create([
            ['name'=>'pawchess','title'=>'毛球象棋社','icon'=>'fa fa-gamepad','sublist'=>[
                ['name'=>'pawchess/overview/players','title'=>'棋手','ismenu'=>1],
                ['name'=>'pawchess/overview/rooms','title'=>'房间','ismenu'=>1],
                ['name'=>'pawchess/overview/matches','title'=>'对局','ismenu'=>1],
                ['name'=>'pawchess/overview/ratinglog','title'=>'积分流水','ismenu'=>1],
            ]],
        ]);
        \think\Cache::rm('__menu__');
        return true;
    }
    public function upgrade() {
        $prefix=(string)\think\Config::get('database.prefix');
        if (!preg_match('/^[A-Za-z0-9_]*$/',$prefix)) throw new \think\Exception('棋馆数据表前缀无效');
        $table=$prefix.'paw_chess_player';
        $columns=\think\Db::query("SHOW COLUMNS FROM `{$table}` LIKE 'member_id'");
        if (!$columns) \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `member_id` bigint unsigned DEFAULT NULL");
        $indexes=\think\Db::query("SHOW INDEX FROM `{$table}` WHERE `Key_name` = 'member_id'");
        if (!$indexes) \think\Db::execute("ALTER TABLE `{$table}` ADD UNIQUE KEY `member_id` (`member_id`)");
        $devTable=$prefix.'paw_chess_dev_identity';
        \think\Db::execute("CREATE TABLE IF NOT EXISTS `{$devTable}` (`identity_hash` char(64) NOT NULL, `player_id` bigint unsigned NOT NULL, `created_at` bigint NOT NULL, PRIMARY KEY (`identity_hash`), UNIQUE KEY `player_id` (`player_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $legacy=\think\Db::query("SHOW COLUMNS FROM `{$table}` LIKE 'identity_hash'");
        if ($legacy) {
            // Preserve unlinked local development identities without keeping hashes in the public player table.
            \think\Db::execute("INSERT IGNORE INTO `{$devTable}` (`identity_hash`,`player_id`,`created_at`) SELECT `identity_hash`,`id`,`created_at` FROM `{$table}` WHERE `member_id` IS NULL");
            $identityIndex=\think\Db::query("SHOW INDEX FROM `{$table}` WHERE `Key_name` = 'identity_hash'");
            if ($identityIndex) \think\Db::execute("ALTER TABLE `{$table}` DROP INDEX `identity_hash`");
            \think\Db::execute("ALTER TABLE `{$table}` DROP COLUMN `identity_hash`");
        }
        \app\common\library\Menu::create([
            ['name'=>'pawchess','title'=>'毛球象棋社','icon'=>'fa fa-gamepad','sublist'=>[
                ['name'=>'pawchess/overview/players','title'=>'棋手','ismenu'=>1],
                ['name'=>'pawchess/overview/rooms','title'=>'房间','ismenu'=>1],
                ['name'=>'pawchess/overview/matches','title'=>'对局','ismenu'=>1],
                ['name'=>'pawchess/overview/ratinglog','title'=>'积分流水','ismenu'=>1],
            ]],
        ]);
        return true;
    }
    public function uninstall() { \app\common\library\Menu::delete('pawchess'); \think\addons\Service::importsql('pawchess','uninstall.sql'); return true; }
    public function enable() { \app\common\library\Menu::enable('pawchess'); \think\Cache::rm('__menu__'); return true; }
    public function disable() { \app\common\library\Menu::disable('pawchess'); \think\Cache::rm('__menu__'); return true; }
    public function appInit() {
        if (\think\Request::instance()->isCli()) {
            \think\Console::addDefaultCommands(['\\addons\\pawchess\\library\\ExpireCommand']);
        }
        $target='\\addons\\pawchess\\controller\\Api@dispatch';
        foreach (['health'=>'GET','api/session'=>'POST','api/me'=>'GET','api/ranking'=>'GET','api/rooms/join'=>'POST','api/rooms'=>'POST','api/rooms/:id/:action'=>'POST','api/rooms/:id'=>'GET'] as $path=>$method) {
            \think\Route::rule($path,$target,$method);
        }
    }
    public function appDispatch() {
        // FastAdmin disables routing for the api module; restore it only for this addon's endpoints.
        $path=trim(\think\Request::instance()->pathinfo(),'/');
        if (preg_match('~^api/(?:session|me|ranking|rooms(?:/.*)?)$~',$path)) {
            \think\App::route(true);
        }
    }
}
