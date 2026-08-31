<?php

namespace addons\remotecontrol\library;

use think\Db;
use think\Exception;

class RemoteMemberService
{
    public function grantTrial($userId, $days = null)
    {
        $userId = $this->normalizeUserId($userId);
        $days = $days === null ? $this->getTrialDays() : max(1, (int)$days);
        $now = time();

        Db::startTrans();
        try {
            $member = $this->getMemberForUpdate($userId);
            if (!$member) {
                $member = $this->createMember($userId);
            }

            if ((int)$member['trial_given'] === 1) {
                Db::commit();
                return $this->getMember($userId);
            }

            $expireTime = $now + $days * 86400;
            Db::name('remote_member')->where('user_id', $userId)->update([
                'trial_given'       => 1,
                'trial_started_at'  => $now,
                'expire_time'       => $expireTime,
                'control_enabled'   => 1,
                'updated_at'        => $now,
            ]);

            Db::commit();
            return $this->getMember($userId);
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    public function canControl($userId, $now = null)
    {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return false;
        }

        $member = $this->getMember($userId);
        if (!$member || (int)$member['control_enabled'] !== 1) {
            return false;
        }

        $expireTime = (int)($member['expire_time'] ?? 0);
        return $expireTime > ($now ?: time());
    }

    public function addDays($userId, $days, $paidAmount = null, $paidAt = null, $useTransaction = true)
    {
        $userId = $this->normalizeUserId($userId);
        $days = (int)$days;
        if ($days <= 0) {
            throw new Exception('Days must be greater than 0');
        }

        $now = time();
        $paidAt = $paidAt ?: $now;

        if ($useTransaction) {
            Db::startTrans();
        }
        try {
            $this->addDaysWithoutTransaction($userId, $days, $paidAmount, $paidAt, $now);
            if ($useTransaction) {
                Db::commit();
            }
            return $this->getMember($userId);
        } catch (\Exception $e) {
            if ($useTransaction) {
                Db::rollback();
            }
            throw $e;
        }
    }

    public function disable($userId)
    {
        return $this->setControlEnabled($userId, 0);
    }

    public function enable($userId)
    {
        return $this->setControlEnabled($userId, 1);
    }

    public function setExpire($userId, $expireTime)
    {
        $userId = $this->normalizeUserId($userId);
        $expireTime = $this->normalizeExpireTime($expireTime);
        $member = $this->getMember($userId);
        if (!$member) {
            $member = $this->createMember($userId);
        }

        Db::name('remote_member')->where('user_id', $userId)->update([
            'expire_time' => $expireTime,
            'updated_at'  => time(),
        ]);

        return $this->getMember($userId);
    }

    public function getMember($userId)
    {
        return Db::name('remote_member')->where('user_id', (int)$userId)->find();
    }

    protected function setControlEnabled($userId, $enabled)
    {
        $userId = $this->normalizeUserId($userId);
        $member = $this->getMember($userId);
        if (!$member) {
            $member = $this->createMember($userId);
        }

        Db::name('remote_member')->where('user_id', $userId)->update([
            'control_enabled' => $enabled ? 1 : 0,
            'updated_at'      => time(),
        ]);

        return $this->getMember($userId);
    }

    protected function getMemberForUpdate($userId)
    {
        return Db::name('remote_member')->where('user_id', $userId)->lock(true)->find();
    }

    protected function createMember($userId)
    {
        $now = time();
        $data = [
            'user_id'         => $userId,
            'expire_time'     => null,
            'trial_given'     => 0,
            'control_enabled' => 1,
            'total_paid'      => '0.00',
            'created_at'      => $now,
            'updated_at'      => $now,
        ];
        Db::name('remote_member')->insert($data);

        return $this->getMemberForUpdate($userId);
    }

    protected function addDaysWithoutTransaction($userId, $days, $paidAmount, $paidAt, $now)
    {
        $member = $this->getMemberForUpdate($userId);
        if (!$member) {
            $member = $this->createMember($userId);
        }

        $baseTime = max($now, (int)($member['expire_time'] ?? 0));
        $data = [
            'expire_time'     => $baseTime + $days * 86400,
            'control_enabled' => 1,
            'updated_at'      => $now,
        ];

        if ($paidAmount !== null) {
            $data['last_paid_at'] = $paidAt;
            $data['total_paid'] = bcadd((string)$member['total_paid'], (string)$paidAmount, 2);
        }

        Db::name('remote_member')->where('user_id', $userId)->update($data);
    }

    protected function normalizeUserId($userId)
    {
        $userId = (int)$userId;
        if ($userId <= 0) {
            throw new Exception('User ID must be greater than 0');
        }
        return $userId;
    }

    protected function normalizeExpireTime($expireTime)
    {
        if (is_numeric($expireTime)) {
            $expireTime = (int)$expireTime;
        } else {
            $expireTime = strtotime((string)$expireTime);
        }

        if (!$expireTime) {
            throw new Exception('Expire time is invalid');
        }

        return $expireTime;
    }

    protected function getTrialDays()
    {
        $config = get_addon_config('remotecontrol');
        return max(1, (int)($config['trial_days'] ?? 3));
    }
}
