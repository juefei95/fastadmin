<?php
namespace app\admin\controller\code2yellow;
use app\common\controller\Backend;
class User extends Backend
{
    protected $searchFields = 'user_id';
    public function _initialize() { parent::_initialize(); $this->model = new \app\admin\model\code2yellow\User; }
    public function index()
    {
        $this->request->filter(['strip_tags', 'trim']);
        if (!$this->request->isAjax()) return $this->view->fetch();
        [$where, $sort, $order, $offset, $limit] = $this->buildparams();
        $list = $this->model->where($where)->order($sort, $order)->paginate($limit);
        $rows = array_map(function ($row) { return $row->toArray(); }, $list->items());
        $ids = array_column($rows, 'user_id');
        $users = $ids ? \think\Db::name('user')->where('id', 'in', $ids)->column('nickname', 'id') : [];
        foreach ($rows as &$row) { $row['nickname'] = $users[$row['user_id']] ?? ''; }
        return json(['total' => $list->total(), 'rows' => $rows]);
    }
    public function add() { $this->error('用户列表仅供查看'); }
    public function edit($ids = null) { $this->error('用户列表仅供查看'); }
    public function del($ids = null) { $this->error('用户列表仅供查看'); }
    public function multi($ids = null) { $this->error('用户列表仅供查看'); }
    public function dragsort() { $this->error('用户列表仅供查看'); }
    public function destroy($ids = null) { $this->error('用户列表仅供查看'); }
    public function restore($ids = null) { $this->error('用户列表仅供查看'); }
}
