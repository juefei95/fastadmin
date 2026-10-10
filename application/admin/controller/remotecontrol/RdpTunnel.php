<?php

namespace app\admin\controller\remotecontrol;

use addons\remotecontrol\library\RemoteRdpTunnelService;
use app\common\controller\Backend;
use think\Db;
use think\Exception;

class RdpTunnel extends Backend
{
    protected $searchFields = 'device_id,public_port,status,last_error';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\remotecontrol\RdpTunnel;
    }

    public function index()
    {
        $this->request->filter(['strip_tags', 'trim']);
        if (!$this->request->isAjax()) {
            return $this->view->fetch();
        }
        [$where, $sort, $order, $offset, $limit] = $this->buildparams();
        $list = $this->model->where($where)->order($sort, $order)->paginate($limit);
        $userIds = [];
        $deviceIds = [];
        foreach ($list as $row) {
            $userIds[] = (int)$row['user_id'];
            $deviceIds[] = (string)$row['device_id'];
        }
        $members = [];
        if ($userIds) {
            foreach (Db::name('remote_member')->where('user_id', 'in', array_unique($userIds))->field('user_id,expire_time,control_enabled')->select() as $member) {
                $members[$member['user_id']] = $member;
            }
        }
        $devices = [];
        if ($deviceIds) {
            foreach (Db::name('remote_device')->where('device_id', 'in', array_unique($deviceIds))->field('device_id,hostname,username')->select() as $device) {
                $devices[$device['device_id']] = $device;
            }
        }
        foreach ($list as $row) {
            $member = $members[$row['user_id']] ?? [];
            $device = $devices[$row['device_id']] ?? [];
            $row['expire_time'] = (int)($member['expire_time'] ?? 0);
            $row['control_enabled'] = (int)($member['control_enabled'] ?? 0);
            $row['hostname'] = (string)($device['hostname'] ?? '');
            $row['username'] = (string)($device['username'] ?? '');
        }
        return json(['total' => $list->total(), 'rows' => $list->items()]);
    }

    public function close($ids = null)
    {
        $ids = $ids ?: $this->request->param('ids');
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        try {
            (new RemoteRdpTunnelService())->requestStop($row['user_id'], $row['device_id'], 'admin');
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
        $this->success(__('Operate successful'));
    }

    public function add() { $this->error('映射由客户端申请，后台仅支持查看和停用'); }
    public function edit($ids = null) { $this->error('映射由客户端申请，后台仅支持查看和停用'); }
    public function del($ids = null) { $this->error('映射由客户端申请，后台仅支持查看和停用'); }
    public function multi($ids = null) { $this->error('映射由客户端申请，后台仅支持查看和停用'); }
}
