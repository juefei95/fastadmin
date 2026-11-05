<?php
namespace addons\checkin\controller;

use addons\wallet\library\Wallet;
use addons\checkin\library\SpringFestival;
use addons\checkin\library\RewardService;
use addons\third\library\Service;
use addons\third\model\Third;
use app\common\controller\Api as BaseApi;
use fast\Http;
use think\Config;
use think\Db;
use think\Validate;

class Api extends BaseApi
{
    protected $noNeedLogin = ['login', 'accountlogin', 'rewardconfig', 'rechargepackages'];
    protected $noNeedRight = '*';

    private function miniappUserId()
    {
        if (!Third::where('user_id', $this->auth->id)->where('platform', 'wechat')->where('apptype', 'miniapp')->find()) {
            $this->error('请使用新小程序登录');
        }
        $this->ensureCheckinUser((int)$this->auth->id);
        return (int)$this->auth->id;
    }

    private function ensureCheckinUser($userId)
    {
        Db::execute('INSERT IGNORE INTO `' . config('database.prefix') . 'checkin_user` (`user_id`, `createtime`) VALUES (?, ?)', [$userId, time()]);
    }

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
        $this->ensureCheckinUser((int)$this->auth->id);
        $userinfo = $this->auth->getUserinfo();
        $userinfo['score'] = Wallet::balance($this->auth->id);
        $this->success('登录成功', ['userinfo' => $userinfo]);
    }

    public function accountlogin()
    {
        if (!$this->request->isPost()) $this->error('请求方法错误');
        $account = trim((string)$this->request->post('account', ''));
        $password = (string)$this->request->post('password', '');
        if (!$account || !$password) $this->error('请输入用户名和密码');
        if (!$this->auth->login($account, $password)) $this->error($this->auth->getError());
        if (Third::where('user_id', $this->auth->id)->where('platform', 'wechat')->where('apptype', 'miniapp')->find()) {
            $this->ensureCheckinUser((int)$this->auth->id);
        }
        $userinfo = $this->auth->getUserinfo();
        $userinfo['score'] = Wallet::balance($this->auth->id);
        $this->success('登录成功', ['userinfo' => $userinfo]);
    }

    public function wallet()
    {
        $this->success('', ['score' => Wallet::balance($this->auth->id)]);
    }

    public function rechargepackages()
    {
        $rows = Db::name('checkin_recharge_package')->where('enabled', 1)
            ->field('id,name,description,recommended,coins,price_fen,original_price_fen')->order('weigh asc,id asc')->select();
        $this->success('', ['packages' => $rows]);
    }

    public function createrechargeorder()
    {
        if (!$this->request->isPost()) $this->error('请求方法错误');
        $userId = $this->miniappUserId();
        $packageId = (int)$this->request->post('package_id', 0);
        $config = get_addon_config('checkin') ?: [];
        $epay = get_addon_config('epay') ?: [];
        $wechat = $epay['wechat'] ?? [];
        $appid = trim((string)($config['payment_appid'] ?? ''));
        $notify = trim((string)($config['payment_notify_url'] ?? ''));
        $parts = parse_url($notify);
        if ((string)($config['payment_wechat_enabled'] ?? '0') !== '1') $this->error('微信支付未开放');
        if ($packageId < 1) $this->error('请选择有效套餐');
        if (!preg_match('/^wx[a-zA-Z0-9]{16}$/D', $appid) || empty($wechat['mch_id']) || ($wechat['mode'] ?? 'normal') !== 'normal'
            || !is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || ($parts['path'] ?? '') !== '/addons/checkin/payment/wechatnotify'
            || isset($parts['query']) || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass'])) {
            $this->error('微信支付配置不完整');
        }
        $plugin = get_addon_instance('epay');
        if (!$plugin || !(get_addon_info('epay')['state'] ?? false) || !function_exists('bcmul')) $this->error('支付服务不可用');
        $version = \addons\epay\library\Service::getSdkVersion();
        if (!in_array($version, ['v2', 'v3'], true)) $this->error('支付服务不可用');
        try {
            $order = Db::transaction(function () use ($packageId, $userId, $appid, $wechat) {
                $package = Db::name('checkin_recharge_package')->where('id', $packageId)->lock(true)->find();
                if (!$package || (int)$package['enabled'] !== 1 || (int)$package['price_fen'] < 1 || (int)$package['coins'] < 1) {
                    throw new \InvalidArgumentException('套餐不可用');
                }
                $order = ['order_no' => 'C' . date('YmdHis') . bin2hex(random_bytes(8)), 'user_id' => $userId,
                    'package_id' => $packageId, 'coins' => (int)$package['coins'], 'price_fen' => (int)$package['price_fen'],
                    'pay_type' => 'wechat', 'payment_appid' => $appid, 'merchant_id' => (string)$wechat['mch_id'],
                    'status' => 'pending', 'createtime' => time()];
                Db::name('checkin_recharge_order')->insert($order);
                return $order;
            });
        } catch (\InvalidArgumentException $e) { $this->error($e->getMessage()); }
        catch (\Throwable $e) { \think\Log::record('checkin recharge order creation failed: ' . get_class($e), 'error'); $this->error('订单创建失败，请稍后重试'); }

        try {
            $plugin->epayConfigInit();
            $amount = sprintf('%d.%02d', intdiv($order['price_fen'], 100), $order['price_fen'] % 100);
            $custom = $version === 'v3' ? ['mp_app_id' => $appid] : ['app_id' => $appid];
            $result = \addons\epay\library\Service::submitOrder($amount, $order['order_no'], 'wechat', '打卡金币充值', $notify, null, 'scan', '', $custom);
            if (is_object($result) && method_exists($result, 'all')) $resultData = $result->all();
            elseif (is_object($result) && method_exists($result, 'getContent')) $resultData = json_decode((string)$result->getContent(), true);
            else $resultData = null;
            if (!is_array($resultData)) throw new \RuntimeException('Invalid payment parameters');
            $codeUrl = (string)($resultData['code_url'] ?? '');
            $url = parse_url($codeUrl);
            if (!is_array($url) || ($url['scheme'] ?? '') !== 'weixin' || ($url['host'] ?? '') !== 'wxpay') throw new \RuntimeException('Payment QR unavailable');
        } catch (\Throwable $e) {
            // A timeout may still have created a provider order: keep the local order pending for reconciliation.
            \think\Log::record('checkin recharge payment initiation failed: ' . get_class($e), 'error');
            $this->error('支付下单未完成，请稍后重试', ['order_no' => $order['order_no']]);
        }
        $this->success('订单创建成功', ['order_no' => $order['order_no'], 'coins' => $order['coins'], 'price_fen' => $order['price_fen'], 'code_url' => $codeUrl]);
    }

    public function rechargeorder()
    {
        $orderNo = trim((string)$this->request->get('order_no', ''));
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $orderNo)) $this->error('订单号无效');
        $order = Db::name('checkin_recharge_order')->where('user_id', $this->auth->id)->where('order_no', $orderNo)
            ->field('order_no,coins,price_fen,pay_type,status,createtime,paidtime')->find();
        if (!$order) $this->error('订单不存在');
        $order['score'] = Wallet::balance($this->auth->id);
        $this->success('', $order);
    }

    public function changeusername()
    {
        if (!$this->request->isPost()) $this->error('请求方法错误');
        $username = trim((string)$this->request->post('username', ''));
        if (!preg_match('/^[0-9A-Za-z_]{3,30}$/', $username)) $this->error('用户名不可使用');
        $user = $this->auth->getUser();
        if (\app\common\model\User::where('username', $username)->where('id', '<>', $user->id)->find()) {
            $this->error('用户名已存在');
        }
        if (preg_match('/^1\d{10}$/', $username)) {
            if (\app\common\model\User::where('mobile', $username)->where('id', '<>', $user->id)->find()) {
                $this->error('手机号已存在');
            }
            $user->mobile = $username;
        }
        $user->username = $username;
        $user->save();
        $this->success('修改用户名成功');
    }

    public function changepwdwithoutold()
    {
        if (!$this->request->isPost()) $this->error('请求方法错误');
        $newpassword = (string)$this->request->post('newpassword', '');
        $repassword = (string)$this->request->post('repassword', '');
        if ($newpassword !== $repassword) $this->error('两次密码输入不一致');
        if (!Validate::make()->check(['newpassword' => $newpassword], ['newpassword' => 'require|regex:\S{6,30}'])) {
            $this->error('密码须为6至30位非空白字符');
        }
        if (!$this->auth->changepwd($newpassword, '', true)) $this->error($this->auth->getError());
        $this->success('修改密码成功');
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

    public function checkinstatus()
    {
        $this->miniappUserId();
        $date = date('Y-m-d');
        $record = Db::name('checkin_daily')->where('user_id', $this->auth->id)->where('check_date', $date)->find();
        $this->success('', [
            'date' => $date,
            'checked' => (bool)$record,
            'reward' => $record ? (int)$record['reward'] : 0,
            'score' => Wallet::balance($this->auth->id),
        ]);
    }

    public function checkin()
    {
        if (!$this->request->isPost()) $this->error('请求方法错误');
        $userId = $this->miniappUserId();
        $date = date('Y-m-d');
        $day = (int)date('N');
        $monthDay = date('m-d');
        $holiday = $monthDay === '01-01' || $monthDay === '05-01' || ($monthDay >= '10-01' && $monthDay <= '10-07') || SpringFestival::contains($date);
        $rewards = $this->getRewardConfig();
        $reward = $holiday ? $rewards['holiday_reward'] : ($day >= 6 ? $rewards['weekend_reward'] : $rewards['weekday_reward']);
        // Reward is calculated by the server; a client-supplied amount is never trusted.
        try {
            $score = RewardService::checkin($userId, $reward);
        } catch (\Throwable $e) {
            if (RewardService::checkedToday($userId)) $this->error('今日已签到');
            $this->error('签到失败');
        }
        $this->success('签到成功', ['score' => $score, 'reward' => $reward]);
    }

    public function todayrewards()
    {
        if (!$this->request->isPost()) $this->error('请求方法错误');
        $this->success('', RewardService::todayRewards($this->miniappUserId()));
    }

    public function adreward()
    {
        if (!$this->request->isPost()) $this->error('请求方法错误');
        $userId = $this->miniappUserId();
        try {
            $score = RewardService::adReward($userId, (int)$this->request->post('addscorenum', 0));
        } catch (\InvalidArgumentException $e) { $this->error($e->getMessage()); }
        $this->success('奖励发放成功', ['score' => $score]);
    }

    public function claiminvitation()
    {
        if (!$this->request->isPost()) $this->error('请求方法错误');
        try {
            $awarded = RewardService::claimInvitation($this->miniappUserId(), (int)$this->request->post('inviter_id', 0));
        } catch (\InvalidArgumentException $e) { $this->error($e->getMessage()); }
        $this->success('', ['awarded' => $awarded]);
    }

    public function virtuallocation()
    {
        if (!$this->request->isPost()) $this->error('请求方法错误');
        try {
            $result = RewardService::virtualLocation($this->miniappUserId());
        } catch (\Throwable $e) { $this->error($e->getMessage() === '余额不足' ? '金币不足' : '扣费失败'); }
        $this->success('操作成功', $result);
    }

}
