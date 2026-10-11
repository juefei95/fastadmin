<?php

namespace app\admin\controller;

use app\common\controller\Backend;

/**
 * 游戏排行榜管理
 *
 * @icon fa fa-trophy
 */
class Gamerank extends Backend
{
    /**
     * 父级菜单默认入口。
     *
     * 菜单点击会请求 gamerank/index；将其定向至实际的玩家列表，
     * 避免框架按约定查找不存在的 view/gamerank/index.html。
     */
    public function index()
    {
        return $this->redirect('gamerank/player');
    }

    public function player()
    {
        $this->relationSearch = true;
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $model = new \app\admin\model\GamerankPlayer;
            $total = $model->with(['user'])->where($where)->order($sort, $order)->count();
            $list = $model->with(['user'])->where($where)->order($sort, $order)->limit($offset, $limit)->select();
            foreach ($list as $item) {
                if ($item->user) {
                    $item->user->visible(['username', 'nickname']);
                }
            }
            return json(['total' => $total, 'rows' => collection($list)->toArray()]);
        }
        return $this->view->fetch();
    }

    public function pointlog()
    {
        $this->relationSearch = true;
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $model = new \app\admin\model\GamerankPointLog;
            $total = $model->with(['user'])->where($where)->order($sort, $order)->count();
            $list = $model->with(['user'])->where($where)->order($sort, $order)->limit($offset, $limit)->select();
            foreach ($list as $item) {
                if ($item->user) {
                    $item->user->visible(['username', 'nickname']);
                }
            }
            return json(['total' => $total, 'rows' => collection($list)->toArray()]);
        }
        return $this->view->fetch();
    }

    public function record()
    {
        $this->relationSearch = true;
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            if ($this->request->request('keyField')) return $this->selectpage();
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $model = new \app\admin\model\GamerankRecord;
            $total = $model->with(['user'])->where($where)->order($sort, $order)->count();
            $list = $model->with(['user'])->where($where)->order($sort, $order)->limit($offset, $limit)->select();
            foreach ($list as $item) if ($item->user) $item->user->visible(['username', 'nickname']);
            return json(['total' => $total, 'rows' => collection($list)->toArray()]);
        }
        return $this->view->fetch();
    }
}
