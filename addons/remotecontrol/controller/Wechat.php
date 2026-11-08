<?php

namespace addons\remotecontrol\controller;

use addons\remotecontrol\library\RemoteLoginDeviceService;
use addons\wechatqrcodelogin\library\WechatLoginService;
use app\common\controller\Api;
use app\common\library\Token;

class Wechat extends Api
{
    protected $noNeedLogin = ['create', 'poll', 'notify'];
    protected $noNeedRight = '*';
    const SCENE_PREFIX = 'remotecontrol_login_scene_';

    public function create()
    {
        $deviceId = trim((string)$this->request->request('device_id', ''));
        $deviceUuid = trim((string)$this->request->request('device_uuid', ''));
        if ($deviceId === '' && $deviceUuid === '') {
            $this->error('无法识别当前登录设备');
        }
        try {
            $result = (new WechatLoginService($this->getWechatConfig()))->create($this->request->request('expire_seconds', 300));
            cache(self::SCENE_PREFIX . $result['scene'], ['device_id' => $deviceId, 'device_uuid' => $deviceUuid], (int)$result['expires_in'] + 600);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
        }
        $this->success('ok', $result);
    }

    public function poll()
    {
        $scene = trim((string)$this->request->request('scene', ''));
        $device = $scene ? cache(self::SCENE_PREFIX . $scene) : null;
        if (!$device) {
            $this->error('二维码已过期或不存在');
        }
        try {
            $result = (new WechatLoginService($this->getWechatConfig()))->poll($scene);
            if ($result['status'] === 'logged_in') {
                $user = $result['userinfo'];
                try {
                    (new RemoteLoginDeviceService())->registerLogin($user['id'], $device['device_id'], $device['device_uuid'], $user['token']);
                } catch (\Throwable $e) {
                    Token::delete($user['token']);
                    cache(self::SCENE_PREFIX . $scene, null);
                    throw $e;
                }
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
        }
        $this->success($result['status'] === 'logged_in' ? '登录成功' : '', $result);
    }

    public function notify()
    {
        try {
            return (new WechatLoginService($this->getWechatConfig()))->notify();
        } catch (\Throwable $e) {
            return 'fail';
        }
    }

    protected function getWechatConfig()
    {
        $addon = (array)get_addon_config('remotecontrol');
        $appid = trim((string)($addon['wechat_appid'] ?? ''));
        $secret = trim((string)($addon['wechat_secret'] ?? ''));
        $token = trim((string)($addon['wechat_token'] ?? ''));
        $aesKey = trim((string)($addon['wechat_aes_key'] ?? ''));
        if ($appid === '' && $secret === '' && $token === '' && $aesKey === '') {
            return null;
        }
        if ($appid === '' || $secret === '' || $token === '') {
            throw new \RuntimeException('请在远程控制插件设置中完整填写公众号 AppID、AppSecret 和 Token');
        }
        $http = ['verify' => (bool)($addon['wechat_http_verify'] ?? true), 'timeout' => 30.0];
        $proxy = trim((string)($addon['wechat_http_proxy'] ?? ''));
        if ($proxy !== '') {
            $http['proxy'] = $proxy;
        }
        return ['app_id' => $appid, 'secret' => $secret, 'token' => $token, 'aes_key' => $aesKey,
            'response_type' => 'array', 'http' => $http, 'log' => ['default' => 'error']];
    }
}
