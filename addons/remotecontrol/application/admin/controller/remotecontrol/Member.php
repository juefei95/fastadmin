<?php

namespace app\admin\controller\remotecontrol;

use addons\remotecontrol\library\RemoteMemberService;
use app\common\controller\Backend;
use think\Exception;

/**
 * 远控用户权益管理
 *
 * @icon fa fa-circle-o
 */
class Member extends Backend
{

    /**
     * Member模型对象
     * @var \app\admin\model\remotecontrol\Member
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\remotecontrol\Member;
        $this->view->assign("trialGivenList", $this->model->getTrialGivenList());
        $this->view->assign("controlEnabledList", $this->model->getControlEnabledList());
    }

    /**
     * 增加有效期
     */
    public function adddays($ids = null)
    {
        $ids = $ids ?: $this->request->param('ids');
        if (!$ids) {
            $this->error(__('Parameter %s can not be empty', 'ids'));
        }

        $days = $this->request->param('days/d', 0);
        if (false === $this->request->isPost() && !$days) {
            $this->view->assign('ids', $ids);
            return $this->view->fetch();
        }

        if ($days <= 0) {
            $this->error(__('Days must be greater than 0'));
        }

        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        try {
            $service = new RemoteMemberService();
            $service->addDays($row['user_id'], $days);
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }

        $this->success(__('Operate successful'));
    }

    /**
     * 设置到期时间
     */
    public function setexpire($ids = null)
    {
        $ids = $ids ?: $this->request->param('ids');
        if (!$ids) {
            $this->error(__('Parameter %s can not be empty', 'ids'));
        }

        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        if (false === $this->request->isPost()) {
            $this->view->assign('row', $row);
            return $this->view->fetch();
        }

        $expireTime = $this->request->param('expire_time');
        try {
            $service = new RemoteMemberService();
            $service->setExpire($row['user_id'], $expireTime);
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }

        $this->success(__('Operate successful'));
    }

    /**
     * 启用控制权限
     */
    public function enable($ids = null)
    {
        $this->setControlEnabled($ids, true);
    }

    /**
     * 禁用控制权限
     */
    public function disable($ids = null)
    {
        $this->setControlEnabled($ids, false);
    }

    protected function setControlEnabled($ids, $enabled)
    {
        $ids = $ids ?: $this->request->param('ids');
        if (!$ids) {
            $this->error(__('Parameter %s can not be empty', 'ids'));
        }

        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        try {
            $service = new RemoteMemberService();
            $enabled ? $service->enable($row['user_id']) : $service->disable($row['user_id']);
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }

        $this->success(__('Operate successful'));
    }



    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


}
