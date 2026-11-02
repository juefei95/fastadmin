<?php
namespace addons\legacycheckin\controller;

use addons\third\library\Service;
use addons\third\model\Third;
use addons\wallet\library\Wallet;
use app\common\controller\Api as BaseApi;
use app\common\model\User;
use fast\Http;
use think\Db;

class Api extends BaseApi
{
    protected $noNeedLogin = ['wechatregister', 'accountlogin'];
    protected $noNeedRight = '*';

    private function legacyUserId()
    {
        $bound = Third::where('user_id', $this->auth->id)
            ->where('platform', 'wechat_legacy')->where('apptype', 'miniapp')->find();
        if (!$bound) $this->error('请使用旧版小程序登录');
        return (int)$this->auth->id;
    }

    public function index()
    {
        $userId = $this->legacyUserId();
        $this->success('', [
            'username' => (string)$this->auth->getUser()->username,
            'welcome' => (string)$this->auth->getUser()->nickname,
            'score' => Wallet::balance($userId),
        ]);
    }

    public function accountlogin()
    {
        if (!$this->request->isPost()) $this->error('请求方法错误');
        $account = trim((string)$this->request->post('account', ''));
        $password = (string)$this->request->post('password', '');
        if (!$account || !$password) $this->error('请输入用户名和密码');
        if (!$this->auth->login($account, $password)) $this->error($this->auth->getError());
        $this->legacyUserId();
        $info = $this->auth->getUserinfo();
        $info['score'] = Wallet::balance($this->auth->id);
        $this->success('登录成功', ['userinfo' => $info]);
    }

    public function wechatregister()
    {
        if (!$this->request->isPost()) $this->error('请求方法错误');
        $code = trim((string)$this->request->post('code', ''));
        if (!$code) $this->error('缺少微信登录 code');
        $config = get_addon_config('legacycheckin') ?: [];
        $appid = trim((string)($config['wxapp_appid'] ?? ''));
        $secret = trim((string)($config['wxapp_secret'] ?? ''));
        if (!$appid || !$secret) $this->error('旧小程序配置缺失');
        $response = Http::get('https://api.weixin.qq.com/sns/jscode2session', [
            'appid' => $appid, 'secret' => $secret, 'js_code' => $code,
            'grant_type' => 'authorization_code'
        ]);
        $session = json_decode($response, true);
        if (!is_array($session) || !empty($session['errcode']) || empty($session['openid'])) {
            $this->error('微信登录失败');
        }
        $openid = trim((string)$session['openid']);
        $third = Third::where('platform', 'wechat_legacy')->where('openid', $openid)->find();
        if ($third) {
            $user = User::get($third->user_id);
            if (!$user || $user->status !== 'normal') $this->error('账号不可用');
            $ok = $this->auth->direct($user->id);
        } else {
            // A distinct platform keeps the old and new miniapps' identities separate.
            $ok = Service::connect('wechat_legacy', ['openid' => $openid, 'apptype' => 'miniapp']);
        }
        if (!$ok) $this->error('微信登录失败');
        $info = $this->auth->getUserinfo();
        $this->success('登录成功', ['userinfo' => [
            'id' => $info['id'] ?? null,
            'username' => $info['username'] ?? '',
            'nickname' => $info['nickname'] ?? '',
            'avatar' => $info['avatar'] ?? '',
            'token' => $info['token'] ?? '',
            'score' => Wallet::balance($this->auth->id),
            'expires_in' => $info['expires_in'] ?? null,
        ]]);
    }

    public function checkin()
    {
        if (!$this->request->isPost()) $this->error('请求方法错误');
        $userId = $this->legacyUserId();
        $date = date('Y-m-d');
        $reward = (int)$this->request->post('addscorenum', 0);
        if ($reward < 1 || $reward > 10) $this->error('签到奖励无效');
        try {
            $score = Db::transaction(function () use ($userId, $date, $reward) {
                Db::name('legacy_checkin_daily')->insert(['user_id' => $userId, 'check_date' => $date, 'reward' => $reward, 'createtime' => time()]);
                return Wallet::change($userId, $reward, 'legacy_checkin', '每日签到');
            });
        } catch (\Throwable $e) {
            if (Db::name('legacy_checkin_daily')->where('user_id', $userId)->where('check_date', $date)->find()) $this->error('今日已签到');
            $this->error('签到失败');
        }
        $this->success('签到成功', ['score' => $score]);
    }

    public function todayrewards()
    {
        $userId = $this->legacyUserId();
        $start = strtotime('today');
        $checkin = (int)Db::name('legacy_checkin_daily')->where('user_id', $userId)->where('check_date', date('Y-m-d'))->sum('reward');
        $ad = (int)Db::name('wallet_ledger')->where('user_id', $userId)->where('type', 'legacy_ad')->where('createtime', '>=', $start)->sum('amount');
        $share = (int)Db::name('wallet_ledger')->where('user_id', $userId)->where('type', 'legacy_invite')->where('createtime', '>=', $start)->sum('amount');
        $this->success('', ['checkin' => $checkin, 'ad' => $ad, 'share' => $share, 'total' => $checkin + $ad + $share, 'score' => Wallet::balance($userId)]);
    }

    public function adreward()
    {
        if (!$this->request->isPost()) $this->error('请求方法错误');
        $userId = $this->legacyUserId();
        $amount = (int)$this->request->post('addscorenum', 0);
        if ($amount < 1 || $amount > 10) $this->error('广告奖励无效');
        $score = Db::transaction(function () use ($userId, $amount) { return Wallet::change($userId, $amount, 'legacy_ad', '观看视频广告'); });
        $this->success('奖励发放成功', ['score' => $score]);
    }

    public function claiminvitation()
    {
        if (!$this->request->isPost()) $this->error('请求方法错误');
        $visitorId = $this->legacyUserId();
        $inviterId = (int)$this->request->post('inviter_id', 0);
        if ($inviterId <= 0 || $inviterId === $visitorId) $this->error('无效的邀请');
        $awarded = Db::transaction(function () use ($visitorId, $inviterId) {
            if (!User::get($inviterId) || !Third::where('user_id', $inviterId)->where('platform', 'wechat_legacy')->find()) return false;
            Db::name('user')->where('id', $visitorId)->lock(true)->find();
            if (Db::name('wallet_ledger')->where('type', 'legacy_invite')->where('source_type', 'legacy_invite')->where('source_id', $visitorId)->find()) return false;
            Wallet::change($inviterId, 5, 'legacy_invite', '邀请好友:' . $visitorId, 'coin', 'legacy_invite', $visitorId);
            return true;
        });
        $this->success('', ['awarded' => $awarded]);
    }

    public function virtuallocation()
    {
        if (!$this->request->isPost()) $this->error('请求方法错误');
        $userId = $this->legacyUserId();
        try {
            $result = Db::transaction(function () use ($userId) {
                Db::name('wallet_account')->where('user_id', $userId)->where('currency', 'coin')->lock(true)->find();
                $recent = Db::name('wallet_ledger')->where('user_id', $userId)->where('type', 'legacy_virtual')->where('createtime', '>=', time() - 10800)->find();
                if ($recent) return ['score' => Wallet::balance($userId), 'deducted' => 0];
                return ['score' => Wallet::change($userId, -2, 'legacy_virtual', '虚拟定位'), 'deducted' => 2];
            });
        } catch (\Throwable $e) { $this->error('虚拟定位失败：' . ($e->getMessage() === '余额不足' ? '当前金币不足2个，请先获得金币' : '系统异常')); }
        $this->success('虚拟定位成功', $result);
    }

    public function changeusername()
    {
        if (!$this->request->isPost()) $this->error('请求方法错误');
        $this->legacyUserId();
        $username = trim((string)$this->request->post('username', ''));
        if (!preg_match('/^[0-9A-Za-z_]{3,30}$/', $username)) $this->error('用户名不可使用');
        $user = $this->auth->getUser();
        if (User::where('username', $username)->where('id', '<>', $user->id)->find()) $this->error('用户名已存在');
        $user->username = $username;
        $user->save();
        $this->success('修改用户名成功');
    }

    public function changepwdwithoutold()
    {
        if (!$this->request->isPost()) $this->error('请求方法错误');
        $this->legacyUserId();
        $password = (string)$this->request->post('newpassword', '');
        if (strlen($password) < 6 || strlen($password) > 30 || preg_match('/\s/', $password) || $password !== (string)$this->request->post('repassword', '')) $this->error('密码无效或两次密码不一致');
        if (!$this->auth->changepwd($password, '', true)) $this->error($this->auth->getError());
        $this->success('修改密码成功');
    }
}
