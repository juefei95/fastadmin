<?php

namespace addons\remotecontrol\library;

use think\Exception;

class RemotePortManagerService
{
    protected $config;

    public function __construct(array $config = null)
    {
        $this->config = $config === null ? get_addon_config('remotecontrol') : $config;
    }

    public function health()
    {
        $data = $this->request('GET', '/health');
        if (($data['status'] ?? '') !== 'ok') {
            throw new Exception('端口管理服务健康检查失败');
        }
        return true;
    }

    public function getEnabledTcpPorts()
    {
        $data = $this->request('GET', '/api/ports');
        if (!isset($data['ports']) || !is_array($data['ports'])) {
            throw new Exception('端口管理服务返回的端口列表格式无效');
        }
        $ports = [];
        foreach ($data['ports'] as $item) {
            if (!is_array($item) || strtolower((string)($item['protocol'] ?? '')) !== 'tcp') {
                continue;
            }
            $port = (int)($item['port'] ?? 0);
            if ($port >= 1 && $port <= 65535) {
                $ports[$port] = true;
            }
        }
        return $ports;
    }

    public function setTcpPortEnabled($port, $enabled)
    {
        $port = (int)$port;
        if ($port < 1 || $port > 65535) {
            throw new Exception('端口号无效');
        }
        $data = $this->request('POST', $enabled ? '/api/ports/enable' : '/api/ports/disable', [
            'port' => $port,
            'protocol' => 'tcp',
        ]);
        if (!array_key_exists('enabled', $data)
            || (bool)$data['enabled'] !== (bool)$enabled
            || (int)($data['port'] ?? 0) !== $port
            || strtolower((string)($data['protocol'] ?? '')) !== 'tcp') {
            throw new Exception('端口管理服务未确认操作结果');
        }
        return true;
    }

    protected function request($method, $path, array $body = null)
    {
        $baseUrl = rtrim(trim((string)($this->config['port_manager_url'] ?? '')), '/');
        if ($baseUrl === '' || !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new Exception('请先正确配置端口管理接口');
        }
        $scheme = strtolower((string)parse_url($baseUrl, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new Exception('端口管理接口仅支持 HTTP 或 HTTPS');
        }
        if (!function_exists('curl_init')) {
            throw new Exception('服务器未安装 cURL 扩展');
        }

        $handle = curl_init($baseUrl . $path);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ];
        if ($method === 'POST') {
            $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
            if ($payload === false) {
                throw new Exception('端口管理请求参数编码失败');
            }
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $payload;
            $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        }
        curl_setopt_array($handle, $options);
        $response = curl_exec($handle);
        $curlError = curl_error($handle);
        $statusCode = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ($response === false) {
            throw new Exception('无法连接端口管理服务：' . $curlError);
        }
        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new Exception('端口管理服务返回了无效 JSON');
        }
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new Exception((string)($data['error'] ?? ('端口管理服务返回 HTTP ' . $statusCode)));
        }
        return $data;
    }
}
