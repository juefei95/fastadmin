<?php

namespace addons\remotecontrol\library;

use think\Exception;

class RemoteFrpsMonitorService
{
    protected $config;

    public function __construct(array $config = null)
    {
        $this->config = $config === null ? get_addon_config('remotecontrol') : $config;
    }

    public function getTcpProxiesByPort()
    {
        $url = rtrim(trim((string)($this->config['frps_dashboard_url'] ?? '')), '/');
        if ($url === '') {
            throw new Exception('请先配置 FRPS Dashboard 地址');
        }
        if (!filter_var($url, FILTER_VALIDATE_URL) || !function_exists('curl_init')) {
            throw new Exception('FRPS Dashboard 配置无效或服务器未启用 cURL');
        }

        $ch = curl_init($url . '/api/proxy/tcp');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        $user = (string)($this->config['frps_dashboard_user'] ?? '');
        $password = (string)($this->config['frps_dashboard_password'] ?? '');
        if ($user !== '' || $password !== '') {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $user . ':' . $password);
        }
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($body === false || $status < 200 || $status >= 300) {
            throw new Exception('无法读取 FRPS TCP 代理列表' . ($error ? '：' . $error : ''));
        }

        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['proxies']) || !is_array($data['proxies'])) {
            throw new Exception('FRPS TCP 代理列表响应格式无效');
        }
        $result = [];
        foreach ($data['proxies'] as $proxy) {
            $conf = isset($proxy['conf']) && is_array($proxy['conf']) ? $proxy['conf'] : [];
            $port = (int)($conf['remotePort'] ?? $conf['remote_port'] ?? $proxy['remotePort'] ?? $proxy['remote_port'] ?? 0);
            if ($port < RemoteRdpTunnelService::PORT_MIN || $port > RemoteRdpTunnelService::PORT_MAX) {
                continue;
            }
            $result[$port] = [
                'name' => (string)($proxy['name'] ?? $conf['name'] ?? ''),
                'status' => (string)($proxy['status'] ?? 'online'),
            ];
        }
        return $result;
    }
}
