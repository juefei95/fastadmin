<?php

namespace addons\wechatqrcodelogin\library;

use GuzzleHttp\Client;

class LegacyUpdateService
{
    const RELEASE_URL = 'https://lddownload.zhenhaiedu.top/code2yellow/stable.json';
    const CACHE_KEY = 'wechatqrcodelogin_legacy_download_url';

    public function downloadUrl()
    {
        $url = cache(self::CACHE_KEY);
        if (is_string($url) && $this->isValidUrl($url)) {
            return $url;
        }
        $client = new Client(['timeout' => 8, 'connect_timeout' => 5, 'allow_redirects' => false]);
        $response = $client->request('GET', self::RELEASE_URL, ['stream' => true]);
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('无法获取最新版本信息');
        }
        $body = $response->getBody();
        $json = '';
        while (!$body->eof() && strlen($json) <= 65536) {
            $json .= $body->read(8192);
        }
        if (strlen($json) > 65536) {
            throw new \RuntimeException('版本信息过大');
        }
        $release = json_decode($json, true);
        $url = $release['windows']['url'] ?? null;
        if (!is_string($url) || !$this->isValidUrl($url)) {
            throw new \RuntimeException('最新版本下载地址无效');
        }
        cache(self::CACHE_KEY, $url, 300);
        return $url;
    }

    private function isValidUrl($url)
    {
        return strlen($url) <= 2048 && filter_var($url, FILTER_VALIDATE_URL)
            && strtolower((string)parse_url($url, PHP_URL_SCHEME)) === 'https';
    }
}
