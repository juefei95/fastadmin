<?php

namespace addons\cms\controller;

use addons\cms\library\IntCode;
use addons\cms\library\Service;
use addons\cms\model\Archives as ArchivesModel;
use addons\cms\model\Channel;
use addons\cms\model\Fields;
use addons\cms\model\Modelx;
use addons\cms\model\SpiderLog;
use think\Config;
use think\Exception;

/**
 * 文档控制器
 * Class Archives
 * @package addons\cms\controller
 */
class Archives extends Base
{
    public function index()
    {
        $config = get_addon_config('cms');
        $action = $this->request->post("action");
        if ($action && $this->request->isPost() && in_array($action, ['vote', 'download'])) {
            return $this->$action();
        }
        $catename = $this->request->param('catename');
        $diyname = $this->request->param('diyname');
        $eid = $this->request->param('eid');
        if ($eid) {
            $diyname = IntCode::decode($eid);
        }
        if ($diyname && !is_numeric($diyname)) {
            $archives = ArchivesModel::with('channel')->where('diyname', $diyname)->find();
        } else {
            $id = $diyname ?: $this->request->param('id', '');
            $archives = ArchivesModel::get($id, ['channel']);
        }
        if (!$archives || ($archives['status'] != 'normal' && (!$archives['user_id'] || $archives['user_id'] != $this->auth->id)) || $archives['deletetime']) {
            $this->error(__('No specified article found'), null, '', 3, ['status' => '404 Not Found']);
        }

        if (!$this->auth->id && !$archives['isguest']) {
            $this->error(__('Please login first'), 'index/user/login');
        }
        $channel = $archives->channel;
        if (!$channel) {
            $this->error(__('No specified channel found'));
        }
        if ($catename && $catename != $channel['diyname']) {
            $this->error(__('No specified channel found'));
        }
        $model = Modelx::get($channel['model_id'], [], true);
        if (!$model) {
            $this->error(__('No specified model found'));
        }
        $addon = db($model['table'])->where('id', $archives['id'])->find();
        if ($addon) {
            if ($model->fields) {
                Service::appendTextAndList('model', $model->id, $addon);
            }
            $archives->setData($addon);
        } else {
            $this->error(__('No specified addon article found'));
        }

        // 判断是否跳转移动端
        $this->checkredirect('archives', ['id' => $archives->id, 'model' => $model->id, 'diyname' => $archives->diyname, 'eid' => $archives->eid]);

        SpiderLog::record('archives', $archives['id']);

        // 追加主表自定义字段Text和List
        Service::appendTextAndList('archives', 0, $archives);

        // 追加栏目自定义字段Text和List
        Service::appendTextAndList('channel', 0, $channel);

        //PC支持内容分页
        $page = (int)$this->request->request("page", 1);
        $page = max(1, $page);
        $contentArr = array_values(array_filter(explode("##pagebreak##", $archives->content)));
        $content = $contentArr ? ($contentArr[$page - 1] ?? $contentArr[0]) : '';

        $archives->content = $content . $archives->getPagerHTML($page, count($contentArr));

        $archives->setInc("views", 1);
        $this->view->assign("__ARCHIVES__", $archives);
        $this->view->assign("__CHANNEL__", $channel);
        $this->view->assign("__MODEL__", $model);

        //统计作者文章数和评论数
        if ($archives->user) {
            $archives->user->archives = ArchivesModel::where('user_id', $archives->user_id)->where('status', 'normal')->cache(3600, null, 'cms')->count();
            $archives->user->comments = \addons\cms\model\Comment::where('user_id', $archives->user_id)->where('status', 'normal')->cache(3600, null, 'cms')->count();
        }

        //设置TKD
        Config::set('cms.title', isset($archives['seotitle']) && $archives['seotitle'] ? $archives['seotitle'] : $archives['title']);
        Config::set('cms.keywords', $archives['keywords']);
        Config::set('cms.description', $archives['description']);
        Config::set('cms.image', isset($archives['image']) && $archives['image'] ? cdnurl($archives['image'], true) : '');

        //是否跳转链接
        if (isset($archives['outlink']) && $archives['outlink']) {
            $this->redirect($archives['outlink']);
        }

        $template = preg_replace("/\.html\$/i", '', $channel['showtpl']);
        if (!$template) {
            $this->error('请检查栏目是否配置相应的模板');
        }

        return $this->view->fetch('/' . $template);
    }

    /**
     * 赞与踩
     */
    public function vote()
    {
        $id = (int)$this->request->post("id");
        $type = trim($this->request->post("type", ""));
        if (!$id || !$type) {
            $this->error(__('Operation failed'));
        }
        $archives = ArchivesModel::get($id);
        if (!$archives || ($archives['user_id'] != $this->auth->id && $archives['status'] != 'normal') || $archives['deletetime']) {
            $this->error(__('No specified article found'));
        }
        $archives->where('id', $id)->setInc($type === 'like' ? 'likes' : 'dislikes', 1);
        $archives = ArchivesModel::get($id);
        $this->success(__('Operation completed'), null, ['likes' => $archives->likes, 'dislikes' => $archives->dislikes, 'likeratio' => $archives->likeratio]);
    }

    /**
     * 下载次数
     */
    public function download()
    {
        if (!$this->request->isPost()) {
            $this->error("请求方式错误");
        }
        $id = (int)$this->request->post("id");
        if (!$id) {
            $this->error(__('Operation failed'));
        }
        $archives = ArchivesModel::get($id, ['model']);
        if (!$archives || ($archives['user_id'] != $this->auth->id && $archives['status'] != 'normal') || $archives['deletetime']) {
            $this->error(__('No specified article found'));
        }
        try {
            $table = $archives->getRelation('model')->getData('table');
            \think\Db::name($table)->where('id', $id)->setInc('downloads');
        } catch (Exception $e) {
            //
        }
        $this->success(__('Operation completed'), null);
    }
}
