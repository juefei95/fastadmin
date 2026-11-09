<?php

namespace app\admin\controller\remotecontrol;

use app\common\controller\Backend;

class Device extends Backend
{
    protected $searchFields = 'device_id,hostname,username';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\remotecontrol\Device;
    }

    public function index()
    {
        $this->request->filter(['strip_tags', 'trim']);
        if (!$this->request->isAjax()) {
            return $this->view->fetch();
        }

        [$where, $sort, $order, $offset, $limit] = $this->buildparams();
        $list = $this->model
            ->field('id,device_id,client_version,hostname,username,connection_count,last_seen_at')
            ->where($where)
            ->order($sort, $order)
            ->paginate($limit);

        return json(['total' => $list->total(), 'rows' => $list->items()]);
    }

    public function add()
    {
        $this->error('设备状态仅供查看');
    }

    public function edit($ids = null)
    {
        $this->error('设备状态仅供查看');
    }

    public function del($ids = null)
    {
        $this->error('设备状态仅供查看');
    }

    public function multi($ids = null)
    {
        $this->error('设备状态仅供查看');
    }

    public function dragsort()
    {
        $this->error('设备状态仅供查看');
    }

    public function destroy($ids = null)
    {
        $this->error('设备状态仅供查看');
    }

    public function restore($ids = null)
    {
        $this->error('设备状态仅供查看');
    }
}
