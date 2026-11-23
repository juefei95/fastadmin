<?php

namespace addons\cms\controller\api;

use addons\third\model\Third;
use think\Config;
use think\Validate;

/**
 * 会员
 */
class User extends Base
{
    protected $noNeedLogin = ['getSigned', 'userInfo'];

    public function _initialize()
    {
        parent::_initialize();

        if (!Config::get('fastadmin.usercenter')) {
            $this->error(__('User center already closed'));
        }
    }

    /**
     * 个人中心
     */
    public function index()
    {
        $apptype = $this->request->param('apptype');
        $platform = $this->request->param('platform');
        $logincode = $this->request->param('logincode');

        $info = $this->auth->getUserInfo();
        $info['avatar'] = cdnurl($info['avatar'], true);
        $vip = get_addon_info('vip');
        $info['is_install_vip'] = ($vip && $vip['state']);
        if (!$info['is_install_vip']) { //禁用
            $info['vip'] = 0;
            $info['vipInfo'] = null;
        } else {
            $info['vipInfo'] = \addons\vip\library\Service::getVipInfo($info['id']) ?? null;
            if (empty($info['vipInfo'])) {
                $info['vip'] = 0;
            }
        }
        $signin = get_addon_info('signin');
        $info['is_install_signin'] = ($signin && $signin['state']);

        $firstlogin = $this->auth->jointime === $this->auth->logintime;

        //判断是否显示昵称更新提示
        $profilePrompt = false;
        $config = get_addon_config('cms');
        if ($config['porfilePrompt'] === 'firstlogin') {
            $profilePrompt = $this->auth->jointime === $this->auth->logintime;
        } elseif ($config['porfilePrompt'] === 'everylogin') {
            $profilePrompt = true;
        } elseif ($config['porfilePrompt'] === 'disabled') {
            $profilePrompt = false;
        }
        $showProfilePrompt = false;
        if ($profilePrompt) {
            $showProfilePrompt = !$info['nickname'] || stripos($info['nickname'], '微信用户') !== false || preg_match("/^\d{3}\*{4}\d{4}$/", $info['nickname']);
        }

        $openid = '';
        //如果有传登录code，则获取openid
        if ($logincode) {
            $json = (new \addons\cms\library\Wechat\Service())->getWechatSession($logincode);
            $openid = $json['openid'] ?? '';
        }
        $data['openid'] = $openid;

        $this->success('', [
            'userInfo' => $info,
            'openid'            => $openid,
            'showProfilePrompt' => $showProfilePrompt
        ]);
    }

    //用户发表的文章和评论
    public function userInfo()
    {
        $user_id = $this->request->param('user_id');
        $type = $this->request->param('type');
        $user = \app\common\model\User::field('id,nickname,bio,avatar,status')->where('id', $user_id)->find();
        if (!$user) {
            $this->error("未找到指定会员");
        }
        if ($user['status'] == 'hidden') {
            $this->error("暂时无法浏览");
        }
        $user['avatar'] = cdnurl($user['avatar'], true);
        $pagesize = 10;
        $data = ['user' => $user];
        if ($type == 'archives') {
            $data['list'] = \addons\cms\model\Archives::with([
                'user' => function ($query) {
                    $query->field('id,nickname,avatar');
                },
                'channel'
            ])
                ->where('user_id', $user['id'])
                ->field('id,user_id,title,channel_id,dislikes,likes,tags,createtime,image,images,style,comments,views,diyname')
                ->where('status', 'normal')
                ->order('id', 'desc')
                ->paginate($pagesize);
            foreach ($data['list'] as $index => $datum) {
                $datum['id'] = $datum['eid'];
            }
        } else {
            $commentList = \addons\cms\model\Comment::with([
                'user' => function ($query) {
                    $query->field('id,nickname,avatar');
                },
                'archives'
            ])
                ->where('type', 'archives')
                ->where('user_id', $user['id'])
                ->where('status', 'normal')
                ->order('id', 'desc')
                ->paginate($pagesize);
            foreach ($commentList as $index => $item) {
                $item->create_date = human_date($item->createtime);
                if ($item->archives) {
                    $item->aid = $item->archives->eid;
                    $item->archives->eid = $item->archives->eid;
                }
            }
            $commentList = $commentList->toArray();

            foreach ($commentList['data'] as $index => &$datum) {
                if (isset($datum['archives']) && $datum['archives']) {
                    $datum['archives']['id'] = $datum['archives']['eid'];
                    unset($datum['archives']['channel']);
                }
            }
            $data['list'] = $commentList;
        }
        $data['archives'] = \addons\cms\model\Archives::where('user_id', $user['id'])->where('status', 'normal')->count();
        $data['comments'] = \addons\cms\model\Comment::where('user_id', $user['id'])->where('status', 'normal')->count();
        $this->success('', $data);
    }


    /**
     * 个人资料
     */
    public function profile()
    {
        if (!$this->request->isPost()) {
            $this->error("请求方式错误");
        }
        $user = $this->auth->getUser();
        $username = $this->request->post('username');
        $nickname = $this->request->post('nickname');
        $bio = $this->request->post('bio');
        $avatar = $this->request->post('avatar');
        if (!$username || !$nickname) {
            $this->error("用户名和昵称不能为空");
        }
        if (strlen($bio) > 100) {
            $this->error("签名太长了！");
        }
        $exists = \app\common\model\User::where('username', $username)->where('id', '<>', $this->auth->id)->find();
        if ($exists) {
            $this->error(__('Username already exists'));
        }

        $avatar = str_replace(cdnurl('', true), '', $avatar);

        $user->username = $username;
        $user->nickname = $nickname;
        $user->bio = $bio;
        $user->avatar = $avatar;
        $user->save();

        $this->success('修改成功！');
    }

    /**
     * 保存头像
     */
    public function avatar()
    {
        if (!$this->request->isPost()) {
            $this->error("请求方式错误");
        }
        $user = $this->auth->getUser();
        $avatar = $this->request->post('avatar');
        if (!$avatar) {
            $this->error("头像不能为空");
        }

        $avatar = str_replace(cdnurl('', true), '', $avatar);
        $user->avatar = $avatar;
        $user->save();
        $this->success('修改成功！');
    }

    /**
     * 注销登录
     */
    public function logout()
    {
        $this->auth->logout();
        $this->success(__('Logout successful'), ['__token__' => $this->request->token()]);
    }

    /**
     * 公众号内分享
     */
    public function getSigned()
    {
        $url = $this->request->param('url', '', 'trim');
        $js_sdk = new \addons\cms\library\Jssdk();
        $data = $js_sdk->getSignedPackage($url);
        $this->success('', $data);
    }
}
