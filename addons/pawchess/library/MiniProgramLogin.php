<?php
namespace addons\pawchess\library;

use addons\third\library\Service as ThirdService;
use addons\third\model\Third;
use app\common\library\Auth;
use fast\Http;

/** Reuse FastAdmin's third-party member identity for the WeChat mini game. */
final class MiniProgramLogin
{
    const PLATFORM = 'wechat_minigame';

    public static function login($code, array $config)
    {
        $appId = trim((string)($config['wechat_app_id'] ?? ''));
        $appSecret = trim((string)($config['wechat_app_secret'] ?? ''));
        if (!$appId || !$appSecret) {
            throw new \RuntimeException('微信小游戏 AppID 或 AppSecret 尚未配置');
        }

        $response = Http::get('https://api.weixin.qq.com/sns/jscode2session', [
            'appid' => $appId,
            'secret' => $appSecret,
            'js_code' => $code,
            'grant_type' => 'authorization_code',
        ]);
        $data = (array)json_decode($response, true);
        if (empty($data['openid']) || !empty($data['errcode'])) {
            throw new \RuntimeException('微信登录失败，请重试');
        }

        $connected = ThirdService::connect(self::PLATFORM, [
            'platform' => self::PLATFORM,
            'apptype' => 'minigame',
            'openid' => $data['openid'],
            'unionid' => $data['unionid'] ?? '',
        ], [], (int)($config['login_keeptime'] ?? 0));
        if (!$connected || !Auth::instance()->getUser()) {
            throw new \RuntimeException('创建或登录会员失败');
        }

        // The generic connector does not refresh these fields on an existing binding.
        $binding = Third::get(['platform' => self::PLATFORM, 'openid' => $data['openid']]);
        if ($binding) {
            $update = ['apptype' => 'minigame', 'logintime' => time()];
            if (!empty($data['unionid'])) {
                $update['unionid'] = $data['unionid'];
            }
            $binding->save($update);
        }

        return ['user' => Auth::instance()->getUser(), 'token' => Auth::instance()->getToken(), 'openid' => $data['openid']];
    }
}
