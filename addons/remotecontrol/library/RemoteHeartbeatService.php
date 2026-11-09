<?php

namespace addons\remotecontrol\library;

use think\Db;
use think\Exception;

class RemoteHeartbeatService
{
    public function recordHeartbeat(array $payload)
    {
        return $this->saveDevice($payload, false);
    }

    public function recordSysinfo(array $payload)
    {
        return $this->saveDevice($payload, true);
    }

    public function getSysinfoVersion($deviceId)
    {
        if ($deviceId === '') {
            return '';
        }

        $device = Db::name('remote_device')
            ->where('device_id', $deviceId)
            ->field('sysinfo_updated_at')
            ->find();
        return $device ? (string)$device['sysinfo_updated_at'] : '';
    }

    protected function saveDevice(array $payload, $withSysinfo)
    {
        $deviceId = trim((string)($payload['id'] ?? ''));
        if ($deviceId === '' || strlen($deviceId) > 64) {
            throw new Exception('Invalid remote device ID');
        }

        $now = time();
        $connections = isset($payload['conns']) && is_array($payload['conns'])
            ? count($payload['conns'])
            : 0;
        $data = [
            'device_uuid'  => substr((string)($payload['uuid'] ?? ''), 0, 128),
            'client_version' => substr((string)($payload['version'] ?? $payload['ver'] ?? ''), 0, 64),
            'hostname'     => substr((string)($payload['hostname'] ?? ''), 0, 255),
            'username'     => substr((string)($payload['username'] ?? ''), 0, 255),
            'connection_count' => $connections,
            'last_seen_at' => $now,
            'updated_at'   => $now,
        ];
        if ($withSysinfo) {
            $data['sysinfo'] = json_encode($payload, JSON_UNESCAPED_UNICODE);
            $data['sysinfo_updated_at'] = $now;
        }

        $device = Db::name('remote_device')->where('device_id', $deviceId)->find();
        if ($device) {
            Db::name('remote_device')->where('id', $device['id'])->update($data);
            return $device['id'];
        }

        $data['device_id'] = $deviceId;
        $data['created_at'] = $now;
        return Db::name('remote_device')->insertGetId($data);
    }
}
