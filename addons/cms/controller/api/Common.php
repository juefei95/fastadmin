<?php

namespace addons\cms\controller\api;

use addons\cms\library\Theme;
use addons\cms\model\Block;
use addons\cms\model\Channel;
use fast\Http;
use think\Config;
use think\exception\HttpResponseException;
use think\Hook;
use think\captcha\Captcha;
use think\Log;

/**
 * 公共
 */
class Common extends Base
{
    protected $noNeedLogin = '*';

    /**
     * 初始化
     */
    public function init()
    {
        //焦点图
        $bannerList = [];
        $list = Block::getBlockList(['name' => 'uniappfocus', 'row' => 5]);
        foreach ($list as $index => $item) {
            $bannerList[] = ['image' => cdnurl($item['image'], true), 'url' => $item['url'], 'title' => $item['title']];
        }

        //配置信息
        $upload = Config::get('upload');

        //如果非服务端中转模式需要修改为中转
        if ($upload['storage'] != 'local' && isset($upload['uploadmode']) && $upload['uploadmode'] != 'server') {
            //临时修改上传模式为服务端中转
            set_addon_config($upload['storage'], ["uploadmode" => "server"], false);

            $upload = \app\common\model\Config::upload();
            // 上传信息配置后
            Hook::listen("upload_config_init", $upload);

            $upload = Config::set('upload', array_merge(Config::get('upload'), $upload));
        }

        $upload['cdnurl'] = $upload['cdnurl'] ?: cdnurl('', true);
        //上传地址强制切换为使用本地上传，云存储插件会自动处理
        $upload['uploadurl'] = url('/api/common/upload', '', false, true);

        //支付列表和默认支付方式
        $paytypearr = array_filter(explode(',', Config::get('cms.paytypelist')));
        $defaultPaytype = Config::get('cms.defaultpaytype');
        $defaultPaytype = in_array($defaultPaytype, $paytypearr) ? $defaultPaytype : reset($paytypearr);

        //登录类型列表
        $logintypearr = array_filter(explode(',', Config::get('cms.logintypelist')));
        $config = [
            'upload'         => $upload,
            //登录类型列表
            'logintypearr'   => $logintypearr,
            'paytypelist'    => implode(',', $paytypearr),
            'defaultpaytype' => $defaultPaytype,
            'sitename'       => Config::get('cms.sitename'),
            'sitelogo'       => cdnurl(Config::get('cms.sitelogo'), true),
        ];

        $data = array_merge($config, [
            'bannerList' => $bannerList,
            '__token__'  => $this->request->token()
        ]);

        //合并主题样式，判断是否预览模式
        $isPreview = stripos($this->request->SERVER("HTTP_REFERER"), "mode=preview") !== false;
        $themeConfig = $isPreview && \think\Session::get("previewtheme") ? \think\Session::get("previewtheme") : Theme::get();
        $themeConfig = Theme::render($themeConfig);
        $data = array_merge($data, $themeConfig);

        $this->success('', $data);
    }

    /**
     * 获取分类
     */
    public function getCategory()
    {
        $model_id = $this->request->param('model');
        $parent_id = $this->request->param('channel');
        $menu_index = (int)$this->request->param('menu_index/d', 0);
        //对于首页，应用初始化，app原生导航,无法给传参，默认传-1，取导航的设置参数
        if ($model_id == -1) {
            $model_id = Theme::getFirstParam('model', $menu_index);
        }
        if ($parent_id == -1) {
            $parent_id = Theme::getFirstParam('channel', $menu_index);
        }
        $tabList = [['id' => 0, 'title' => '全部']];
        $channelList = Channel::where('status', 'normal')
            ->where("FIND_IN_SET('recommend', flag)")
            ->where('type', 'in', ['list'])
            ->where(function ($query) use ($model_id, $parent_id) {
                if (!empty($model_id)) {
                    $query->where('model_id', $model_id);
                }
                if (!empty($parent_id)) {
                    $query->where('parent_id', $parent_id);
                }
            })
            ->field('id,parent_id,model_id,name,diyname')
            ->order('weigh desc,id desc')
            ->select();

        foreach ($channelList as $index => $item) {
            $data = ['id' => $item['id'], 'title' => $item['name']];
            $tabList[] = $data;
        }
        $this->success('', $tabList);
    }

    /**
     * 获取关联数据
     */
    public function selectpage()
    {
        $dispatch = $this->request->dispatch();
        $method = ['\think\addons\Route', 'execute'];
        $vars = array_merge($this->request->param(), $dispatch['var'], ['controller' => 'ajax', 'action' => 'selectpage']);
        return \think\App::invokeMethod($method, $vars);
    }

    /**
     * 获取验证码
     */
    public function captcha()
    {
        $ident = $this->request->param('ident');
        $captcha = new Captcha((array)Config::get('captcha'));
        $res = $captcha->entry($ident)->getContent();
        $this->success('获取成功', 'data:image/png;base64,' . base64_encode($res));
    }


    /**
     * 获取小程序码
     */
    public function getWxCode()
    {
        if (!$this->request->isPost()) {
            $this->error("请求方式错误");
        }
        $id = $this->request->post('id');
        $model = $this->request->post('model');
        $version = $this->request->post('version', 'release');
        if (empty($id)) {
            $this->error('参数错误');
        }
        $user_id = $this->auth->isLogin() ? $this->auth->id : '';
        $resource = '';
        $fileStream = (new \addons\cms\library\Wechat\Service)->getWxCodeUnlimited([
            'scene'       => "invite_id={$user_id}&id={$id}",
            'env_version' => $version, //要打开的小程序版本。正式版为 release，体验版为 trial，开发版为 develop
            'page'        => $model ? 'pages/article/detail' : 'pages/product/detail'
        ]);
        if (is_null(json_decode($fileStream))) {
            try {
                $img = imagecreatefromstring($fileStream);
                ob_start();
                imagepng($img);
                $resource = ob_get_clean();
            } catch (\Exception $e) {
                Log::write($e->getMessage());
                $this->error("获取微信二维码失败！");
            }
        } else {
            $config = get_addon_config('cms');
            if ($config['wxapp']) {
                $localFile = ROOT_PATH . 'public' . $config['wxapp'];
                if (is_file($localFile)) {
                    $resource = file_get_contents($localFile);
                } else {
                    $resource = Http::get(cdnurl($config['wxapp'], true));
                }
            }
            if (config('app_debug')) {
                Log::write($fileStream);
            }
        }
        if (!$resource) {
            Log::write($fileStream);
            $this->error("获取二维码失败！");
        }
        $base64_data = base64_encode($resource);
        $base64_file = 'data:image/png;base64,' . $base64_data;
        $this->success('获取成功', $base64_file);
    }
}
