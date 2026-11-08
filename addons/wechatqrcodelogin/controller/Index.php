<?php

namespace addons\wechatqrcodelogin\controller;

use addons\wechatqrcodelogin\library\WechatLoginService;
use addons\wechatqrcodelogin\library\LegacyUpdateService;
use app\common\controller\Api;

class Index extends Api
{
    protected $noNeedLogin = ['create', 'poll', 'notify', 'loginqrcode', 'loginstatus'];
    protected $noNeedRight = '*';

    /** 旧版显示更新二维码，不创建登录会话。 */
    public function loginqrcode()
    {
        $imagePath = ROOT_PATH . 'public/assets/addons/wechatqrcodelogin/legacy-update.svg';
        if (!is_file($imagePath)) {
            $imagePath = dirname(__DIR__) . '/assets/legacy-update.svg';
        }
        $image = @file_get_contents($imagePath);
        if ($image === false) {
            $this->error('暂时无法显示更新二维码，请稍后重试。');
        }
        $this->success('请扫码获取新版', [
            'scene' => 'update_required',
            'ticket' => '',
            'qr_url' => 'data:image/svg+xml;base64,' . base64_encode($image),
            'expires_in' => 300,
            'status' => 'pending',
        ]);
    }

    public function loginstatus()
    {
        try {
            $url = (new LegacyUpdateService())->downloadUrl();
            $message = '当前版本已停止支持，请在电脑浏览器打开下载地址：' . $url;
        } catch (\Throwable $e) {
            $message = '当前版本已停止支持，暂时无法获取下载地址，请扫码访问临点科技获取新版。';
        }
        $this->error($message);
    }

    public function create()
    {
        try {
            $result = (new WechatLoginService())->create($this->request->request('expire_seconds', 300));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
        }
        $this->success('ok', $result);
    }

    public function poll()
    {
        try {
            $result = (new WechatLoginService())->poll($this->request->request('scene', ''));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
        }
        $this->success($result['status'] === 'logged_in' ? '登录成功' : '', $result);
    }

    public function notify()
    {
        return (new WechatLoginService())->notify();
    }
}
