<?php

namespace addons\cms\controller;

use addons\cms\library\Service;
use addons\cms\model\Archives;
use addons\cms\model\Channel;
use addons\cms\model\Diydata;
use addons\cms\model\Fields;
use addons\cms\model\Modelx;
use addons\cms\model\Page;
use addons\cms\model\Special;
use think\Config;
use think\Db;
use think\Exception;
use think\exception\PDOException;

/**
 * 数据迁移Api接口控制器
 *
 * 仅限用于数据迁移或内部接口，请勿用于对接外部API接口
 * Class Api
 * @package addons\cms\controller
 */
class Api extends Base
{

    public function _initialize()
    {
        if (!$this->request->isPost()) {
            $this->error("请求方式错误");
        }
        Config::set('default_return_type', 'json');

        $apikey = $this->request->post('apikey');
        $config = get_addon_config('cms');
        if (!$config['apikey']) {
            $this->error('请先在后台配置API密钥');
        }
        if ($config['apikey'] != $apikey) {
            $this->error('密钥不正确');
        }

        parent::_initialize();
    }

    /**
     * 文档数据写入接口
     */
    public function index()
    {
        // API接口默认使用xss_clean进行全局过滤
        $data = $this->request->post('', null, 'trim,xss_clean');
        if (isset($data['user']) && $data['user']) {
            $user = \app\common\model\User::where('nickname', $data['user'])->find();
            if ($user) {
                $data['user_id'] = $user->id;
            }
        }
        //如果有传栏目名称
        if (isset($data['channel']) && $data['channel']) {
            $channel = Channel::where('name', $data['channel'])->find();
            if (!$channel || $channel['status'] != 'normal') {
                $this->error('栏目未找到');
            }
            $data['channel_id'] = $channel->id;
            unset($data['channel']);
        } else {
            $channel_id = $this->request->request('channel_id');
            $channel = Channel::get($channel_id);
            if (!$channel || $channel['status'] != 'normal') {
                $this->error('栏目未找到');
            }
        }
        $model = Modelx::get($channel['model_id']);
        if (!$model) {
            $this->error('模型未找到');
        }
        $data['model_id'] = $model['id'];
        $data['publishtime'] = time();
        $data['createby'] = 'api';
        $data['weigh'] = 0;

        Db::startTrans();
        try {
            //副表数据插入会在模型事件中完成
            $archives = new \app\admin\model\cms\Archives;
            $archives->allowField(true)->save($data);
            Db::commit();
            $data = [
                'id'  => $archives->id,
                'url' => $archives->fullurl
            ];
        } catch (PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('新增成功', '', $data);
    }

    /**
     * 读取文章数据
     */
    public function archives()
    {
        $id = $this->request->request("id/d");
        $archives = Archives::get($id, ['channel']);
        if (!$archives || $archives['status'] != 'normal' || $archives['deletetime']) {
            $this->error("文章未找到");
        }
        $channel = Channel::get($archives['channel_id']);
        if (!$channel) {
            $this->error("栏目未找到");
        }
        $model = Modelx::get($channel['model_id'], [], true);
        if (!$model) {
            $this->error("文章模型未找到");
        }
        $addon = db($model['table'])->where('id', $archives['id'])->find();
        if ($addon) {
            if ($model->fields) {
                Service::appendTextAndList('model', $model->id, $addon);
            }
            $archives->setData($addon);
        } else {
            $this->error('文章副表数据未找到');
        }
        $content = $archives->content;

        //移除分页数据
        $content = str_replace("##pagebreak##", "<br>", $content);
        $archives->content = $content;
        Service::appendTextAndList('archives', 0, $archives);

        $this->success(__('读取成功'), '', $archives->toArray());
    }

    /**
     * 读取文章列表
     */
    public function arclist()
    {
        $params = [];
        $model = (int)$this->request->request('model');
        $channel = (int)$this->request->request('channel');
        $page = (int)$this->request->request('page');
        $pagesize = (int)$this->request->request('pagesize');
        $tags = $this->request->request('tags');
        $flag = $this->request->request('flag');
        $special = (int)$this->request->request('special');
        $pagesize = $pagesize ?: 10;

        if ($model) {
            $params['model'] = $model;
        }
        if ($channel) {
            $channelInfo = Channel::get($channel);
            if (!$channelInfo || $channelInfo['status'] != 'normal') {
                $channel = -1;
            }
            $params['channel'] = $channel;
        }
        $page = max(1, $page);
        $params['limit'] = ($page - 1) * $pagesize . ',' . $pagesize;
        if ($tags) {
            $params['tags'] = $tags;
        }
        if ($flag) {
            $params['flag'] = $flag;
        }
        if ($special) {
            $params['special'] = $special;
        }

        $list = Archives::getArchivesList($params);
        $list = collection($list)->toArray();

        foreach ($list as $index => &$item) {
            $item['url'] = $item['fullurl'];
            unset($item['imglink'], $item['textlink'], $item['channellink'], $item['taglist'], $item['weigh'], $item['status'], $item['deletetime'], $item['memo'], $item['img']);
        }
        $this->success('读取成功', '', $list);
    }

    /**
     * 获取栏目列表
     */
    public function channel()
    {
        $channelList = Channel::where('status', 'normal')
            ->where('type', 'list')
            ->order('weigh DESC,id DESC')
            ->column('id,name');

        Service::appendTextAndList('channel', 0, $channelList, true);
        $this->success(__('读取成功'), '', $channelList);
    }

    /**
     * 评论数据写入接口
     */
    public function comment()
    {
        try {
            $params = $this->request->post();
            $params['status'] = $params['status'] ?? 'hidden';
            $model = new \addons\cms\model\Comment();
            $model->allowField(true)->save($params);
            $archives = $params['type'] == 'archives' ? Archives::get($params['aid']) : ($params['type'] == 'special' ? Special::get($params['aid']) : Page::get($params['aid']));

            //评论状态正常则增加统计
            if ($params['status'] == 'normal') {
                $archives->setInc('comments');
            }
            Db::commit();
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
        $this->success(__('评论成功'), '');
    }

    /**
     * 自定义表单数据写入接口
     */
    public function diyform()
    {
        $id = $this->request->post("diyform_id/d");
        $diyform = \addons\cms\model\Diyform::get($id);
        if (!$diyform || $diyform['status'] != 'normal') {
            $this->error("自定义表单未找到");
        }

        //是否需要登录判断
        if ($diyform['needlogin'] && !$this->auth->isLogin()) {
            $this->error("请登录后再操作", "index/user/login");
        }

        $diydata = new Diydata([], $diyform);

        $data = $this->request->post();
        try {
            $diydata->allowField(true)->save($data);
        } catch (Exception $e) {
            $this->error("数据提交失败");
        }
        $this->success("数据提交成功", $diyform['redirecturl'] ? $diyform['redirecturl'] : addon_url('cms/index/index'));
    }
}
