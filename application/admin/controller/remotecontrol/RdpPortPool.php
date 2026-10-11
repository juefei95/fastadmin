<?php

namespace app\admin\controller\remotecontrol;

use addons\remotecontrol\library\RemoteFrpsMonitorService;
use addons\remotecontrol\library\RemotePortManagerService;
use addons\remotecontrol\library\RemoteRdpTunnelService;
use app\common\controller\Backend;
use think\Db;

class RdpPortPool extends Backend
{
    public function index()
    {
        if (!$this->request->isAjax()) {
            return $this->view->fetch();
        }

        $tunnelService = new RemoteRdpTunnelService();
        list($min, $max) = $tunnelService->getPortRange();
        $mappings = Db::name('remote_rdp_tunnel')
            ->where('public_port', 'between', [$min, $max])
            ->select();
        $mappingByPort = [];
        foreach ($mappings as $mapping) {
            $mappingByPort[(int)$mapping['public_port']] = $mapping;
        }
        $monitorError = '';
        try {
            $actualByPort = (new RemoteFrpsMonitorService())->getTcpProxiesByPort();
        } catch (\Exception $e) {
            $actualByPort = [];
            $monitorError = $e->getMessage();
        }
        $accessError = '';
        try {
            $enabledTcpPorts = (new RemotePortManagerService())->getEnabledTcpPorts();
        } catch (\Exception $e) {
            $enabledTcpPorts = [];
            $accessError = $e->getMessage();
        }

        $rows = [];
        for ($port = $min; $port <= $max; $port++) {
            $mapping = $mappingByPort[$port] ?? null;
            $actual = $actualByPort[$port] ?? null;
            $actualOnline = $actual && strtolower(trim((string)$actual['status'])) === 'online';
            $expectedName = (string)($mapping['proxy_name'] ?? '');
            if ($monitorError !== '') {
                $poolStatus = $mapping ? 'reserved_unverified' : 'unknown';
            } elseif ($mapping && $actualOnline && $expectedName !== '' && $actual['name'] === $expectedName) {
                $poolStatus = 'rustdesk_online';
            } elseif ($mapping && $actualOnline) {
                $poolStatus = 'conflict';
            } elseif ($mapping) {
                $poolStatus = 'reserved_offline';
            } elseif ($actualOnline) {
                $poolStatus = 'external_occupied';
            } else {
                $poolStatus = 'free';
            }
            $rows[] = [
                'port' => $port,
                'allocation_status' => $mapping ? 'allocated' : 'unallocated',
                'public_access_status' => $accessError === '' ? (isset($enabledTcpPorts[$port]) ? 'open' : 'closed') : 'unknown',
                'pool_status' => $poolStatus,
                'user_id' => $mapping ? (int)$mapping['user_id'] : null,
                'device_id' => $mapping ? (string)$mapping['device_id'] : '',
                'mapping_status' => $mapping ? (string)$mapping['status'] : '',
                'expected_proxy_name' => $expectedName,
                'actual_proxy_name' => $actual ? (string)$actual['name'] : '',
                'actual_status' => $actual ? (string)$actual['status'] : '',
                'monitor_error' => $monitorError,
                'public_access_error' => $accessError,
            ];
        }

        $search = trim((string)$this->request->get('search', ''));
        if ($search !== '') {
            $rows = array_values(array_filter($rows, function ($row) use ($search) {
                return strpos(implode(' ', $row), $search) !== false;
            }));
        }
        $total = count($rows);
        $offset = max(0, (int)$this->request->get('offset', 0));
        $limit = max(1, (int)$this->request->get('limit', 20));
        return json(['total' => $total, 'rows' => array_slice($rows, $offset, $limit)]);
    }

    public function openaccess($ids = null)
    {
        $this->setAccess($ids, true);
    }

    public function closeaccess($ids = null)
    {
        $this->setAccess($ids, false);
    }

    protected function setAccess($ids, $enabled)
    {
        $ids = $ids ?: $this->request->param('ids');
        $ports = array_filter(explode(',', (string)$ids), 'strlen');
        if (!$ports) {
            $this->error('请选择端口');
        }
        $ports = array_values(array_unique($ports));
        list($min, $max) = (new RemoteRdpTunnelService())->getPortRange();
        foreach ($ports as $port) {
            if (!ctype_digit($port) || (int)$port < $min || (int)$port > $max) {
                $this->error('端口必须在 ' . $min . '-' . $max . ' 范围内');
            }
        }
        $service = new RemotePortManagerService();
        foreach ($ports as $port) {
            try {
                $service->setTcpPortEnabled((int)$port, $enabled);
            } catch (\Exception $e) {
                $this->error('端口 ' . $port . ($enabled ? ' 开放' : ' 关闭') . '失败：' . $e->getMessage());
            }
        }
        $this->success('已' . ($enabled ? '开放' : '关闭') . ' ' . count($ports) . ' 个端口');
    }
}
