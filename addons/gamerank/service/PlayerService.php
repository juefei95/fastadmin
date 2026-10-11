<?php

namespace addons\gamerank\service;

use addons\gamerank\model\Player;
use addons\gamerank\model\PointLog;
use app\common\model\User;
use think\Db;

class PlayerService
{
    public static function ensurePlayer(User $user)
    {
        $player = Player::get(['user_id' => $user->id]);
        if ($player) {
            return $player;
        }

        try {
            return Player::create([
                'user_id'           => $user->id,
                'nickname'          => mb_substr((string)$user->nickname, 0, 32),
                'avatar_url'        => (string)$user->avatar,
                'total_points'      => 0,
                'rank_visible'      => 1,
                'last_played_at'    => 0,
                'points_updated_at' => 0,
            ], true);
        } catch (\Exception $e) {
            $player = Player::get(['user_id' => $user->id]);
            if ($player) {
                return $player;
            }
            throw $e;
        }
    }

    public static function settle($userId, $points, $roundId, $remark = '')
    {
        $idempotencyKey = 'game:' . $userId . ':' . $roundId;
        Db::startTrans();
        try {
            $existing = PointLog::where('idempotency_key', $idempotencyKey)->lock(true)->find();
            if ($existing) {
                if (intval($existing->user_id) !== intval($userId)) {
                    throw new \RuntimeException('局次标识已被其他玩家使用');
                }
                Db::commit();
                return ['log' => $existing, 'duplicated' => true];
            }

            $player = Player::where('user_id', $userId)->lock(true)->find();
            if (!$player) {
                throw new \RuntimeException('玩家档案不存在');
            }

            $before = intval($player->total_points);
            $after = $before + intval($points);
            $now = time();
            $player->save([
                'total_points'      => $after,
                'last_played_at'    => $now,
                'points_updated_at' => $now,
            ]);
            $log = PointLog::create([
                'user_id'         => $userId,
                'delta'           => intval($points),
                'points_before'   => $before,
                'points_after'    => $after,
                'source_type'     => 'game_settlement',
                'source_ref'      => $roundId,
                'idempotency_key' => $idempotencyKey,
                'remark'          => $remark,
            ], true);
            Db::commit();
            return ['log' => $log, 'duplicated' => false];
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    public static function rank(Player $player)
    {
        if (!$player->rank_visible) {
            return null;
        }
        return Player::where('rank_visible', 1)
            ->where('total_points', '>', intval($player->total_points))->count()
            + Player::where('rank_visible', 1)
                ->where('total_points', intval($player->total_points))
                ->where('id', '<', intval($player->id))->count()
            + 1;
    }
}
