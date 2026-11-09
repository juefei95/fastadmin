<?php
namespace app\admin\controller\checkin;

use app\common\controller\Backend;
use think\Db;

class User extends Backend
{
    public function index()
    {
        $keyword = trim((string)$this->request->get('keyword', ''));
        $query = Db::name('checkin_user')->alias('c')
            ->join('__USER__ u', 'u.id=c.user_id', 'LEFT')
            ->field('c.user_id,c.createtime,u.username,u.nickname,u.status');
        if ($keyword !== '') {
            if (ctype_digit($keyword)) {
                $query->where('c.user_id', (int)$keyword);
            } else {
                $query->where('u.username', $keyword);
            }
        }
        $page = $query->order('c.createtime desc,c.user_id desc')->paginate(30, false, ['query' => ['keyword' => $keyword]]);
        $this->view->assign('users', $page->items());
        $this->view->assign('pager', $page->render());
        $this->view->assign('keyword', $keyword);
        return $this->view->fetch();
    }
}
