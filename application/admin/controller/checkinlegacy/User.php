<?php
namespace app\admin\controller\checkinlegacy;

use app\common\controller\Backend;
use think\Db;

class User extends Backend
{
    public function index()
    {
        $keyword = trim((string)$this->request->get('keyword', ''));
        $query = Db::name('third')->alias('t')
            ->join('__USER__ u', 'u.id=t.user_id', 'LEFT')
            ->where('t.platform', 'wechat_legacy')->where('t.apptype', 'miniapp')
            ->field('t.id,t.user_id,t.createtime,u.username,u.nickname,u.status');
        if ($keyword !== '') {
            if (ctype_digit($keyword)) $query->where('t.user_id', (int)$keyword);
            else $query->where('u.username', $keyword);
        }
        $page = $query->order('t.createtime desc,t.id desc')->paginate(30, false, ['query' => ['keyword' => $keyword]]);
        $this->view->assign('users', $page->items());
        $this->view->assign('pager', $page->render());
        $this->view->assign('keyword', $keyword);
        return $this->view->fetch();
    }
}
