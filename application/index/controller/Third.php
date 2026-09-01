<?php

namespace app\index\controller;

use addons\third\library\Application;
use addons\third\library\Service;
use app\common\controller\Frontend;
use app\common\library\Sms;
use fast\Random;
use think\Cookie;
use think\Hook;
use think\Lang;
use think\Session;

/**
 * 第三方登录控制器
 */
class Third extends Frontend
{
    protected $noNeedLogin = ['prepare'];
    protected $noNeedRight = ['*'];
    protected $app = null;
    protected $options = [];
    protected $layout = 'default';

    public function _initialize()
    {
        parent::_initialize();
        $config = get_addon_config('third');
        $this->app = new Application($config);

        $auth = $this->auth;
        //监听注册登录事件
        Hook::add('user_login_successed', function ($user) use ($auth) {
            $expire = input('post.keeplogin') ? 30 * 86400 : 0;
            Cookie::set('uid', $user->id, $expire);
            Cookie::set('token', $auth->getToken(), $expire);
        });
        Hook::add('user_register_successed', function ($user) use ($auth) {
            Cookie::set('uid', $user->id);
            Cookie::set('token', $auth->getToken());
        });
    }

    /**
     * 准备绑定
     */
    public function prepare()
    {
        $platform = $this->request->request('platform', '');
        if (!in_array($platform, ['wechat', 'weibo', 'qq'])) {
            $this->error("未找到指定平台");
        }
        $url = $this->request->get('url', '/', 'trim,xss_clean');

        // 授权成功后的回调
        $thirdinfo = Session::get("third-{$platform}");
        if (!$thirdinfo) {
            $this->error("操作失败，请返回重试");
        }

        $apptype = Service::getApptype();
        $openid = $thirdinfo['openid'] ?? '';
        $unionid = $thirdinfo['unionid'] ?? '';

        // 如果是登录状态，直接跳到绑定
        if ($this->auth->id) {
            $isBind = Service::isBindThird($platform, $openid, '', $unionid);
            if ($isBind) {
                $this->error("已经绑定其它账号，无法进行绑定");
            }
            $this->redirect(url("index/third/bind") . "?" . http_build_query(['platform' => $platform, 'url' => $url]));
        }

        if ($this->request->isPost()) {
            $mobile = $this->request->post("mobile");
            $platform = $this->request->post("platform");
            $captcha = $this->request->post("captcha");
            $nickname = $thirdinfo['userinfo']['nickname'] ?? '';
            $avatar = $thirdinfo['userinfo']['avatar'] ?? '';

            if (!Sms::check($mobile, $captcha, 'bind')) {
                $this->error(__('验证码不正确'));
            }

            $user = \app\common\model\User::where('mobile', $mobile)->find();
            if ($user) {
                $isBind = \addons\third\model\Third::where('platform', $platform)->where('user_id', $user['id'])->find();
                if ($isBind) {
                    $this->error('该手机号已经占用');
                }

                $result = $this->auth->direct($user->id);
            } else {
                $username = method_exists('\fast\Random', 'username')
                    ? Random::username()
                    : 'user_' . bin2hex(random_bytes(8)) . substr(uniqid(), -8);
                $nickname = $nickname ?: '用户' . mb_substr($mobile, -4);
                $extend = array_filter(['nickname' => $nickname, 'avatar' => $avatar]);
                $result = $this->auth->register($username, Random::alnum(16), '', $mobile, $extend);
            }
            // 账号创建成功则添加绑定第三方绑定
            if ($result) {
                \addons\third\model\Third::create(['user_id' => $this->auth->id, 'platform' => $platform, 'apptype' => $apptype, 'openid' => $openid, 'unionid' => $unionid, 'openname' => $nickname], true);
            }

            //删除临时Session
            Session::delete("third-{$platform}");

            //绑定成功，跳转到之前页面
            $this->success("绑定成功", $url);
        }

        $this->view->assign('userinfo', $thirdinfo['userinfo']);
        $this->view->assign('platform', $platform);
        $this->view->assign('url', $url);
        $this->view->assign('bindurl', url("index/third/bind") . '?' . http_build_query(['platform' => $platform, 'url' => $url]));
        $this->view->assign('captchaType', config('fastadmin.user_register_captcha'));
        $this->view->assign('title', "账号绑定");

        return $this->view->fetch();
    }

    /**
     * 绑定账号
     */
    public function bind()
    {
        $platform = $this->request->request('platform', '');
        if (!in_array($platform, ['wechat', 'weibo', 'qq'])) {
            $this->error("未找到指定平台");
        }
        $url = $this->request->get('url', $this->request->server('HTTP_REFERER', '', 'trim'), 'trim');
        if (!$platform) {
            $this->error("参数不正确");
        }

        $apptype = $platform == 'wechat' ? Service::getApptype() : '';

        // 授权成功后的回调
        $thirdinfo = Session::get("third-{$platform}");
        if (!$thirdinfo) {
            $this->redirect(addon_url('third/index/connect', [':platform' => $platform]) . '?url=' . urlencode($url));
        }
        $third = \addons\third\model\Third::where('user_id', $this->auth->id)->where('platform', $platform)->find();
        if ($third) {
            $this->error("已绑定账号，请勿重复绑定");
        }
        $time = time();
        $values = [
            'platform'      => $platform,
            'apptype'       => $apptype,
            'user_id'       => $this->auth->id,
            'unionid'       => $thirdinfo['unionid'] ?? '',
            'openid'        => $thirdinfo['openid'],
            'openname'      => $thirdinfo['userinfo']['nickname'] ?? '',
            'access_token'  => $thirdinfo['access_token'],
            'refresh_token' => $thirdinfo['refresh_token'],
            'expires_in'    => $thirdinfo['expires_in'],
            'logintime'     => $time,
            'expiretime'    => $time + $thirdinfo['expires_in'],
        ];
        $isBind = Service::isBindThird($platform, $values['openid'], '', $values['unionid']);
        if ($isBind) {
            $this->error("已经绑定其它账号，无法进行绑定");
        }
        $third = \addons\third\model\Third::create($values);
        if ($third) {

            //删除临时Session
            Session::delete("third-{$platform}");

            $this->success("账号绑定成功", $url);
        } else {
            $this->error("账号绑定失败，请重试", $url);
        }
    }

    /**
     * 解绑账号
     */
    public function unbind()
    {
        $platform = $this->request->request('platform', '');
        if (!in_array($platform, ['wechat', 'weibo', 'qq'])) {
            $this->error("未找到指定平台");
        }
        $apptype = $platform == 'wechat' ? Service::getApptype() : '';

        $third = \addons\third\model\Third::where('user_id', $this->auth->id)
            ->where('platform', $platform)
            ->where(function ($query) use ($platform, $apptype) {
                if ($platform == 'wechat') {
                    $query->where('apptype', $apptype);
                }
            })
            ->find();
        if (!$third) {
            $this->error("未找到指定的账号绑定信息");
        }
        Session::delete("third-{$platform}");
        $third->delete();
        $this->success("账号解绑成功");
    }
}
