<?php

namespace app\admin\controller\cms;

use app\common\controller\Backend;
use think\exception\HttpResponseException;

/**
 * 内容模型表
 *
 * @icon fa fa-th
 */
class Modelx extends Backend
{

    /**
     * Model模型对象
     * @var \app\admin\model\cms\Modelx
     */
    protected $model = null;
    protected $searchFields = 'id,name,table,channeltpl,listtpl,showtpl';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\cms\Modelx;
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
                    \think\Log::write('创建模型发生错误:' . $e->getMessage());
                    $this->error('创建内容模型表发生错误');
                }
                $this->success();
            } else {
                $response->send();
            }
        }
    }

    /**
     * 删除
     * @param mixed $ids
     */
    public function edit($ids = "")
    {
        $channelList = \app\admin\model\cms\Channel::where('model_id', $ids)->select();
        $this->view->assign('channelList', $channelList);
        return parent::edit($ids);
    }

    /**
     * 复制模型
     */
    public function duplicate($ids = "")
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        if ($this->request->isPost()) {
            $table = $this->request->request("table");
            $tableInfo = null;
            try {
                $tableInfo = \think\Db::name($table)->getTableInfo();
            } catch (\Exception $e) {
            }
            if ($tableInfo) {
                $this->error("模型表名已经存在");
            }
            $prefix = config('database.prefix');
            if (preg_match("/^" . $prefix . "/i", $table)) {
                $this->error("请勿添加表前缀:" . $prefix);
            }
            $data = [
                'name'       => $row->getData('name') . '_copy',
                'table'      => $table,
                'fields'     => $row->fields,
                'channeltpl' => $row->channeltpl,
                'listtpl'    => $row->listtpl,
                'showtpl'    => $row->showtpl,
                'setting'    => $row->setting,
            ];
            $modelx = \app\admin\model\cms\Modelx::create($data, true);
            $modelx->createTable();
            $fieldsList = \app\admin\model\cms\Fields::where('source', 'model')->where('source_id', $row['id'])->select();
            foreach ($fieldsList as $index => $item) {
                $data = $item->toArray();
                $data['source_id'] = $modelx->id;
                unset($data['id'], $data['createtime'], $data['updatetime']);
                \app\admin\model\cms\Fields::create($data, true);
            }
            $this->success();
        }
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

}
