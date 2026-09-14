<?php

namespace addons\wechatqrcodelogin\controller;

use addons\third\library\Service as ThirdService;
use app\common\controller\Api;
use EasyWeChat\Factory;
use fast\Random;
use think\Config;

/**
 * 公众号扫码登录
 */
class Index extends Api
{
    protected $noNeedLogin = ['loginqrcode', 'qrcode', 'loginstatus', 'status', 'notify'];
    protected $noNeedRight = '*';

    const ADDON_NAME = 'wechatqrcodelogin';
    const PLATFORM = 'wechat';
    const APPTYPE = 'mp';
    const SCENE_PREFIX = 'wxlogin_';
    const CACHE_PREFIX = 'wechat_login_scene_';
    const DEFAULT_EXPIRE_SECONDS = 300;

    /**
     * 获取登录二维码
     */
    public function loginqrcode()
    {
        $this->ensureThirdLoginAvailable();
        try {
            $app = $this->getOfficialAccountApp();
            $scene = self::SCENE_PREFIX . Random::alnum(24);
            $expireSeconds = (int)$this->request->request('expire_seconds', self::DEFAULT_EXPIRE_SECONDS);
            $expireSeconds = max(60, min($expireSeconds, 1800));
            $result = $app->qrcode->temporary($scene, $expireSeconds);
        } catch (\Throwable $e) {
            $this->error('获取微信登录二维码失败：' . $e->getMessage());
        }

        if (!is_array($result)) {
            $result = (array)$result;
        }

        if (!empty($result['errcode'])) {
            $message = $result['errmsg'] ?? '未知错误';
            $this->error('微信接口返回错误：[' . $result['errcode'] . '] ' . $message, $result);
        }

        if (empty($result['ticket'])) {
            $this->error('微信未返回有效二维码 ticket', $result);
        }

        $now = time();
        $payload = [
            'scene'        => $scene,
            'status'       => 'pending',
            'created_at'   => $now,
            'expire_at'    => $now + (int)($result['expire_seconds'] ?? $expireSeconds),
            'ticket'       => (string)$result['ticket'],
        ];
        cache($this->getSceneCacheKey($scene), $payload, $expireSeconds + 600);

        $this->success('ok', [
            'scene'      => $scene,
            'ticket'     => (string)$result['ticket'],
            'qr_url'     => $app->qrcode->url($result['ticket']),
            'expires_in' => (int)($result['expire_seconds'] ?? $expireSeconds),
            'status'     => 'pending',
        ]);
    }

    /**
     * 轮询登录状态
     */
    public function loginstatus()
    {
        $scene = trim((string)$this->request->request('scene', ''));
        if (!$scene) {
            $this->error('scene 不能为空');
        }

        $cacheKey = $this->getSceneCacheKey($scene);
        $payload = cache($cacheKey);
        if (!$payload) {
            $this->error('二维码已过期或不存在');
        }

        if ($payload['status'] === 'pending') {
            $this->success('', [
                'scene'  => $scene,
                'status' => 'pending',
            ]);
        }

        if ($payload['status'] === 'failed') {
            $this->error($payload['message'] ?? '微信登录失败');
        }

        if ($payload['status'] === 'logged_in' && !empty($payload['login_result'])) {
            $this->success('登录成功', $payload['login_result']);
        }

        if ($payload['status'] !== 'authorized' || empty($payload['third_userinfo'])) {
            $this->error('登录状态异常，请重新扫码');
        }

        $this->ensureThirdLoginAvailable();
        $ret = ThirdService::connect(self::PLATFORM, $payload['third_userinfo']);
        if (!$ret) {
            $this->error($this->auth->getError() ?: '微信登录失败');
        }

        $loginResult = [
            'scene'    => $scene,
            'status'   => 'logged_in',
            'userinfo' => $this->auth->getUserinfo(),
        ];

        $payload['status'] = 'logged_in';
        $payload['login_result'] = $loginResult;
        cache($cacheKey, $payload, $this->getSceneCacheTtl($payload));

        $this->success('登录成功', $loginResult);
    }

    /**
     * 微信服务器回调
     */
    public function notify()
    {
        try {
            $app = $this->getOfficialAccountApp();
            $server = $app->server;
            $server->push(function ($message) {
                $this->handleWechatEvent((array)$message);
                return 'success';
            });
            $response = $server->forceValidate()->serve();
            return $response->getContent();
        } catch (\Throwable $e) {
            return 'fail';
        }
    }

    public function qrcode()
    {
        return $this->loginqrcode();
    }

    public function status()
    {
        return $this->loginstatus();
    }

    /**
     * 处理扫码 / 关注事件
     */
    protected function handleWechatEvent(array $message)
    {
        $msgType = strtolower((string)($message['MsgType'] ?? $message['msg_type'] ?? ''));
        if ($msgType !== 'event') {
            return;
        }

        $event = strtolower((string)($message['Event'] ?? $message['event'] ?? ''));
        if (!in_array($event, ['subscribe', 'scan'], true)) {
            return;
        }

        $openid = trim((string)($message['FromUserName'] ?? $message['from_user_name'] ?? ''));
        $eventKey = trim((string)($message['EventKey'] ?? $message['event_key'] ?? ''));
        if ($event === 'subscribe' && strpos($eventKey, 'qrscene_') === 0) {
            $eventKey = substr($eventKey, 8);
        }

        if (!$openid || !$eventKey || strpos($eventKey, self::SCENE_PREFIX) !== 0) {
            return;
        }

        $cacheKey = $this->getSceneCacheKey($eventKey);
        $payload = cache($cacheKey);
        if (!$payload) {
            return;
        }

        try {
            $thirdUserinfo = $this->getThirdWechatUserInfo($openid);
            $payload['status'] = 'authorized';
            $payload['openid'] = $openid;
            $payload['third_userinfo'] = $thirdUserinfo;
            $payload['authorized_at'] = time();
            if (empty($payload['welcome_sent_at'])) {
                $this->sendLoginSuccessMessages($openid);
                $payload['welcome_sent_at'] = time();
            }
        } catch (\Throwable $e) {
            $payload['status'] = 'failed';
            $payload['message'] = $e->getMessage();
        }

        cache($cacheKey, $payload, $this->getSceneCacheTtl($payload));
    }

    /**
     * 拉取并整理 third 插件可识别的公众号用户信息
     */
    protected function getThirdWechatUserInfo($openid)
    {
        $userInfo = $this->fetchWechatUserInfo($openid);
        if (empty($userInfo['openid'])) {
            throw new \RuntimeException('未获取到公众号用户标识');
        }
        if (isset($userInfo['subscribe']) && (int)$userInfo['subscribe'] !== 1) {
            throw new \RuntimeException('用户尚未关注公众号');
        }

        $nickname = $this->normalizeWechatNickname($userInfo);
        $avatar = (string)($userInfo['headimgurl'] ?? '');

        return [
            'platform'      => self::PLATFORM,
            'apptype'       => self::APPTYPE,
            'openid'        => (string)$userInfo['openid'],
            'unionid'       => (string)($userInfo['unionid'] ?? ''),
            'nickname'      => $nickname,
            'avatar'        => $avatar,
            'openname'      => $nickname,
            'access_token'  => '',
            'refresh_token' => '',
            'expires_in'    => 0,
            'userinfo'      => [
                'nickname' => $nickname,
                'avatar'   => $avatar,
            ],
            'raw_userinfo'  => $userInfo,
        ];
    }

    /**
     * 拉取公众号粉丝信息
     */
    protected function fetchWechatUserInfo($openid)
    {
        $app = $this->getOfficialAccountApp();
        $info = $app->user->get($openid);
        return is_array($info) ? $info : (array)$info;
    }

    /**
     * 获取公众号应用实例
     */
    protected function getOfficialAccountApp()
    {
        return Factory::officialAccount($this->getWechatConfig());
    }

    protected function sendLoginSuccessMessages($openid)
    {
        $messages = [
            "-----------------------------\n您好，您已使用微信成功登录！\n-----------------------------",
            "欢迎使用打卡小工具\n#小程序://卡勿/hmTRiBKERz2WfKu",
            "配套工具联系-周振海\n电话微信:17755706292"
        ];

        try {
            $app = $this->getOfficialAccountApp();
            foreach ($messages as $message) {
                $app->customer_service->message($message)->to($openid)->send();
            }
        } catch (\Throwable $e) {
            // 客服消息发送失败不影响扫码登录主流程
        }
    }

    /**
     * 获取公众号配置
     */
    protected function getWechatConfig()
    {
        $addon = (array)get_addon_config(self::ADDON_NAME);
        $site = (array)Config::get('site');

        $config = [
            'app_id' => $addon['appid'] ?? $addon['app_id'] ?? $site['wechat_official_appid'] ?? $site['wechat_mp_appid'] ?? '',
            'secret' => $addon['secret'] ?? $addon['app_secret'] ?? $site['wechat_official_secret'] ?? $site['wechat_mp_secret'] ?? '',
            'token' => $addon['token'] ?? $site['wechat_official_token'] ?? $site['wechat_mp_token'] ?? '',
            'aes_key' => $addon['aes_key'] ?? $addon['encoding_aes_key'] ?? $site['wechat_official_aes_key'] ?? $site['wechat_mp_aes_key'] ?? '',
            'response_type' => 'array',
            'http' => [
                'verify' => isset($addon['http_verify']) ? (bool)$addon['http_verify'] : (isset($site['wechat_official_http_verify']) ? (bool)$site['wechat_official_http_verify'] : false),
                'timeout' => 30.0,
            ],
            'log' => [
                'default' => 'error',
            ],
        ];

        if (!$config['app_id'] || !$config['secret'] || !$config['token']) {
            $this->error('请先在微信扫码登录插件配置中填写公众号 appid、secret、token');
        }

        return $config;
    }
    protected function getSceneCacheKey($scene)
    {
        return self::CACHE_PREFIX . $scene;
    }

    protected function getSceneCacheTtl(array $payload)
    {
        $expireAt = (int)($payload['expire_at'] ?? (time() + self::DEFAULT_EXPIRE_SECONDS));
        return max(60, $expireAt - time() + 600);
    }

    protected function ensureThirdLoginAvailable()
    {
        $third = get_addon_info('third');
        if (!$third || empty($third['state'])) {
            $this->error('请在后台插件管理安装第三方登录插件并启用');
        }
    }

    protected function normalizeWechatNickname(array $userInfo)
    {
        $fallback = '微信用户';
        $nickname = trim((string)($userInfo['nickname'] ?? $fallback));
        if ($nickname === '') {
            return $fallback;
        }

        if (function_exists('mb_substr')) {
            $nickname = mb_substr($nickname, 0, 50, 'UTF-8');
        } else {
            $nickname = substr($nickname, 0, 50);
        }

        return $nickname ?: $fallback;
    }
}