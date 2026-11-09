<?php
namespace addons\code2yellow\library;

use GuzzleHttp\Client;

class GameQrService
{
    public function generate($appid, $secret, $path)
    {
        $config = (array)get_addon_config('code2yellow');
        $options = ['timeout' => 15, 'connect_timeout' => 5, 'allow_redirects' => false, 'verify' => true];
        $proxy = trim((string)($config['http_proxy'] ?? ''));
        if ($proxy !== '') $options['proxy'] = $proxy;
        $client = new Client($options);
        $key = 'code2yellow_game_token_' . hash('sha256', $appid . ':' . $secret);
        $token = cache($key);
        if (!is_string($token) || $token === '') {
            $response = $client->request('GET', 'https://api.weixin.qq.com/cgi-bin/token', ['query' => ['grant_type'=>'client_credential', 'appid'=>$appid, 'secret'=>$secret]]);
            $result = json_decode((string)$response->getBody(), true);
            if (empty($result['access_token'])) throw new \RuntimeException('获取小游戏 access_token 失败，请检查凭据和微信服务器配置');
            $token = (string)$result['access_token'];
            cache($key, $token, max(60, (int)($result['expires_in'] ?? 7200) - 300));
        }
        $response = $client->request('POST', 'https://api.weixin.qq.com/cgi-bin/wxaapp/createwxaqrcode', ['query'=>['access_token'=>$token], 'json'=>['path'=>$path, 'width'=>430], 'stream'=>true]);
        $stream = $response->getBody();
        $image = '';
        while (!$stream->eof() && strlen($image) <= 2097152) $image .= $stream->read(8192);
        if (strlen($image) > 2097152 || !function_exists('getimagesizefromstring')) throw new \RuntimeException('微信二维码响应无效');
        $info = @getimagesizefromstring($image);
        if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) throw new \RuntimeException('微信二维码生成失败，请检查小游戏是否已发布、参数和接口权限');
        $dir = ROOT_PATH . 'public' . DS . 'uploads' . DS . 'code2yellow-game';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) throw new \RuntimeException('二维码目录创建失败');
        $name = hash('sha256', $appid . "\n" . $path) . ($info[2] === IMAGETYPE_PNG ? '.png' : '.jpg');
        if (file_put_contents($dir . DS . $name, $image, LOCK_EX) === false) throw new \RuntimeException('二维码保存失败');
        return '/uploads/code2yellow-game/' . $name;
    }
}
