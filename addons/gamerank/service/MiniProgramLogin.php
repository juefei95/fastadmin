<?php

namespace addons\gamerank\service;

use addons\third\library\Service as ThirdService;
use addons\third\model\Third;
use app\common\library\Auth;
use fast\Http;

class MiniProgramLogin
{
    const CODE_TO_SESSION_URL = 'https://api.weixin.qq.com/sns/jscode2session';
    const PLATFORM = 'wechat_minigame';

    public static function login($code, array $config)
    {
        $miniProgram = $config['mini_program'] ?? [];
        $appId = trim((string)($miniProgram['app_id'] ?? ''));
        $appSecret = trim((string)($miniProgram['app_secret'] ?? ''));
        if (!$appId || !$appSecret) {
            throw new \RuntimeException('微信小游戏 AppID 或 AppSecret 尚未配置');
        }

        $response = Http::get(self::CODE_TO_SESSION_URL, [
            'appid'      => $appId,
            'secret'     => $appSecret,
            'js_code'    => $code,
            'grant_type' => 'authorization_code',
        ]);
        $data = (array)json_decode($response, true);
        if (empty($data['openid']) || !empty($data['errcode'])) {
            throw new \RuntimeException('微信登录失败' . (!empty($data['errmsg']) ? '：' . $data['errmsg'] : ''));
        }

        $result = ThirdService::connect(self::PLATFORM, [
            'platform' => self::PLATFORM,
            'apptype'  => 'minigame',
            'openid'   => $data['openid'],
            'unionid'  => $data['unionid'] ?? '',
        ], [], intval($config['login_keeptime'] ?? 0));
        if (!$result) {
            throw new \RuntimeException('创建或登录用户失败');
        }

        // third 的通用 connect 在已绑定账号登录时不会刷新第三方登录时间。
        $binding = Third::get(['platform' => self::PLATFORM, 'openid' => $data['openid']]);
        if ($binding) {
            $update = ['apptype' => 'minigame', 'logintime' => time()];
            if (!empty($data['unionid'])) {
                $update['unionid'] = $data['unionid'];
            }
            $binding->save($update);
        }

        return Auth::instance()->getUser();
    }
}
