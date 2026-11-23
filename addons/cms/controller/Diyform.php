<?php

namespace addons\cms\controller;

use addons\cms\library\Service;
use addons\cms\model\Diydata;
use addons\cms\model\Diyform as DiyformModel;
use addons\cms\model\Fields;
use addons\cms\model\SpiderLog;
use think\Config;
use think\Exception;
use think\Hook;
use think\Request;

/**
 * 自定义表单控制器
 * Class Diyform
 * @package addons\cms\controller
 */
class Diyform extends Base
{

    protected $diyform = null;

    public function _initialize()
    {
        parent::_initialize();

        $diyname = $this->request->param('diyname');
        if ($diyname && !is_numeric($diyname)) {
            $diyform = DiyformModel::getByDiyname($diyname);
        } else {
            $id = $diyname ?: $this->request->get('id', '');
            $diyform = DiyformModel::get($id);
        }
        if (!$diyform || $diyform['status'] != 'normal') {
            $this->error(__('表单未找到'), null, '', 3, ['status' => '404 Not Found']);
        }
        $this->diyform = $diyform;
        $this->view->assign("__DIYFORM__", $diyform);
    }

    public function __construct(?Request $request = null)
    {
        parent::__construct($request);

        $channel = \addons\cms\model\Channel::getChannelByLinktype('diyform', $this->diyform->id);
        $this->view->assign("__CHANNEL__", $channel);
    }

    /**
     * 数据列表
     * @return string
     */
    public function index()
    {

        // 判断是否跳转移动端
        $this->checkredirect('diyform/list', ['diyname' => $this->diyform->diyname]);

        $diyform = $this->diyform;
        if (!$diyform['isguest'] && !$this->auth->isLogin()) {
            $this->error("请登录后再操作", "index/user/login");
        }
        $config = get_addon_config('cms');

        $filter = $this->request->get('filter/a', []);
        $orderby = $this->request->get('orderby', '');
        $orderway = $this->request->get('orderway', '');
        $multiple = $this->request->get('multiple/d', 0);

        $orderway = $orderway && in_array(strtolower($orderway), ['asc', 'desc']) ? $orderway : 'desc';

        $params = [];
        $filter = $this->request->get();
        $filter = array_diff_key($filter, array_flip(['orderby', 'orderway', 'page', 'multiple']));
        if (isset($filter['filter'])) {
            $filter = array_merge($filter, $filter['filter']);
            unset($filter['filter']);
        }
        if ($filter) {
            $filter = array_filter($filter, 'strlen');
            $params['filter'] = $filter;
            $params = $filter;
        }
        if ($orderby) {
            $params['orderby'] = $orderby;
        }
        if ($orderway) {
            $params['orderway'] = $orderway;
        }
        if ($multiple) {
            $params['multiple'] = $multiple;
        }

        $search = $this->request->get('q', '');
        $search = strip_tags($search);
        $search = mb_substr($search, 0, 100);

        if ($search) {
            //禁止搜索过滤域名
            if (preg_match("/\.[a-z]{2,}/i", $search)) {
                $this->error("未找到相关记录");
            }

            //限制搜索字符长度
            if (mb_strlen($search) > 15) {
                $this->error("搜索关键字长度超出限制");
            }

            //限制搜索来源
            $referer = $this->request->server('HTTP_REFERER', '');
            if ($referer && ($config['limitsearch'] ?? true) && !Service::isInternalRequest()) {
                $this->error("请求不允许");
            }

            $params['q'] = $search;
        }

        //默认排序字段
        $orders = [
            ['name' => 'default', 'field' => 'createtime DESC,id DESC', 'title' => __('Default')],
        ];

        //合并特殊筛选字段
        $orders = array_merge($orders, $diyform->getOrderFields());

        //获取过滤列表
        list($filterList, $filter, $params, $fields, $multiValueFields, $fieldsList) = Service::getFilterList('diyform', $diyform['id'], $filter, $params, $multiple);

        //获取排序列表
        list($orderList, $orderby, $orderway) = Service::getOrderList($orderby, $orderway, $orders, $params, $fieldsList);

        //获取过滤的条件和绑定参数
        list($filterWhere, $filterBind) = Service::getFilterWhereBind($filter, $multiValueFields, $multiple);

        //模板名称
        $template = preg_replace("/\.html\$/i", "", $diyform['listtpl'] ? $diyform['listtpl'] : 'diyform_list');
        $template = $this->request->get("noframe", "0") ? "diyform_noframe" : $template;
        $template = '/' . $template;

        $pagelistParams = Service::getPagelistParams($template);
        //分页大小
        $pagesize = $pagelistParams['pagesize'] ?? 10;
        //过滤条件
        $filterPagelist = function ($query) use ($pagelistParams) {
            if (isset($pagelistParams['condition'])) {
                $query->where($pagelistParams['condition']);
            }
        };

        $auth = $this->auth;
        $model = new Diydata([], $diyform);
        $pageList = $model
            ->where($filterWhere)
            ->bind($filterBind)
            ->where(function ($query) use ($diyform, $auth) {
                //用户过滤模式
                //如果是仅用户自己消息可见
                if ($diyform['usermode'] == 'user') {
                    $query->where('user_id', $auth->id);
                }
            })
            ->where(function ($query) use ($diyform, $auth) {
                //状态过滤模式
                if ($diyform['statusmode'] === 'normal') {
                    if ($auth->id) {
                        $query->whereRaw("user_id='" . intval($auth->id) . "' OR status='normal'");
                    } else {
                        $query->where('status', 'normal');
                    }
                }
            })
            ->where(function ($query) use ($search, $fieldsList) {
                if ($search && $fieldsList) {
                    $fields = [];
                    foreach ($fieldsList as $index => $item) {
                        $fields[] = $item['name'];
                    }
                    $query->where(implode('|', $fields), 'like', '%' . $search . '%');
                }
            })
            ->where($filterPagelist)
            ->order($orderby, $orderway)
            ->paginate($pagesize, $config['pagemode'] == 'simple');

        Service::appendTextAndList('diyform', $diyform->id, $pageList, true);

        $pageList->appends(array_filter($params));
        $this->view->assign("__FILTERLIST__", $filterList);
        $this->view->assign("__ORDERLIST__", $orderList);
        $this->view->assign("__PAGELIST__", $pageList);

        SpiderLog::record('diyform', $diyform['id']);

        //设置TKD
        Config::set('cms.title', $diyform['seotitle'] ?: $diyform['title']);
        Config::set('cms.keywords', $diyform['keywords']);
        Config::set('cms.description', $diyform['description']);
        Config::set('cms.image', isset($diyform['image']) && $diyform['image'] ? cdnurl($diyform['image'], true) : '');

        return $this->view->fetch($template);
    }

    /**
     * 查看详情
     * @return string
     */
    public function show()
    {
        $id = $this->request->param('id/d');

        // 判断是否跳转移动端
        $this->checkredirect('diyform/list', ['diyname' => $this->diyform->diyname, 'id' => $id]);
        $diyform = $this->diyform;

        if (!$diyform['isguest'] && !$this->auth->isLogin()) {
            $this->error("请登录后再操作", "index/user/login");
        }
        $auth = $this->auth;
        $model = new Diydata([], $diyform);

        $diydata = $model
            ->where('id', $id)
            ->where(function ($query) use ($diyform, $auth) {
                //用户过滤模式
                //如果是仅用户自己消息可见
                if ($diyform['usermode'] == 'user') {
                    $query->where('user_id', $auth->id);
                }
            })
            ->where(function ($query) use ($diyform, $auth) {
                //状态过滤模式
                if ($diyform['statusmode'] === 'normal') {
                    if ($auth->id) {
                        $query->whereRaw("user_id='" . intval($auth->id) . "' OR status='normal'");
                    } else {
                        $query->where('status', 'normal');
                    }
                }
            })
            ->find();

        if (!$diydata) {
            $this->error("数据未找到或正在审核");
        }
        $fieldsList = Fields::where('source', 'diyform')->where('source_id', $diyform['id'])
            ->order('weigh desc,id desc')->select();

        Service::appendTextAndList('diyform', $diyform->id, $diydata);

        $this->view->assign('fieldsList', $fieldsList);
        $this->view->assign("__DIYDATA__", $diydata);

        // 语言检测
        $lang = $this->request->langset();
        $lang = preg_match("/^([a-zA-Z\-_]{2,10})\$/i", $lang) ? $lang : 'zh-cn';

        $site = Config::get("site");
        $upload = \app\common\model\Config::upload();
        // 上传信息配置后
        Hook::listen("upload_config_init", $upload);

        // 配置信息
        $config = [
            'site'           => array_intersect_key($site, array_flip(['name', 'cdnurl', 'version', 'timezone', 'languages'])),
            'upload'         => $upload,
            'modulename'     => 'addons',
            'controllername' => 'diyform',
            'actionname'     => 'show',
            'jsname'         => 'diyform/index',
            'moduleurl'      => rtrim(url("/index", '', false), '/'),
            'language'       => $lang
        ];
        $config = array_merge($config, Config::get("view_replace_str"));

        Config::set('upload', array_merge(Config::get('upload'), $upload));
        // 配置信息后
        Hook::listen("config_init", $config);

        $this->view->assign('diydata', $diydata);
        $this->view->assign('__DIYDATA__', $diydata);
        $this->view->assign('jsconfig', $config);

        //设置TKD
        Config::set('cms.title', $diyform['name'] . '详情');
        Config::set('cms.keywords', '');
        Config::set('cms.description', '');
        Config::set('cms.image', isset($diyform['image']) && $diyform['image'] ? cdnurl($diyform['image'], true) : '');

        //加载模板
        $template = preg_replace("/\.html\$/i", "", $diyform['showtpl'] ? $diyform['showtpl'] : 'diyform_show');
        return $this->view->fetch('/' . $template);
    }

    /**
     * 自定义表单提交
     */
    public function post()
    {
        $diyform = $this->diyform;
        $id = $this->request->request("id/d");
        $diydata = new Diydata([], $diyform);
        if ($diyform['needlogin'] && !$this->auth->isLogin()) {
            $this->error("请登录后再操作", "index/user/login");
        }
        if ($id) {
            if (!$this->auth->isLogin()) {
                $this->error("请登录后再操作", "index/user/login");
            }
            $diydata = $diydata->find($id);
            if (!$diydata) {
                $this->error("未找到指定数据");
            }
            if ($diydata['user_id'] != $this->auth->id) {
                $this->error("无法进行越权操作");
            }
        }
        if ($this->request->isPost()) {
            $config = get_addon_config('cms');
            $this->token();

            //检测是否开启验证码
            if (isset($diyform['iscaptcha']) && $diyform['iscaptcha']) {
                $captcha = $this->request->post('captcha');
                // 读取点选验证码状态，仅PC端适配
                $clicaptchaInfo = get_addon_info('clicaptcha');
                if ($clicaptchaInfo && $clicaptchaInfo['state']) {
                    $clicaptcha = new \addons\clicaptcha\library\Clicaptcha();
                    if (!$clicaptcha->check($captcha, true, $id)) {
                        $this->error("验证码不正确");
                    };
                } else {
                    if (!captcha_check($captcha)) {
                        $this->error("验证码不正确");
                    }
                }
            }
            $row = $this->request->post('row/a', '', 'trim,xss_clean');
            unset($row['id']);

            $fields = DiyformModel::getDiyformFields($diyform['id']);
            foreach ($fields as $index => $field) {
                if ($field['isrequire'] && (!isset($row[$field['name']]) || $row[$field['name']] == '')) {
                    $this->error("{$field['title']}不能为空！");
                }
            }

            $row['user_id'] = $this->auth->id;
            $diydata['status'] = 'hidden';
            try {
                $diydata->save($row);
            } catch (\Exception $e) {
                $this->error("发生错误:" . $e->getMessage());
            }
            //发送通知
            Service::notice(config('cms.sitename') . '收到新的' . $diyform['name']);

            $noframe = $this->request->get('noframe');
            $redirecturl = $noframe ? $diyform['posturl'] . '?noframe=1' : ($diyform['redirecturl'] ?: $diyform['url']);
            $this->success($diyform['successtips'] ?: '提交成功！', $redirecturl);
        }

        $fields = DiyformModel::getDiyformFields($diyform['id'], $diydata->toArray());
        $data = [
            'fields' => $fields,
            'values' => $diydata->toArray()
        ];
        $diyform['fieldslist'] = $this->fetch('common/fields', $data);

        // 语言检测
        $lang = $this->request->langset();
        $lang = preg_match("/^([a-zA-Z\-_]{2,10})\$/i", $lang) ? $lang : 'zh-cn';

        $site = Config::get("site");
        $upload = \app\common\model\Config::upload();
        // 上传信息配置后
        Hook::listen("upload_config_init", $upload);

        // 配置信息
        $config = [
            'site'           => array_intersect_key($site, array_flip(['name', 'cdnurl', 'version', 'timezone', 'languages'])),
            'upload'         => $upload,
            'modulename'     => 'addons',
            'controllername' => 'diyform',
            'actionname'     => 'index',
            'jsname'         => 'diyform/index',
            'moduleurl'      => rtrim(url("/index", '', false), '/'),
            'language'       => $lang
        ];
        $config = array_merge($config, Config::get("view_replace_str"));

        Config::set('upload', array_merge(Config::get('upload'), $upload));
        // 配置信息后
        Hook::listen("config_init", $config);

        $this->view->assign('diydata', $diydata);
        $this->view->assign('__DIYDATA__', $diydata);
        $this->view->assign('jsconfig', $config);

        //设置TKD
        Config::set('cms.title', $diyform['posttitle']);
        Config::set('cms.keywords', '');
        Config::set('cms.description', '');

        $template = preg_replace("/\.html\$/i", "", $diyform['posttpl'] ? $diyform['posttpl'] : 'diyform_post');
        return $this->view->fetch('/' . $template);
    }
}
