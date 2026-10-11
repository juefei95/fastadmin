<?php

namespace addons\gamerank\controller;

use addons\gamerank\model\Player;
use addons\gamerank\service\MiniProgramLogin;
use addons\gamerank\service\PlayerService;
use app\common\controller\Api as CommonApi;

class Api extends CommonApi
{
    protected $noNeedLogin = ['login', 'leaderboard'];
    protected $noNeedRight = '*';

    protected function config()
    {
        return get_addon_config('gamerank');
    }

    public function login()
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $code = trim($this->request->post('code', ''));
        if (!$code || strlen($code) > 256) {
            $this->error('缺少或无效的微信登录凭据');
        }

        try {
            $user = MiniProgramLogin::login($code, $this->config());
            $player = PlayerService::ensurePlayer($user);
            $this->success('', [
                'userinfo' => $this->auth->getUserinfo(),
                'player'   => $this->playerData($player),
            ]);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    public function leaderboard()
    {
        $config = $this->config();
        $limit = intval($this->request->get('limit', 20));
        $maxLimit = max(1, min(100, intval($config['leaderboard_limit'] ?? 50)));
        $limit = max(1, min($maxLimit, $limit));
        $offset = max(0, intval($this->request->get('offset', 0)));

        $players = Player::where('rank_visible', 1)
            ->order('total_points desc,points_updated_at asc,id asc')
            ->limit($offset, $limit)
            ->select();
        $list = [];
        foreach ($players as $index => $player) {
            $list[] = [
                'rank'         => $offset + $index + 1,
                'nickname'     => $player->nickname,
                'avatar_url'   => $player->avatar_url,
                'total_points' => intval($player->total_points),
            ];
        }
        $this->success('', [
            'list'  => $list,
            'total' => Player::where('rank_visible', 1)->count(),
        ]);
    }

    public function me()
    {
        $player = PlayerService::ensurePlayer($this->auth->getUser());
        $this->success('', ['player' => $this->playerData($player)]);
    }

    public function profile()
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $player = PlayerService::ensurePlayer($this->auth->getUser());
        $data = [];
        if ($this->request->has('nickname', 'post')) {
            $nickname = trim($this->request->post('nickname', ''));
            if (!$nickname || mb_strlen($nickname) > 32) {
                $this->error('昵称长度应为 1 至 32 个字符');
            }
            $data['nickname'] = $nickname;
        }
        if ($this->request->has('avatar_url', 'post')) {
            $avatarUrl = trim($this->request->post('avatar_url', ''));
            if ($avatarUrl && !filter_var($avatarUrl, FILTER_VALIDATE_URL)) {
                $this->error('头像地址无效');
            }
            $data['avatar_url'] = mb_substr($avatarUrl, 0, 255);
        }
        if ($this->request->has('rank_visible', 'post')) {
            $data['rank_visible'] = $this->request->post('rank_visible') ? 1 : 0;
        }
        if (!$data) {
            $this->error('没有可更新的资料');
        }
        $player->save($data);
        $this->success('', ['player' => $this->playerData($player->refresh())]);
    }

    public function settle()
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $roundId = trim($this->request->post('round_id', ''));
        $points = $this->request->post('points', null);
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $roundId)) {
            $this->error('局次标识无效');
        }
        if (filter_var($points, FILTER_VALIDATE_INT) === false || intval($points) < 1) {
            $this->error('积分必须是正整数');
        }
        $maxPoints = max(1, intval($this->config()['max_points_per_settlement'] ?? 500));
        if (intval($points) > $maxPoints) {
            $this->error('单局积分超过上限');
        }
        $remark = mb_substr(trim($this->request->post('remark', '')), 0, 255);
        try {
            $player = PlayerService::ensurePlayer($this->auth->getUser());
            $result = PlayerService::settle($player->user_id, intval($points), $roundId, $remark);
            $player = Player::get($player->id);
            $this->success('', [
                'duplicated' => $result['duplicated'],
                'player'     => $this->playerData($player),
            ]);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    protected function playerData(Player $player)
    {
        return [
            'nickname'     => $player->nickname,
            'avatar_url'   => $player->avatar_url,
            'total_points' => intval($player->total_points),
            'rank_visible' => boolval($player->rank_visible),
            'rank'         => PlayerService::rank($player),
        ];
    }
}
