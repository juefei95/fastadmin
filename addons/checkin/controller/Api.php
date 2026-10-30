<?php
namespace addons\checkin\controller;

use addons\wallet\library\Wallet;
use addons\third\library\Service;
use addons\third\model\Third;
use app\common\controller\Api as BaseApi;
use fast\Http;
use think\Config;
use think\Db;

class Api extends BaseApi
{
    protected $noNeedLogin = ['login', 'rewardconfig'];
    protected $noNeedRight = '*';

    public function login()
    {
        $code = trim((string)$this->request->post('code', ''));
        if (!$this->request->isPost() || !$code) $this->error('缺少微信登录 code');
        $pluginConfig = get_addon_config('checkin') ?: [];
        $appid = trim((string)(!empty($pluginConfig['wxapp_appid']) ? $pluginConfig['wxapp_appid'] : Config::get('site.wxapp_appid')));
        $secret = trim((string)(!empty($pluginConfig['wxapp_secret']) ? $pluginConfig['wxapp_secret'] : Config::get('site.wxapp_secret')));
        if (!$appid || !$secret) $this->error('微信小程序配置缺失');
        $response = Http::get('https://api.weixin.qq.com/sns/jscode2session', [
            'appid' => $appid, 'secret' => $secret, 'js_code' => $code,
            'grant_type' => 'authorization_code'
        ]);
        $session = json_decode($response, true);
        if (!is_array($session) || !empty($session['errcode']) || empty($session['openid'])) {
            $this->error('微信登录失败');
        }
        $openid = (string)$session['openid'];
        $third = Third::where('platform', 'wechat')->where('openid', $openid)->find();
        if ($third && $third->apptype !== 'miniapp') $this->error('微信身份已绑定其他应用');
        if ($third) {
            $user = \app\common\model\User::get($third->user_id);
            if (!$user || $user->status !== 'normal') $this->error('账号不可用');
            $ok = $this->auth->direct($user->id);
        } else {
            // Service creates a FastAdmin user and its fa_third binding for a new miniapp OpenID.
            // Do not pass unionid here: Service otherwise reuses an unrelated WeChat app's binding.
            $ok = Service::connect('wechat', ['openid' => $openid, 'apptype' => 'miniapp']);
        }
        if (!$ok) $this->error('微信登录失败');
        $userinfo = $this->auth->getUserinfo();
        $userinfo['score'] = Wallet::balance($this->auth->id);
        $this->success('登录成功', ['userinfo' => $userinfo]);
    }

    public function wallet()
    {
        $this->success('', ['score' => Wallet::balance($this->auth->id)]);
    }

    private function getRewardConfig()
    {
        $config = get_addon_config('checkin') ?: [];
        $defaults = ['weekday_reward' => 1, 'weekend_reward' => 2, 'holiday_reward' => 3];
        foreach ($defaults as $key => $default) {
            $value = isset($config[$key]) ? (string)$config[$key] : '';
            $defaults[$key] = ctype_digit($value) && (int)$value > 0 ? (int)$value : $default;
        }
        return $defaults;
    }

    public function rewardconfig()
    {
        $this->success('', $this->getRewardConfig());
    }

    public function checkin()
    {
        if (!$this->request->isPost()) $this->error('请求方法错误');
        $userId = $this->auth->id;
        $date = date('Y-m-d');
        $day = (int)date('N');
        $monthDay = date('m-d');
        $holiday = $monthDay === '01-01' || $monthDay === '05-01' || ($monthDay >= '10-01' && $monthDay <= '10-07');
        $rewards = $this->getRewardConfig();
        $reward = $holiday ? $rewards['holiday_reward'] : ($day >= 6 ? $rewards['weekend_reward'] : $rewards['weekday_reward']);
        // Reward is calculated by the server; a client-supplied amount is never trusted.
        try {
            $score = Db::transaction(function () use ($userId, $date, $reward) {
                Db::name('checkin_daily')->insert(['user_id' => $userId, 'check_date' => $date, 'reward' => $reward, 'createtime' => time()]);
                return Wallet::change($userId, $reward, 'checkin', '每日签到');
            });
        } catch (\Throwable $e) {
            if (Db::name('checkin_daily')->where('user_id', $userId)->where('check_date', $date)->find()) $this->error('今日已签到');
            $this->error('签到失败');
        }
        $this->success('签到成功', ['score' => $score, 'reward' => $reward]);
    }

    public function virtuallocation()
    {
        if (!$this->request->isPost()) $this->error('请求方法错误');
        $userId = $this->auth->id;
        try {
            $result = Db::transaction(function () use ($userId) {
                Db::name('wallet_account')->where('user_id', $userId)->where('currency', 'coin')->lock(true)->find();
                $recent = Db::name('wallet_ledger')->where('user_id', $userId)->where('currency', 'coin')->where('type', 'virtual_location')->where('createtime', '>=', time() - 10800)->order('id', 'desc')->find();
                if ($recent) return ['score' => Wallet::balance($userId), 'deducted' => 0];
                return ['score' => Wallet::change($userId, -2, 'virtual_location', '虚拟定位'), 'deducted' => 2];
            });
        } catch (\Throwable $e) { $this->error($e->getMessage() === '余额不足' ? '金币不足' : '扣费失败'); }
        $this->success('操作成功', $result);
    }

}
