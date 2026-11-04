<?php
namespace addons\pawnightmarket\controller;

use app\common\controller\Api as BaseApi;
use addons\third\library\Service;
use fast\Http;

class Api extends BaseApi
{
    protected $noNeedLogin = ['login'];
    protected $noNeedRight = ['*'];

    public function login()
    {
        if ($this->auth->isLogin()) $this->error('请不要携带已有登录凭证');
        if (!$this->request->isPost()) $this->error('请使用POST请求');
        $code = $this->request->post('code', '', 'trim');
        if (!$code || strlen($code) > 256) $this->error('登录凭证无效');
        $config = get_addon_config('pawnightmarket');
        $appid = $config['wechat_appid'] ?? '';
        $secret = $config['wechat_secret'] ?? '';
        // Keep existing environment-only deployments working when no UI settings exist.
        if (!$appid && !$secret) {
            $appid = getenv('PAW_WECHAT_APPID');
            $secret = getenv('PAW_WECHAT_SECRET');
        }
        if (!$appid || !$secret) $this->error('小游戏登录尚未配置');
        $response = Http::get('https://api.weixin.qq.com/sns/jscode2session', [
            'appid' => $appid, 'secret' => $secret, 'js_code' => $code,
            'grant_type' => 'authorization_code'
        ], [CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_FOLLOWLOCATION => false]);
        $identity = json_decode($response, true);
        if (!is_array($identity) || !empty($identity['errcode']) || empty($identity['openid'])) {
            $this->error('微信登录失败，请重试');
        }
        // Never trust a client-supplied identity or persist the WeChat session key.
        $params = ['openid' => $identity['openid'], 'unionid' => $identity['unionid'] ?? '', 'apptype' => 'miniapp'];
        if (!Service::connect('wechat', $params)) $this->error('账号登录失败');
        $this->success('登录成功', ['user' => $this->auth->getUserinfo()]);
    }
}
