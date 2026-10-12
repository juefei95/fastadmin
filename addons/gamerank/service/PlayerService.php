<?php

namespace addons\gamerank\service;

use addons\gamerank\model\Player;
use addons\gamerank\model\PointLog;
use addons\gamerank\model\Record;
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

    public static function settle($userId, $points, $roundId, array $chart, $remark = '')
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
                return ['log' => $existing, 'record' => self::findRecord($userId, $chart), 'duplicated' => true];
            }

            $player = Player::where('user_id', $userId)->lock(true)->find();
            if (!$player) {
                throw new \RuntimeException('玩家档案不存在');
            }

            $before = intval($player->total_points);
            $after = $before + intval($points);
            $now = time();
            $record = self::findRecord($userId, $chart, true);
            if (!$record) {
                $record = Record::create([
                    'user_id'       => $userId,
                    'game_code'     => $chart['game_code'],
                    'song_id'       => $chart['song_id'],
                    'difficulty'    => $chart['difficulty'],
                    'best_score'    => 0,
                    'total_score'   => 0,
                    'best_accuracy' => 0,
                    'max_combo'     => 0,
                    'last_score'    => 0,
                    'play_count'    => 0,
                    'last_played_at'=> 0,
                ], true);
            }
            $isBest = self::isBetterScore($chart, $record);
            $record->save([
                'best_score'     => $isBest ? $chart['score'] : $record->best_score,
                'total_score'    => intval($record->total_score) + intval($chart['score']),
                'best_accuracy'  => $isBest ? $chart['accuracy'] : $record->best_accuracy,
                'max_combo'      => $isBest ? $chart['max_combo'] : $record->max_combo,
                'last_score'     => $chart['score'],
                'play_count'     => intval($record->play_count) + 1,
                'last_played_at' => $now,
            ]);
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
            return ['log' => $log, 'record' => $record->refresh(), 'duplicated' => false];
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

    public static function findRecord($userId, array $chart, $lock = false)
    {
        $query = Record::where('user_id', $userId)
            ->where('game_code', $chart['game_code'])
            ->where('song_id', $chart['song_id'])
            ->where('difficulty', $chart['difficulty']);
        return $lock ? $query->lock(true)->find() : $query->find();
    }

    public static function recordRank(Record $record)
    {
        $recordTable = $record->getTable();
        $playerTable = (new Player())->getTable();
        return Db::table($recordTable)->alias('record')
            ->join($playerTable . ' player', 'player.user_id = record.user_id')
            ->where('player.rank_visible', 1)
            ->where('record.game_code', $record->game_code)
            ->where('record.song_id', $record->song_id)
            ->where('record.difficulty', $record->difficulty)
            ->where(function ($query) use ($record) {
                $query->where('record.best_score', '>', intval($record->best_score))
                    ->whereOr(function ($query) use ($record) {
                        $query->where('record.best_score', intval($record->best_score))
                            ->where('record.id', '<', intval($record->id));
                    });
            })->count() + 1;
    }

    protected static function isBetterScore(array $chart, Record $record)
    {
        return intval($chart['score']) > intval($record->best_score)
            || (intval($chart['score']) === intval($record->best_score)
                && intval($chart['accuracy']) > intval($record->best_accuracy))
            || (intval($chart['score']) === intval($record->best_score)
                && intval($chart['accuracy']) === intval($record->best_accuracy)
                && intval($chart['max_combo']) > intval($record->max_combo));
    }
}
