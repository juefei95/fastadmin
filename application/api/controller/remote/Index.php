<?php

namespace app\api\controller\remote;

use addons\remotecontrol\library\RemoteMemberService;
use app\common\controller\Api;
use think\Config;
use think\Exception;

class Index extends Api
{
    protected $noNeedLogin = ['login'];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();

        if (!Config::get('fastadmin.usercenter')) {
            $this->error(__('User center already closed'));
        }

        $action = strtolower($this->request->action());
        if ($action === 'login' && !$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        if ($action === 'status' && !$this->request->isGet()) {
            $this->error(__('Invalid parameters'));
        }
    }

    public function login()
    {
        $account = $this->request->post('account');
        $password = $this->request->post('password');
        if (!$account || !$password) {
            $this->error(__('Invalid parameters'));
        }

        $ret = $this->auth->login($account, $password);
        if (!$ret) {
            $this->error($this->auth->getError());
        }

        $member = null;
        try {
            $service = new RemoteMemberService();
            $userId = $this->auth->id;
            $member = $service->getMember($userId);
            if (!$member || (int)$member['trial_given'] === 0) {
                $member = $service->grantTrial($userId);
            }
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }

        $this->success(__('Logged in successful'), [
            'userinfo' => $this->auth->getUserinfo(),
            'member'   => [
                'can_control'       => $member ? $service->canControl($userId) : false,
                'trial_given'       => $member ? (int)$member['trial_given'] : 0,
                'trial_started_at'  => $member ? (int)$member['trial_started_at'] : null,
                'expire_time'       => $member && $member['expire_time'] ? date('Y-m-d H:i:s', (int)$member['expire_time']) : null,
                'remaining_seconds' => $member && $member['expire_time'] ? max(0, (int)$member['expire_time'] - time()) : 0,
            ],
        ]);
    }

    public function status()
    {
        try {
            $service = new RemoteMemberService();
            $userId = $this->auth->id;
            $member = $service->getMember($userId);
            $canControl = $service->canControl($userId);
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }

        $expireTime = $member && $member['expire_time'] ? (int)$member['expire_time'] : 0;
        $this->success('', [
            'can_control'       => $canControl,
            'trial_given'       => $member ? (int)$member['trial_given'] : 0,
            'control_enabled'   => $member ? (int)$member['control_enabled'] : 0,
            'expire_time'       => $expireTime ? date('Y-m-d H:i:s', $expireTime) : null,
            'remaining_seconds' => $expireTime ? max(0, $expireTime - time()) : 0,
        ]);
    }
}
