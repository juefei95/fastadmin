<?php

namespace addons\txsms\controller;

use think\addons\Controller;

/**
 * 腾讯云短信
 */
class Index extends Controller
{

    protected $model = null;
    protected $templateList = [
        'register'     => '注册',
        'resetpwd'     => '重置密码',
        'changepwd'    => '修改密码',
        'changemobile' => '修改手机号',
        'profile'      => '修改个人信息',
        'notice'       => '通知',
        'mobilelogin'  => '验证码登录',
        'bind'         => '绑定账号',
    ];

    public function _initialize()
    {
        if (!\app\admin\library\Auth::instance()->id) {
            $this->error('暂无权限浏览');
        }
        parent::_initialize();
    }

    //首页
    public function index()
    {
        $this->view->assign('templateList', $this->templateList);
        return $this->view->fetch();
    }

    //发送测试短信
    public function send()
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $config = get_addon_config('txsms');
        $mobile = $this->request->post('mobile');
        $template = $this->request->post('template');
        $sign = $this->request->post('sign', '');

        if (!$mobile) {
            $this->error('手机号不能为空');
        }

        $templateArr = $config['template'] ?? [];
        if (!isset($templateArr[$template]) || !$templateArr[$template]) {
            $this->error('后台未配置对应的模板');
        }
        $template = $templateArr[$template];
        $signName = $sign ?: $config['signName'];
        $param = (array)json_decode($this->request->post('param', '', 'trim'));

        $length = config('captcha.length');
        $param = $param ?: [mt_rand(pow(10, $length - 1), pow(10, $length) - 1)];
        $cloudSms = new \addons\txsms\library\TencentCloudSmsClient();
        $ret = $cloudSms->setPhoneNumbers($mobile)
            ->setTemplateId($template)
            ->setSmsSdkAppId($config['smsSdkAppId'])
            ->setSignName($signName)
            ->setTemplateParams($param)
            ->sendSms();
        if ($ret) {
            $this->success("发送成功");
        } else {
            $error = $cloudSms->getError();
            $this->error("发送短信失败，错误码：" . $error['code'] . "，错误信息：" . $error['message']);
        }
    }

}
