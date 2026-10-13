<?php

namespace addons\gamerank\controller;

use addons\gamerank\model\Player;
use addons\gamerank\model\Record;
use addons\gamerank\service\MiniProgramLogin;
use addons\gamerank\service\PlayerService;
use addons\gamerank\service\Text;
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
            $token = $this->auth->getToken();
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }

        // success() 通过抛出 HttpResponseException 结束请求，不能被上面的
        // 登录异常处理捕获，否则会把成功响应误报为登录失败。
        $this->success('', [
            // 不依赖会员字段白名单，明确向小游戏返回本次登录令牌。
            'token'    => $token,
            'userinfo' => $this->auth->getUserinfo(),
            'player'   => $this->playerData($player),
        ]);
    }

    public function leaderboard()
    {
        $config = $this->config();
        $type = $this->request->get('type', 'global');
        $limit = intval($this->request->get('limit', 20));
        $maxLimit = max(1, min(100, intval($config['leaderboard_limit'] ?? 50)));
        $limit = max(1, min($maxLimit, $limit));
        $offset = max(0, intval($this->request->get('offset', 0)));

        if ($type === 'chart') {
            $chart = $this->chartInput();
            $playerTable = (new Player())->getTable();
            $records = Record::alias('record')
                ->join($playerTable . ' player', 'player.user_id = record.user_id')
                ->where('player.rank_visible', 1)
                ->where('record.game_code', $chart['game_code'])
                ->where('record.song_id', $chart['song_id'])
                ->where('record.difficulty', $chart['difficulty'])
                ->field('record.*,player.nickname,player.avatar_url')
                ->order('record.best_score desc,record.best_accuracy desc,record.max_combo desc,record.id asc')
                ->limit($offset, $limit)->select();
            $list = [];
            foreach ($records as $index => $record) {
                $list[] = [
                    'rank'          => $offset + $index + 1,
                    'nickname'      => $record->nickname,
                    'avatar_url'    => $record->avatar_url,
                    'score'         => intval($record->best_score),
                    'total_score'   => intval($record->total_score),
                    'accuracy'      => intval($record->best_accuracy),
                    'max_combo'     => intval($record->max_combo),
                ];
            }
            $total = Record::alias('record')->join($playerTable . ' player', 'player.user_id = record.user_id')
                ->where('player.rank_visible', 1)->where($chart)->count();
            $mine = null;
            if ($this->auth->isLogin()) {
                $player = PlayerService::ensurePlayer($this->auth->getUser());
                $mineRecord = PlayerService::findRecord($this->auth->id, $chart);
                if ($mineRecord) {
                    $mine = $this->recordData($mineRecord);
                } elseif ($total === 0 && $offset === 0 && $player->rank_visible) {
                    // 首次进入某个曲目难度时，也让当前玩家作为零分第 1 名展示，
                    // 避免已登录却看到“0 位玩家”的空榜体验。
                    $mine = [
                        'game_code'  => $chart['game_code'],
                        'song_id'    => $chart['song_id'],
                        'difficulty' => $chart['difficulty'],
                        'score'      => 0,
                        'total_score'=> 0,
                        'accuracy'   => 0,
                        'max_combo'  => 0,
                        'play_count' => 0,
                        'last_score' => 0,
                        'rank'       => 1,
                    ];
                    if (count($list) < $limit) {
                        $list[] = [
                            'rank'      => 1,
                            'nickname'  => $player->nickname,
                            'avatar_url'=> $player->avatar_url,
                            'score'     => 0,
                            'total_score'=> 0,
                            'accuracy'  => 0,
                            'max_combo' => 0,
                        ];
                    }
                    $total = 1;
                }
            }
            $this->success('', [
                'type'  => 'chart',
                'chart' => $chart,
                'list'  => $list,
                'total' => $total,
                'mine'  => $mine,
            ]);
        }
        if ($type !== 'global') {
            $this->error('排行榜类型无效');
        }
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
            'type'  => 'global',
            'list'  => $list,
            'total' => Player::where('rank_visible', 1)->count(),
        ]);
    }

    public function me()
    {
        $player = PlayerService::ensurePlayer($this->auth->getUser());
        $data = ['player' => $this->playerData($player)];
        if ($this->request->get('type') === 'chart') {
            $record = PlayerService::findRecord($player->user_id, $this->chartInput());
            $data['record'] = $record ? $this->recordData($record) : null;
        }
        $this->success('', $data);
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
            if (!$nickname || Text::length($nickname) > 32) {
                $this->error('昵称长度应为 1 至 32 个字符');
            }
            $data['nickname'] = $nickname;
        }
        if ($this->request->has('avatar_url', 'post')) {
            $avatarUrl = trim($this->request->post('avatar_url', ''));
            if ($avatarUrl && !filter_var($avatarUrl, FILTER_VALIDATE_URL)) {
                $this->error('头像地址无效');
            }
            $data['avatar_url'] = Text::slice($avatarUrl, 0, 255);
        }
        if ($this->request->has('rank_visible', 'post')) {
            $data['rank_visible'] = $this->request->post('rank_visible') ? 1 : 0;
        }
        if (!$data) {
            $this->error('没有可更新的资料');
        }
        $player->save($data);
        $this->success('', ['player' => $this->playerData(Player::get($player->id))]);
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
        if (filter_var($points, FILTER_VALIDATE_INT) === false || intval($points) < 0) {
            $this->error('积分必须是非负整数');
        }
        $maxPoints = max(1, intval($this->config()['max_points_per_settlement'] ?? 500));
        if (intval($points) > $maxPoints) {
            $this->error('单局积分超过上限');
        }
        $chart = $this->chartInput();
        $score = $this->request->post('score', null);
        $accuracy = $this->request->post('accuracy', null);
        $maxCombo = $this->request->post('max_combo', null);
        if (filter_var($score, FILTER_VALIDATE_INT) === false || intval($score) < 0 || intval($score) > 10000000) {
            $this->error('成绩无效');
        }
        if (filter_var($accuracy, FILTER_VALIDATE_INT) === false || intval($accuracy) < 0 || intval($accuracy) > 10000) {
            $this->error('准确率无效');
        }
        if (filter_var($maxCombo, FILTER_VALIDATE_INT) === false || intval($maxCombo) < 0 || intval($maxCombo) > 100000) {
            $this->error('最高连击无效');
        }
        $chart['score'] = intval($score);
        $chart['accuracy'] = intval($accuracy);
        $chart['max_combo'] = intval($maxCombo);
        $remark = mb_substr(trim($this->request->post('remark', '')), 0, 255);
        try {
            $player = PlayerService::ensurePlayer($this->auth->getUser());
            $result = PlayerService::settle($player->user_id, intval($points), $roundId, $chart, $remark);
            $player = Player::get($player->id);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }

        // 与 login() 一样，success() 会抛出 HttpResponseException 结束请求；
        // 必须位于登录/结算异常捕获之外，避免已提交的数据被客户端误判为失败。
        $this->success('', [
            'duplicated' => $result['duplicated'],
            'player'     => $this->playerData($player),
            'record'     => $this->recordData($result['record']),
        ]);
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

    protected function chartInput()
    {
        $chart = [
            'game_code'  => trim($this->request->param('game_code', 'paw_beat')),
            'song_id'    => trim($this->request->param('song_id', '')),
            'difficulty' => trim($this->request->param('difficulty', '')),
        ];
        foreach ($chart as $value) {
            if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $value)) {
                $this->error('曲目或难度标识无效');
            }
        }
        return $chart;
    }

    protected function recordData(Record $record)
    {
        return [
            'game_code'     => $record->game_code,
            'song_id'       => $record->song_id,
            'difficulty'    => $record->difficulty,
            'score'         => intval($record->best_score),
            'total_score'   => intval($record->total_score),
            'accuracy'      => intval($record->best_accuracy),
            'max_combo'     => intval($record->max_combo),
            'play_count'    => intval($record->play_count),
            'last_score'    => intval($record->last_score),
            'rank'          => PlayerService::recordRank($record),
        ];
    }
}
