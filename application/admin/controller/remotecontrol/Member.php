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
        $days = $this->request->param('days/d', 0);

        if (!$ids) {
            $this->error(__('Parameter %s can not be empty', 'ids'));
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
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


}
