<?php

namespace addons\third\library;

use fast\Http;
use think\Config;
use think\Session;

/**
 * 微信
 */
class Wechat
{
    const GET_AUTH_CODE_URL = "https://open.weixin.qq.com/connect/oauth2/authorize";
    const GET_ACCESS_TOKEN_URL = "https://api.weixin.qq.com/sns/oauth2/access_token";
    const GET_USERINFO_URL = "https://api.weixin.qq.com/sns/userinfo";

    protected $getAuthCodeUrl = "https://open.weixin.qq.com/connect/oauth2/authorize";
    protected $isWechat = true;
    protected $isWeb = false;

    /**
     * 配置信息
     * @var array
     */
    private $config = [];

    public function __construct($options = [])
    {
        if ($config = Config::get('third.wechat')) {
            $this->config = array_merge($this->config, $config);
        }
        $this->config = array_merge($this->config, is_array($options) ? $options : []);
        $this->isWechat = Service::isWechat();
        if (!$this->isWechat && request()->server('HTTP_USER_AGENT') && !request()->header('sid')) {
            //不在微信内调用PC端扫码登录
            $config = get_addon_config('third');
            $this->config = array_merge($this->config, $config['wechatweb'] ?? []);
            $this->isWeb = true;
            $this->getAuthCodeUrl = "https://open.weixin.qq.com/connect/qrconnect";
        }
    }

    /**
     * 登陆
     */
    public function login()
    {
        header("Location:" . $this->getAuthorizeUrl());
    }

    /**
     * 获取authorize_url
     */
    public function getAuthorizeUrl()
    {
        $state = md5(uniqid(rand(), true));
        Session::set('state', $state);
        $queryarr = array(
            "appid"         => $this->config['app_id'],
            "redirect_uri"  => $this->config['callback'],
            "response_type" => "code",
            "scope"         => $this->config['scope'],
            "state"         => $state,
        );
        if ($this->isWeb) {
            //PC端应用scope固定值
            $queryarr['scope'] = "snsapi_login";
        }
        $url = $this->getAuthCodeUrl . '?' . http_build_query($queryarr) . '#wechat_redirect';
        return $url;
    }

    /**
     * 获取用户信息
     * @param array $params
     * @return array
     */
    public function getUserInfo($params = [])
    {
        $params = $params ?: request()->get('', null, null);
        if (isset($params['access_token']) || (isset($params['state']) && $params['state'] == Session::get('state') && isset($params['code']))) {
            //获取access_token
            $data = isset($params['code']) ? $this->getAccessToken($params['code']) : $params;
            $access_token = $data['access_token'] ?? '';
            $refresh_token = $data['refresh_token'] ?? '';
            $expires_in = $data['expires_in'] ?? 0;
            if ($access_token) {
                $openid = $data['openid'] ?? '';
                $unionid = $data['unionid'] ?? '';
                if (preg_match("/(snsapi_userinfo|snsapi_login)/i", $this->config['scope'])) {
                    //获取用户信息
                    $queryarr = [
                        "access_token" => $access_token,
                        "openid"       => $openid,
                        "lang"         => 'zh_CN'
                    ];
                    $ret = Http::get(self::GET_USERINFO_URL, $queryarr);
                    $userinfo = (array)json_decode($ret, true);
                    if (!$userinfo || isset($userinfo['errcode'])) {
                        return [];
                    }
                    $userinfo['avatar'] = $userinfo['headimgurl'] ?? '';
                } else {
                    $userinfo = [];
                }
                $data = [
                    'access_token'  => $access_token,
                    'refresh_token' => $refresh_token,
                    'expires_in'    => $expires_in,
                    'openid'        => $openid,
                    'unionid'       => $unionid,
                    'userinfo'      => $userinfo,
                    'apptype'       => $this->isWeb ? 'web' : 'mp' //自定义 web:网页应用 mp 公众号
                ];
                return $data;
            }
        }
        return [];
    }

    /**
     * 获取access_token
     * @param string $code code
     * @return array
     */
    public function getAccessToken($code = '')
    {
        if (!$code) {
            return [];
        }
        $queryarr = array(
            "appid"      => $this->config['app_id'],
            "secret"     => $this->config['app_secret'],
            "code"       => $code,
            "grant_type" => "authorization_code",
        );
        $response = Http::get(self::GET_ACCESS_TOKEN_URL, $queryarr);
        $ret = (array)json_decode($response, true);
        return $ret ?: [];
    }
}
