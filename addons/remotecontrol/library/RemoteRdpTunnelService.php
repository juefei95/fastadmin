<?php

namespace addons\remotecontrol\library;

use think\Db;
use think\Exception;

class RemoteRdpTunnelService
{
    const PORT_MIN = 7011;
    const PORT_MAX = 7999;

    protected $memberService;
    protected $config;

    public function __construct(RemoteMemberService $memberService = null, array $config = null)
    {
        $this->memberService = $memberService ?: new RemoteMemberService();
        $this->config = $config === null ? get_addon_config('remotecontrol') : $config;
    }

    public function getPortRange()
    {
        $min = (int)$this->getConfig('rdp_port_min', self::PORT_MIN);
        $max = (int)$this->getConfig('rdp_port_max', self::PORT_MAX);
        if ($min < 1 || $max > 65535 || $min > $max) {
            throw new Exception('Windows 远程桌面端口池范围配置无效');
        }
        return [$min, $max];
    }

    public function getTunnel($userId, $deviceId)
    {
        $userId = $this->normalizeUserId($userId);
        $deviceId = $this->normalizeDeviceId($deviceId);
        $membershipValid = $this->memberService->canControl($userId);
        if (!$membershipValid) {
            $this->markInvalidTunnelsForUser($userId);
        }
        $tunnel = Db::name('remote_rdp_tunnel')->where('user_id', $userId)->where('device_id', $deviceId)->find();
        return $this->buildPayload($tunnel, $membershipValid);
    }

    public function activate($userId, $deviceId)
    {
        $userId = $this->normalizeUserId($userId);
        $deviceId = $this->normalizeDeviceId($deviceId);
        if (!$this->memberService->canControl($userId)) {
            throw new Exception('会员权益无效，无法开启 Windows 远程桌面映射');
        }

        $tunnel = $this->reserveTunnel($userId, $deviceId);
        try {
            (new RemotePortManagerService($this->config))->setTcpPortEnabled((int)$tunnel['public_port'], true);
        } catch (\Exception $e) {
            Db::name('remote_rdp_tunnel')->where('id', $tunnel['id'])->update([
                'last_error' => substr('开放公网访问失败：' . $e->getMessage(), 0, 255), 'updated_at' => time(),
            ]);
            throw new Exception('远程桌面端口开放失败：' . $e->getMessage());
        }
        $now = time();
        Db::name('remote_rdp_tunnel')->where('id', $tunnel['id'])->update([
            'status' => 'allocated', 'last_error' => '', 'updated_at' => $now, 'activated_at' => $now,
        ]);
        return $this->getTunnel($userId, $deviceId);
    }

    public function deactivate($userId, $deviceId, $reason = 'client_stopped')
    {
        $userId = $this->normalizeUserId($userId);
        $deviceId = $this->normalizeDeviceId($deviceId);
        $tunnel = Db::name('remote_rdp_tunnel')->where('user_id', $userId)->where('device_id', $deviceId)->find();
        if (!$tunnel || empty($tunnel['public_port'])) {
            return $this->buildPayload($tunnel);
        }
        if ($this->markStopping($tunnel, $reason) !== '') {
            return $this->getTunnel($userId, $deviceId);
        }

        $actual = null;
        try {
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $actualByPort = (new RemoteFrpsMonitorService($this->config))->getTcpProxiesByPort();
                $actual = $actualByPort[(int)$tunnel['public_port']] ?? null;
                if (!$this->isProxyOnline($actual) || $attempt === 4) {
                    break;
                }
                usleep(500000);
            }
        } catch (\Exception $e) {
            $this->markStopping($tunnel, '等待 FRPS 确认客户端 FRPC 已断开：' . $e->getMessage());
            return $this->getTunnel($userId, $deviceId);
        }
        if ($this->isProxyOnline($actual)) {
            \think\Log::warning('[RDP-FRPC] stop pending port=' . (int)$tunnel['public_port']
                . ' expected_proxy=' . (string)$tunnel['proxy_name']
                . ' actual_proxy=' . (string)($actual['name'] ?? '')
                . ' actual_status=' . (string)($actual['status'] ?? '')
                . ' attempts=' . ($attempt + 1));
            $message = (string)($actual['name'] ?? '') === (string)$tunnel['proxy_name']
                ? '等待客户端 FRPC 断开'
                : '端口仍被其他 FRPS 代理占用';
            $this->markStopping($tunnel, $message);
            return $this->getTunnel($userId, $deviceId);
        }

        $this->releaseTunnel($tunnel);
        \think\Log::info('[RDP-FRPC] stop confirmed port=' . (int)$tunnel['public_port']
            . ' attempts=' . ($attempt + 1));
        return $this->getTunnel($userId, $deviceId);
    }

    public function reconcileStoppingTunnels($limit = 100)
    {
        $actualByPort = (new RemoteFrpsMonitorService($this->config))->getTcpProxiesByPort();
        $portManager = new RemotePortManagerService($this->config);
        $enabledPorts = $portManager->getEnabledTcpPorts();
        $limit = max(1, (int)$limit);
        $released = 0;
        $accessFailed = 0;
        $lastId = 0;
        while ($released < $limit) {
            $tunnels = Db::name('remote_rdp_tunnel')->where('id', '>', $lastId)->where('public_port', 'not null')
                ->where('status', 'stopping')->order('id asc')->limit(100)->select();
            if (!$tunnels) {
                break;
            }
            foreach ($tunnels as $tunnel) {
                $lastId = (int)$tunnel['id'];
                $port = (int)$tunnel['public_port'];
                if (isset($enabledPorts[$port])) {
                    try {
                        $portManager->setTcpPortEnabled($port, false);
                        unset($enabledPorts[$port]);
                    } catch (\Exception $e) {
                        $this->updateLastError($tunnel, '关闭公网访问失败：' . $e->getMessage());
                        $accessFailed++;
                        continue;
                    }
                }
                $actual = $actualByPort[$port] ?? null;
                if (!$this->isProxyOnline($actual)) {
                    $this->releaseTunnel($tunnel);
                    $released++;
                    if ($released >= $limit) {
                        break 2;
                    }
                }
            }
            if (count($tunnels) < 100) {
                break;
            }
        }
        return ['released' => $released, 'access_failed' => $accessFailed];
    }

    protected function releaseTunnel(array $tunnel)
    {
        $now = time();
        Db::name('remote_rdp_tunnel')->where('id', $tunnel['id'])->update([
            'public_port' => null, 'status' => 'inactive', 'last_error' => '', 'updated_at' => $now, 'deactivated_at' => $now,
        ]);
    }

    public function confirmStarted($userId, $deviceId)
    {
        $userId = $this->normalizeUserId($userId);
        $deviceId = $this->normalizeDeviceId($deviceId);
        if (!$this->memberService->canControl($userId)) {
            $this->markInvalidTunnelsForUser($userId);
            return $this->getTunnel($userId, $deviceId);
        }
        $tunnel = Db::name('remote_rdp_tunnel')->where('user_id', $userId)->where('device_id', $deviceId)->find();
        if (!$tunnel || empty($tunnel['public_port'])) {
            throw new Exception('请先申请 Windows 远程桌面端口');
        }
        Db::name('remote_rdp_tunnel')->where('id', $tunnel['id'])->update([
            'status' => 'active', 'last_error' => '', 'updated_at' => time(),
        ]);
        return $this->getTunnel($userId, $deviceId);
    }

    public function getFrpcConfig($userId, $deviceId)
    {
        $userId = $this->normalizeUserId($userId);
        $deviceId = $this->normalizeDeviceId($deviceId);
        if (!$this->memberService->canControl($userId)) {
            $this->markInvalidTunnelsForUser($userId);
            throw new Exception('会员权益无效，无法获取 Windows 远程桌面穿透参数');
        }
        $tunnel = Db::name('remote_rdp_tunnel')->where('user_id', $userId)->where('device_id', $deviceId)->find();
        if (!$tunnel || empty($tunnel['public_port']) || !in_array($tunnel['status'], ['pending', 'allocated', 'active'], true)) {
            throw new Exception('请先申请 Windows 远程桌面端口');
        }
        $serverAddr = trim((string)$this->getConfig('rdp_public_host', ''));
        $serverPort = (int)$this->getConfig('frpc_server_port', 0);
        $token = trim((string)$this->getConfig('frpc_token', ''));
        $protocol = strtolower(trim((string)$this->getConfig('frpc_protocol', 'quic')));
        if ($serverAddr === '' || $serverPort < 1 || $serverPort > 65535 || $token === '') {
            throw new Exception('FRPC 服务参数尚未配置完整');
        }
        if (!in_array($protocol, ['quic', 'tcp'], true)) {
            throw new Exception('FRPC 协议配置无效');
        }
        return [
            'server_addr' => $serverAddr, 'server_port' => $serverPort,
            'token' => $token, 'protocol' => $protocol,
            'proxy_name' => (string)$tunnel['proxy_name'],
            'local_ip' => '127.0.0.1', 'local_port' => 3389,
            'remote_port' => (int)$tunnel['public_port'],
        ];
    }

    public function requestStop($userId, $deviceId, $reason = 'admin')
    {
        $userId = $this->normalizeUserId($userId);
        $deviceId = $this->normalizeDeviceId($deviceId);
        $tunnel = Db::name('remote_rdp_tunnel')->where('user_id', $userId)->where('device_id', $deviceId)->find();
        if ($tunnel && !empty($tunnel['public_port'])) {
            $accessError = $this->markStopping($tunnel, $reason);
            $tunnel = Db::name('remote_rdp_tunnel')->where('id', $tunnel['id'])->find();
            if ($accessError !== '') {
                throw new Exception($accessError);
            }
        }
        return $this->buildPayload($tunnel, false);
    }

    public function markInvalidTunnels($limit = 100)
    {
        $limit = max(1, (int)$limit);
        $result = ['marked' => 0];
        $lastId = 0;
        $batchSize = max(100, $limit);
        while ($result['marked'] < $limit) {
            $tunnels = Db::name('remote_rdp_tunnel')->where('id', '>', $lastId)->where('public_port', 'not null')
                ->where('status', 'in', ['pending', 'allocated', 'active'])->order('id asc')->limit($batchSize)->select();
            if (!$tunnels) {
                break;
            }
            foreach ($tunnels as $tunnel) {
                $lastId = (int)$tunnel['id'];
                if ($this->memberService->canControl($tunnel['user_id'])) {
                    continue;
                }
                $this->markStopping($tunnel, 'membership_invalid');
                $result['marked']++;
                if ($result['marked'] >= $limit) {
                    break 2;
                }
            }
            if (count($tunnels) < $batchSize) {
                break;
            }
        }
        return $result;
    }

    public function markInvalidTunnelsForUser($userId)
    {
        $userId = $this->normalizeUserId($userId);
        if ($this->memberService->canControl($userId)) {
            return ['marked' => 0];
        }
        $tunnels = Db::name('remote_rdp_tunnel')->where('user_id', $userId)->where('public_port', 'not null')
            ->where('status', 'in', ['pending', 'allocated', 'active'])->select();
        $result = ['marked' => 0];
        foreach ($tunnels as $tunnel) {
            $this->markStopping($tunnel, 'membership_invalid');
            $result['marked']++;
        }
        return $result;
    }

    protected function reserveTunnel($userId, $deviceId)
    {
        $existing = Db::name('remote_rdp_tunnel')->where('user_id', $userId)->where('device_id', $deviceId)->find();
        $actualByPort = [];
        if (!$existing || empty($existing['public_port'])) {
            $actualByPort = (new RemoteFrpsMonitorService($this->config))->getTcpProxiesByPort();
        }

        Db::startTrans();
        try {
            $tunnel = Db::name('remote_rdp_tunnel')->where('user_id', $userId)->where('device_id', $deviceId)->lock(true)->find();
            if ($tunnel && !empty($tunnel['public_port'])) {
                if (empty($tunnel['proxy_name'])) {
                    $tunnel['proxy_name'] = $this->buildProxyName($userId, $deviceId);
                    Db::name('remote_rdp_tunnel')->where('id', $tunnel['id'])->update([
                        'proxy_name' => $tunnel['proxy_name'], 'updated_at' => time(),
                    ]);
                }
                Db::commit();
                return $tunnel;
            }
            $now = time();
            $data = ['public_port' => $this->findAvailablePort($actualByPort), 'proxy_name' => $this->buildProxyName($userId, $deviceId), 'status' => 'pending', 'last_error' => '', 'updated_at' => $now];
            if ($tunnel) {
                Db::name('remote_rdp_tunnel')->where('id', $tunnel['id'])->update($data);
            } else {
                $data += ['user_id' => $userId, 'device_id' => $deviceId, 'created_at' => $now];
                $tunnel = ['id' => Db::name('remote_rdp_tunnel')->insertGetId($data)];
            }
            Db::commit();
            return Db::name('remote_rdp_tunnel')->where('id', $tunnel['id'])->find();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    protected function findAvailablePort(array $actualByPort)
    {
        list($min, $max) = $this->getPortRange();
        $used = Db::name('remote_rdp_tunnel')->where('public_port', 'between', [$min, $max])->lock(true)->column('public_port');
        $used = array_flip(array_map('intval', $used));
        foreach ($actualByPort as $port => $proxy) {
            if ($this->isProxyOnline($proxy)) {
                $used[(int)$port] = true;
            }
        }
        for ($port = $min; $port <= $max; $port++) {
            if (!isset($used[$port])) {
                return $port;
            }
        }
        throw new Exception('Windows 远程桌面端口池已用尽');
    }

    protected function isProxyOnline($proxy)
    {
        return is_array($proxy) && strtolower(trim((string)($proxy['status'] ?? ''))) === 'online';
    }

    protected function markStopping(array $tunnel, $reason)
    {
        $accessError = '';
        try {
            (new RemotePortManagerService($this->config))->setTcpPortEnabled((int)$tunnel['public_port'], false);
        } catch (\Exception $e) {
            $accessError = '关闭公网访问失败：' . $e->getMessage();
            $reason = trim((string)$reason) . '；' . $accessError;
        }
        Db::name('remote_rdp_tunnel')->where('id', $tunnel['id'])->update([
            'status' => 'stopping', 'last_error' => substr((string)$reason, 0, 255), 'updated_at' => time(),
        ]);
        return $accessError;
    }

    protected function updateLastError(array $tunnel, $message)
    {
        Db::name('remote_rdp_tunnel')->where('id', $tunnel['id'])->update([
            'last_error' => substr((string)$message, 0, 255), 'updated_at' => time(),
        ]);
    }

    protected function buildPayload($tunnel, $membershipValid = null)
    {
        if ($membershipValid === null && $tunnel) {
            $membershipValid = $this->memberService->canControl($tunnel['user_id']);
        }
        $desiredEnabled = (bool)$membershipValid && $tunnel && !empty($tunnel['public_port'])
            && in_array($tunnel['status'], ['pending', 'allocated', 'active'], true);
        return [
            'enabled' => $desiredEnabled, 'desired_enabled' => $desiredEnabled,
            'must_stop' => $tunnel && !empty($tunnel['public_port']) && !$desiredEnabled,
            'membership_valid' => (bool)$membershipValid,
            'status' => $tunnel ? (string)$tunnel['status'] : 'inactive',
            'public_host' => trim((string)$this->getConfig('rdp_public_host', '')),
            'public_port' => $tunnel && !empty($tunnel['public_port']) ? (int)$tunnel['public_port'] : null, 'local_port' => 3389,
            'proxy_name' => $tunnel ? (string)($tunnel['proxy_name'] ?? '') : '',
            'last_error' => $tunnel ? (string)$tunnel['last_error'] : '',
        ];
    }

    protected function normalizeUserId($userId)
    {
        $userId = (int)$userId;
        if ($userId <= 0) {
            throw new Exception('User ID must be greater than 0');
        }
        return $userId;
    }

    protected function buildProxyName($userId, $deviceId)
    {
        return 'rustdesk-rdp-u' . (int)$userId . '-d' . substr(sha1($deviceId), 0, 12);
    }

    protected function normalizeDeviceId($deviceId)
    {
        $deviceId = trim((string)$deviceId);
        if ($deviceId === '' || strlen($deviceId) > 64 || !preg_match('/^[A-Za-z0-9._-]+$/', $deviceId)) {
            throw new Exception('远程设备 ID 无效');
        }
        return $deviceId;
    }

    protected function getConfig($name, $default = null)
    {
        return array_key_exists($name, $this->config) ? $this->config[$name] : $default;
    }
}
