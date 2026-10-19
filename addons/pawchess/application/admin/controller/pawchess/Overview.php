<?php
namespace app\admin\controller\pawchess;

use app\common\controller\Backend;
use think\Db;

/** 毛球象棋社对局审计（只读） */
class Overview extends Backend
{
    protected $noNeedRight = [];

    public function index() { return $this->redirect('pawchess/overview/players'); }
    private function listing($table,$order) {
        if (!$this->request->isAjax()) {
            $this->view->assign('heading', ['paw_chess_player'=>'棋手','paw_chess_room'=>'房间','paw_chess_match'=>'对局','paw_chess_rating_log'=>'积分流水'][$table]);
            return $this->view->fetch('pawchess/overview/list');
        }
        $offset=max(0,(int)$this->request->get('offset',0));$limit=min(100,max(1,(int)$this->request->get('limit',20)));
        $query=Db::name($table);return json(['total'=>(clone $query)->count(),'rows'=>$query->order($order)->limit($offset,$limit)->select()]);
    }
    public function players() { return $this->listing('paw_chess_player','points desc,wins desc,id asc'); }
    public function rooms() { return $this->listing('paw_chess_room','created_at desc'); }
    public function matches() { return $this->listing('paw_chess_match','created_at desc'); }
    public function ratinglog() { return $this->listing('paw_chess_rating_log','id desc'); }
}
