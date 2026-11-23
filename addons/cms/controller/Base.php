<?php

namespace addons\cms\controller;

use addons\cms\library\IntCode;
use addons\cms\library\Service;
use addons\cms\model\SpiderLog;
use think\Config;
use think\Hook;
use think\Request;

/**
 * CMS控制器基类
 */
class Base extends \think\addons\Controller
{

    // 初始化
    public function __construct(?Request $request = null)
    {
        parent::__construct($request);
        $config = get_addon_config('cms');
        // 设定主题模板目录
        $this->view->engine->config('view_path', $this->view->engine->config('view_path') . $config['theme'] . DS);
        // 加载自定义标签库
        //$this->view->engine->config('taglib_pre_load', 'addons\cms\taglib\Cms');
        // 默认渲染栏目为空
        $this->view->assign('__CHANNEL__', null);
        $this->view->assign('isWechat', strpos($this->request->server('HTTP_USER_AGENT'), 'MicroMessenger') !== false);

        // 定义CMS首页的URL
        Config::set('cms.indexurl', addon_url('cms/index/index', [], false));
        // 定义分页类
        Config::set('paginate.type', '\\addons\\cms\\library\\Bootstrap');

        //判断站点状态
        if (isset($config['openedsite']) && !in_array('pc', explode(',', $config['openedsite']))) {
            if ($this->controller != 'order' && $this->action != 'epay') {
                $this->error('站点已关闭');
            }
        }

        //判断文档定时发布
        if (isset($config['publishcheckmode']) && $config['publishcheckmode'] === 'index') {
            Service::checkPublishArchives();
        }

    }

    /**
     * 是否加密ID处理
     */
    protected function hashids($name = 'id')
    {
        $config = get_addon_config('cms');
        $getValue = $this->request->get($name);
        $postValue = $this->request->post($name);
        if ($config['archiveshashids'] && ($getValue || $postValue)) {
            if ($getValue) {
                $getValue = (int)IntCode::decode($getValue);
                $this->request->get([$name => $getValue]);
            }
            if ($postValue) {
                $postValue = (int)IntCode::decode($postValue);
                $this->request->post([$name => $postValue]);
            }
            $this->request->param('');
        }
    }

    // 判断是否跳转移动H5
    protected function checkredirect($type, $params = [])
    {
        $config = get_addon_config('cms');
        //自动跳转H5
        if (($config['autoredirectmobile'] ?? false) && ($config['mobileurl'] ?? '') && $this->request->isMobile()) {
            $pageArr = [
                'archives'         => "/pages/article/detail",
                'channel'          => "/pages/article/article",
                'archives_model_2' => "/pages/product/detail",
                'channel_model_2'  => "/pages/product/product",
                'diyform/list'     => "/pages/diyform/lists",
                'diyform/show'     => "/pages/diyform/detail",
                'special'          => "/pages/special/special",
                'tag'              => "/pages/tag/tag",
                'page'             => "/pages/page/detail",
                'user'             => "/pages/user/user",
            ];
            $pageUrl = '';
            if (isset($params['model'])) {
                $pageUrl = $pageArr[$type . "_model_" . $params['model']] ?? '';
                if ($type == 'archives') {
                    $params['id'] = $params['eid'];
                    unset($params['model'], $params['eid']);
                }
            }
            $pageUrl = $pageUrl ?: ($pageArr[$type] ?? '');
            $params = array_filter($params);
            $this->redirect($config['mobileurl'] . "#" . $pageUrl . ($params ? '?' . http_build_query($params) : ''));
        } else {
            return;
        }
    }

}
