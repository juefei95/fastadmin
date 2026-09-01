<?php

namespace addons\third\controller;

use addons\third\library\Application;
use app\common\controller\Api as commonApi;
use addons\third\library\Service;
use addons\third\model\Third;
use app\common\library\Sms;
use fast\Random;
use think\Config;
use think\Session;
use think\Validate;

/**
 * 第三方登录插件
 */
class Api extends commonApi
{
    protected $noNeedLogin = ['getAuthUrl', 'callback', 'account', 'getOpenidCallback']; // 无需登录即可访问的方法，同时也无需鉴权了
    protected $noNeedRight = ['*']; // 无需鉴权即可访问的方法

    protected $app = null;
    protected $options = [];
    protected $config = null;

    public function _initialize()
    {
        //跨域检测
        check_cors_request();
        //设置session_id
        Config::set('session.id', $this->request->server("HTTP_SID"));

        parent::_initialize();
        $this->config = get_addon_config('third');
        $name = $this->request->request('addon');
        if (!$name && get_addon_info($name)) {
            //判断是否有插件标识，如果有插件标识则取指定插件中的配置
            $config = get_addon_config($name);
            $this->config = array_merge($this->config, array_intersect_key($config, array_flip(['qq', 'wechat', 'wechatweb', 'weibo'])));
        }

        //判断是否静默授权
        $scope = $this->request->param('scope', '', 'trim');
        if ($scope) {
            $this->config['scope'] = $scope;
        }
        $this->app = new Application($this->config);
    }

    /**
     * 获取授权链接
     */
    public function getAuthUrl()
    {
        $url = $this->request->param('url', '', 'trim');
        $platform = $this->request->param('platform');
        if (!$url || !$platform || !isset($this->config[$platform])) {
            $this->error('参数错误');
        }
        $this->config[$platform]['callback'] = $url;

        $this->app = new Application($this->config);
        if (!$this->app->{$platform}) {
            $this->error(__('参数错误'));
        }
        $this->success('', $this->app->{$platform}->getAuthorizeUrl());
    }

    /**
     * 授权回调的请求
     */
    public function callback()
    {
        $platform = $this->request->param('platform');
        $apptype = $this->request->param('apptype');
        $base = $this->request->param('base', 0); //只返回基础信息
        $scope = $this->request->param('scope', '');
        $bind = $this->request->param('bind', 1); //默认要求绑定账号

        if (!$this->app->{$platform}) {
            $this->error(__('参数错误'));
        }
        $userinfo = $this->app->{$platform}->getUserInfo($this->request->param('', null, null));
        if (!$userinfo) {
            $this->error(__('操作失败'));
        }
        if ($base) {
            $this->success("授权成功！", $userinfo['userinfo']);
        }

        $userinfo['apptype'] = $apptype ?: Service::getApptype();
        $userinfo['platform'] = $platform;

        $third = [
            'avatar'   => $userinfo['userinfo']['avatar'],
            'nickname' => $userinfo['userinfo']['nickname']
        ];

        $user = null;
        $config = get_addon_config('third');
        if (!$bind || $this->auth->isLogin() || Service::isBindThird($userinfo['platform'], $userinfo['openid'], $userinfo['apptype'], $userinfo['unionid']) || !$config['bindaccount']) {
            $result = Service::connect($userinfo['platform'], $userinfo);
            if (!$result) {
                $this->error('授权登录失败');
            }
            $user = $this->auth->getUserinfo();
        } else {
            $user = false;
            Session::set('third-userinfo', $userinfo);
        }
        $this->success("授权成功！", ['user' => $user, 'third' => $third, 'openid' => $userinfo['openid']]);
    }

    /**
     * 获取Openid回调
     */
    public function getOpenidCallback()
    {
        $platform = $this->request->param('platform');
        if (!$this->app->{$platform}) {
            $this->error(__('参数错误'));
        }
        $userinfo = $this->app->{$platform}->getUserInfo($this->request->param('', null, null));
        if (!$userinfo) {
            $this->error(__('操作失败'));
        }

        $this->success('', ['openid' => $userinfo['openid']]);
    }

    /**
     * 登录或创建账号
     */
    public function account()
    {
        if ($this->request->isPost()) {
            $params = Session::get('third-userinfo');
            $mobile = $this->request->post('mobile', '');
            $code = $this->request->post('code', $this->request->post('captcha'));
            $token = $this->request->post('__token__');
            $nickname = is_array($params) && isset($params['userinfo']['nickname']) ? $params['userinfo']['nickname'] : '';
            $avatar = is_array($params) && isset($params['userinfo']['avatar']) ? $params['userinfo']['avatar'] : '';

            $rule = [
                'mobile'    => 'require|regex:/^1\d{10}$/',
                '__token__' => 'require|token',
            ];
            $msg = [
                'mobile' => 'Mobile is incorrect',
            ];
            $data = [
                'mobile'    => $mobile,
                '__token__' => $token,
            ];
            $ret = Sms::check($mobile, $code, 'bind');
            if (!$ret) {
                $this->error(__('验证码错误'));
            }
            $validate = new Validate($rule, $msg);
            $result = $validate->check($data);
            if (!$result) {
                $this->error(__($validate->getError()), ['__token__' => $this->request->token()]);
            }

            $userinfo = \app\common\model\User::where('mobile', $mobile)->find();
            if ($userinfo) {
                $isBind = Third::where('platform', $params['platform'])->where('user_id', $userinfo['id'])->find();
                if ($isBind) {
                    $this->error('该手机号已经占用');
                }
                $result = $this->auth->direct($userinfo->id);
            } else {
                $username = method_exists('\fast\Random', 'username')
                    ? Random::username()
                    : 'user_' . bin2hex(random_bytes(8)) . substr(uniqid(), -8);
                $nickname = $nickname ?: '用户' . mb_substr($mobile, -4);
                $extend = array_filter(['nickname' => $nickname, 'avatar' => $avatar]);
                $result = $this->auth->register($username, Random::alnum(16), '', $mobile, $extend);
            }

            if ($result) {
                $result = Service::connect($params['platform'], $params);
                if (!$result) {
                    $this->error('授权登录失败');
                }
                $this->success(__('绑定账号成功'), ['userinfo' => $this->auth->getUserinfo()]);
            } else {
                $this->error($this->auth->getError(), ['__token__' => $this->request->token()]);
            }
        }
    }

    /**
     * 获取第三方登录绑定列表
     */
    public function getBindList()
    {
        $thirdList = Third::where('user_id', $this->auth->id)->field('user_id,platform,apptype')->select();
        $this->success('', collection($thirdList)->toArray());
    }


    /**
     * 解绑
     */
    public function unbind()
    {
        $platform = $this->request->post("platform");
        $apptype = $this->request->post("apptype");
        $exist = Third::where('user_id', $this->auth->id)->where('platform', $platform)->where('apptype', $apptype)->find();
        if ($exist) {
            $this->success("解绑成功");
        } else {
            $this->error("未找到相关数据");
        }
    }

}
