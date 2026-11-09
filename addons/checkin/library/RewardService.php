<?php
namespace addons\checkin\library;

use addons\third\model\Third;
use addons\wallet\library\Wallet;
use app\common\model\User;
use think\Db;

class RewardService
{
    private static function types($legacy)
    {
        return $legacy
            ? ['daily' => 'legacy_checkin_daily', 'checkin' => 'legacy_checkin', 'ad' => 'legacy_ad', 'invite' => 'legacy_invite', 'platform' => 'wechat_legacy']
            : ['daily' => 'checkin_daily', 'checkin' => 'checkin', 'ad' => 'checkin_ad', 'invite' => 'checkin_invite', 'platform' => 'wechat'];
    }

    public static function checkin($userId, $reward, $legacy = false)
    {
        if ($reward < 1 || ($legacy && $reward > 10)) throw new \InvalidArgumentException('签到奖励无效');
        $types = self::types($legacy);
        $date = date('Y-m-d');
        return Db::transaction(function () use ($userId, $reward, $types, $date) {
            Db::name($types['daily'])->insert(['user_id' => $userId, 'check_date' => $date, 'reward' => $reward, 'createtime' => time()]);
            return Wallet::change($userId, $reward, $types['checkin'], '每日签到');
        });
    }

    public static function checkedToday($userId, $legacy = false)
    {
        $types = self::types($legacy);
        return (bool)Db::name($types['daily'])->where('user_id', $userId)->where('check_date', date('Y-m-d'))->find();
    }

    public static function todayRewards($userId, $legacy = false)
    {
        $types = self::types($legacy);
        $start = strtotime('today');
        $checkin = (int)Db::name($types['daily'])->where('user_id', $userId)->where('check_date', date('Y-m-d'))->sum('reward');
        $ad = (int)Db::name('wallet_ledger')->where('user_id', $userId)->where('type', $types['ad'])->where('createtime', '>=', $start)->sum('amount');
        $share = (int)Db::name('wallet_ledger')->where('user_id', $userId)->where('type', $types['invite'])->where('createtime', '>=', $start)->sum('amount');
        return ['checkin' => $checkin, 'ad' => $ad, 'share' => $share, 'total' => $checkin + $ad + $share, 'score' => Wallet::balance($userId)];
    }

    public static function adReward($userId, $amount, $legacy = false)
    {
        if ($amount < 1 || $amount > 10) throw new \InvalidArgumentException('广告奖励无效');
        $type = self::types($legacy)['ad'];
        return Db::transaction(function () use ($userId, $amount, $type) {
            return Wallet::change($userId, $amount, $type, '观看视频广告');
        });
    }

    public static function claimInvitation($visitorId, $inviterId, $legacy = false)
    {
        if ($inviterId <= 0 || $inviterId === $visitorId) throw new \InvalidArgumentException('无效的邀请');
        $types = self::types($legacy);
        return Db::transaction(function () use ($visitorId, $inviterId, $types) {
            if (!User::get($inviterId) || !Third::where('user_id', $inviterId)->where('platform', $types['platform'])->where('apptype', 'miniapp')->find()) return false;
            Db::name('user')->where('id', $visitorId)->lock(true)->find();
            if (Db::name('wallet_ledger')->where('source_type', $types['invite'])->where('source_id', $visitorId)->find()) return false;
            Wallet::change($inviterId, 5, $types['invite'], '邀请好友:' . $visitorId, 'coin', $types['invite'], $visitorId);
            return true;
        });
    }

    public static function virtualLocation($userId, $legacy = false)
    {
        $type = $legacy ? 'legacy_virtual' : 'virtual_location';
        return Db::transaction(function () use ($userId, $type) {
            Db::name('wallet_account')->where('user_id', $userId)->where('currency', 'coin')->lock(true)->find();
            $recent = Db::name('wallet_ledger')->where('user_id', $userId)->where('currency', 'coin')
                ->where('type', $type)->where('createtime', '>=', time() - 10800)->find();
            if ($recent) return ['score' => Wallet::balance($userId), 'deducted' => 0];
            return ['score' => Wallet::change($userId, -2, $type, '虚拟定位'), 'deducted' => 2];
        });
    }
}
