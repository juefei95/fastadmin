<?php

namespace addons\remotecontrol\library;

use think\Db;
use think\Exception;

class RemoteLoginDeviceService
{
    public function registerLogin($userId, $deviceId, $deviceUuid, $token)
    {
        $userId = (int)$userId;
        $deviceId = trim((string)$deviceId);
        $deviceUuid = trim((string)$deviceUuid);
        $token = trim((string)$token);
        if ($userId <= 0 || $token === '' || ($deviceId === '' && $deviceUuid === '')) {
            throw new Exception('无法识别当前登录设备');
        }
        if ($deviceUuid === '') {
            $deviceUuid = 'id:' . $deviceId;
        }

        Db::startTrans();
        try {
            Db::name('user')->where('id', $userId)->lock(true)->find();
            $device = Db::name('remote_login_device')->where('user_id', $userId)
                ->where('device_uuid', $deviceUuid)->find();
            $now = time();
            if ($device) {
                Db::name('remote_login_device')->where('id', $device['id'])->update([
                    'device_id' => $deviceId,
                    'token' => $token,
                    'last_login_at' => $now,
                    'updated_at' => $now,
                ]);
                Db::commit();
                return;
            }

            $limit = $this->getLoginDeviceLimit();
            $count = Db::name('remote_login_device')->where('user_id', $userId)->count();
            if ($count >= $limit) {
                throw new Exception('当前账号最多允许登录' . $limit . '台电脑，请先在其他电脑退出账号');
            }

            Db::name('remote_login_device')->insert([
                'user_id' => $userId,
                'device_id' => $deviceId,
                'device_uuid' => $deviceUuid,
                'token' => $token,
                'created_at' => $now,
                'updated_at' => $now,
                'last_login_at' => $now,
            ]);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    public function removeByToken($token)
    {
        $token = trim((string)$token);
        if ($token !== '') {
            Db::name('remote_login_device')->where('token', $token)->delete();
        }
    }

    protected function getLoginDeviceLimit()
    {
        $config = get_addon_config('remotecontrol');
        return max(1, (int)($config['max_login_devices'] ?? 5));
    }
}
