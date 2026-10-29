<?php
namespace addons\wallet\library;

use think\Db;

class Wallet
{
    public static function balance($userId, $currency = 'coin')
    {
        $balance = Db::name('wallet_account')->where('user_id', $userId)->where('currency', $currency)->value('balance');
        return $balance === null ? 0 : (int)$balance;
    }

    // The caller wraps this and its business record in a transaction.
    public static function change($userId, $amount, $type, $memo = '', $currency = 'coin', $sourceType = null, $sourceId = null)
    {
        if ($userId <= 0 || !$amount || !preg_match('/^[a-z][a-z0-9_]{0,15}$/', $currency) || !preg_match('/^[a-z][a-z0-9_]{0,31}$/', $type)) {
            throw new \InvalidArgumentException('钱包参数无效');
        }
        $now = time();
        Db::execute('INSERT IGNORE INTO `' . config('database.prefix') . 'wallet_account` (`user_id`,`currency`,`balance`,`createtime`,`updatetime`) VALUES (?,?,0,?,?)', [$userId, $currency, $now, $now]);
        $account = Db::name('wallet_account')->where('user_id', $userId)->where('currency', $currency)->lock(true)->find();
        $before = (int)$account['balance'];
        $after = $before + (int)$amount;
        if ($after < 0) throw new \Exception('余额不足');
        Db::name('wallet_account')->where('user_id', $userId)->where('currency', $currency)->update(['balance' => $after, 'updatetime' => $now]);
        Db::name('wallet_ledger')->insert(['user_id' => $userId, 'currency' => $currency, 'amount' => $amount, 'before' => $before, 'after' => $after, 'type' => $type, 'source_type' => $sourceType, 'source_id' => $sourceId, 'memo' => $memo, 'createtime' => $now]);
        return $after;
    }
}
