<?php

namespace addons\cms\controller;

use addons\cms\model\Archives;
use addons\cms\model\Comment;
use addons\cms\model\SpiderLog;
use think\Config;

/**
 * 会员个人主页控制器
 * Class User
 * @package addons\cms\controller
 */
class User extends Base
{

    public function index()
    {
        $config = get_addon_config('cms');
        if (!$config['userpage']) {
            $this->error("会员主页功能已关闭");
        }
        $user_id = $this->request->param('id');
        $user = \app\common\model\User::get($user_id);
        if (!$user) {
            $this->error("未找到指定会员", null, '', 3, ['status' => '404 Not Found']);
        }
        if ($user['status'] == 'hidden') {
            $this->error("暂时无法浏览");
        }

        // 判断是否跳转移动端
        $this->checkredirect('user', ['user_id' => $user->id]);

        $pathArr = explode('/', $this->request->pathinfo());
        $type = end($pathArr);
        $type = is_numeric($type) || !in_array($type, ['archives', 'comment']) ? 'archives' : $type;

        $pagesize = 10;
        $page = $this->request->get('page/d', 1);
        $page = max(1, $page);
        $statistics = ['archives' => 0, 'comments' => 0];

        //分页模式
        $simple = !($config['loadmode'] == 'paging' && $config['pagemode'] == 'full');

        if ($type == 'archives') {
            $total = Archives::where('user_id', $user['id'])->where('status', 'normal')->count();
            $archivesList = Archives::with(['user', 'channel'])
                ->where('user_id', $user['id'])
                ->where('status', 'normal')
                ->order('id', 'desc')
                ->paginate($pagesize, $simple, ['var_page' => 'page', 'fragment' => '']);
            $this->view->assign('archivesList', $archivesList);
            $this->view->assign('__PAGELIST__', $archivesList);
            $statistics['archives'] = $total;
            $statistics['comments'] = Comment::where('user_id', $user['id'])->cache(3600, null, 'cms')->where('status', 'normal')->count();
        } else {
            $total = Comment::where('user_id', $user['id'])->where('status', 'normal')->count();
            $commentList = Comment::with(['user'])
                ->where('user_id', $user['id'])
                ->where('status', 'normal')
                ->order('id', 'desc')
                ->paginate($pagesize, $simple, ['var_page' => 'page', 'fragment' => '']);
            $collection = $commentList->getCollection();
            load_relation($collection, 'source');
            $this->view->assign('commentList', $commentList);
            $this->view->assign('__PAGELIST__', $commentList);
            $statistics['archives'] = Archives::where('user_id', $user['id'])->cache(3600, null, 'cms')->where('status', 'normal')->count();
            $statistics['comments'] = $total;
        }

        $title = $type == 'archives' ? '的文章' : '的评论';
        Config::set('cms.title', $user['nickname'] . $title);
        Config::set('cms.image', isset($user['avatar']) && $user['avatar'] ? cdnurl($user['avatar'], true) : '');

        SpiderLog::record('user', $user['id']);

        $this->view->assign('statistics', $statistics);
        $this->view->assign('title', ($this->auth->id == $user['id'] ? '我' : 'TA') . $title);
        $this->view->assign('page', $page);
        $this->view->assign('type', $type);
        $this->view->assign('__USER__', $user);

        if ($this->request->isAjax()) {
            $this->success("", "", $this->view->fetch('ajax/user'));
        }
        return $this->view->fetch("/user");
    }

}
