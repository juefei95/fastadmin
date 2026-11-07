<?php
namespace addons\code2yellow\controller;

use think\Db;
use app\common\controller\Api;
use addons\wechatqrcodelogin\library\WechatLoginService;

class Index extends Api
{
    protected $noNeedLogin = ['create', 'poll', 'notify', 'updateconfig', 'announcements'];
    protected $noNeedRight = '*';
    const SCENE_OWNER_PREFIX = 'code2yellow_login_scene_';

    public function announcements()
    {
        $version = trim((string)$this->request->get('version', ''));
        $mode = (string)$this->request->get('mode', '');
        if (!preg_match('/^\d+\.\d+\.\d+$/D', $version) || strlen($version) > 32 || !in_array($mode, ['green', 'yellow'], true)) {
            $this->error('版本或模式无效');
        }
        $now = time();
        $rows = Db::name('code2yellow_announcement')->where('status', 'published')
            ->where('mode', 'in', ['all', $mode])
            ->where(function ($query) use ($now) { $query->where('start_at', 0)->whereOr('start_at', '<=', $now); })
            ->where(function ($query) use ($now) { $query->where('end_at', 0)->whereOr('end_at', '>=', $now); })
            ->field('id,title,content,is_pinned,is_urgent,revision,published_at,min_version,max_version')
            ->order('is_pinned desc,published_at desc,id desc')->select();
        $result = [];
        foreach ($rows as $row) {
            if (($row['min_version'] !== '' && version_compare($version, $row['min_version'], '<')) ||
                ($row['max_version'] !== '' && version_compare($version, $row['max_version'], '>'))) continue;
            unset($row['min_version'], $row['max_version']);
            $result[] = $row;
        }
        $this->success('ok', $result);
    }

    public function updateconfig()
    {
        $config = (array)get_addon_config('code2yellow');
        $url = trim((string)($config['update_manifest_url'] ?? ''));
        $parts = parse_url($url);
        if (strlen($url) > 2048 || !filter_var($url, FILTER_VALIDATE_URL) || !$parts
            || strtolower($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass'])) {
            $this->error('请在后台配置有效的 HTTPS 更新清单地址');
        }
        $this->success('ok', ['manifest_url' => $url]);
    }

    public function create()
    {
        try {
            $result = (new WechatLoginService($this->getWechatConfig()))->create($this->request->request('expire_seconds', 300));
            cache(self::SCENE_OWNER_PREFIX . $result['scene'], 1, (int)$result['expires_in'] + 600);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
        }
        $this->success('ok', $result);
    }

    public function poll()
    {
        $scene = trim((string)$this->request->request('scene', ''));
        if (!$scene || !cache(self::SCENE_OWNER_PREFIX . $scene)) {
            $this->error('二维码已过期或不存在');
        }
        try {
            $result = (new WechatLoginService($this->getWechatConfig()))->poll($scene);
            if ($result['status'] === 'logged_in') {
                $this->recordUser($result['userinfo']);
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
        }
        $this->success($result['status'] === 'logged_in' ? '登录成功' : '', $result);
    }

    public function notify()
    {
        try {
            return (new WechatLoginService($this->getWechatConfig()))->notify();
        } catch (\Throwable $e) {
            return 'fail';
        }
    }

    protected function getWechatConfig()
    {
        $addon = (array)get_addon_config('code2yellow');
        $appid = trim((string)($addon['appid'] ?? ''));
        $secret = trim((string)($addon['secret'] ?? ''));
        $token = trim((string)($addon['token'] ?? ''));
        if ($appid === '' && $secret === '' && $token === '' && empty($addon['aes_key'])) {
            return null;
        }
        if ($appid === '' || $secret === '' || $token === '') {
            throw new \RuntimeException('请在 Code2Yellow 插件设置中完整填写公众号 AppID、AppSecret 和 Token');
        }
        $http = ['verify' => (bool)($addon['http_verify'] ?? true), 'timeout' => 30.0];
        $proxy = trim((string)($addon['http_proxy'] ?? ''));
        if ($proxy !== '') {
            $http['proxy'] = $proxy;
        }
        return [
            'app_id' => $appid,
            'secret' => $secret,
            'token' => $token,
            'aes_key' => trim((string)($addon['aes_key'] ?? '')),
            'response_type' => 'array',
            'http' => $http,
            'log' => ['default' => 'error'],
        ];
    }

    protected function recordUser(array $userinfo)
    {
        $userId = (int)($userinfo['id'] ?? 0);
        if (!$userId) {
            throw new \RuntimeException('无法记录登录用户');
        }
        $table = config('database.prefix') . 'code2yellow_user';
        $now = time();
        Db::execute("INSERT INTO `{$table}` (`user_id`, `created_at`, `last_login_at`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `last_login_at` = VALUES(`last_login_at`)", [$userId, $now, $now]);
    }
}
