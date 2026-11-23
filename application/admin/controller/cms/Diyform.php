<?php

namespace app\admin\controller\cms;

use app\common\controller\Backend;
use think\exception\HttpResponseException;

/**
 * 自定义表单表
 *
 * @icon fa fa-list
 */
class Diyform extends Backend
{

    /**
     * Model模型对象
     * @var \app\admin\model\cms\Diyform
     */
    protected $model = null;
    protected $searchFields = 'id,name,title,table';
    protected $noNeedRight = ['check_element_available'];

    public function _initialize()
    {
        parent::_initialize();
        $config = get_addon_config('cms');
        if ($config['diyformdatalimit'] != 'all') {
            $this->dataLimit = $config['diyformdatalimit'];
        }

        $this->assignconfig('spiderRecord', intval($config['spiderrecord'] ?? 0));

        $this->model = new \app\admin\model\cms\Diyform;
        $this->view->assign("statusList", $this->model->getStatusList());
    }

    /**
     * 查看
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);


            \app\admin\model\cms\SpiderLog::render($list, 'diyform');

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        if (false === $this->request->isPost()) {
            return $this->view->fetch();
        }

        try {
            parent::add();
        } catch (HttpResponseException $e) {
            $response = $e->getResponse();
            $result = (array)json_decode($response->getContent(), true);
            if (isset($result['code']) && $result['code'] === 1) {
                try {
                    $this->model->createTable();
                } catch (\Exception $e) {
                    \think\Log::write('创建自定义表单发生错误:' . $e->getMessage());
                    $this->error('创建自定义表单表发生错误');
                }
                $this->success();
            } else {
                $response->send();
            }
        }
    }

    /**
     * 检测元素是否可用
     * @internal
     */
    public function check_element_available()
    {
        $id = $this->request->request('id');
        $name = $this->request->request('name');
        $value = $this->request->request('value');
        $name = substr($name, 4, -1);
        if (!$name) {
            $this->error(__('Parameter %s can not be empty', 'name'));
        }
        if ($id) {
            $this->model->where('id', '<>', $id);
        }
        $exist = $this->model->where($name, $value)->find();
        if ($exist) {
            $this->error(__('The data already exist'));
        } else {
            $this->success();
        }
    }
}
