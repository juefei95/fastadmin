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

        if (!$this->request->isPost()) {
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
}
