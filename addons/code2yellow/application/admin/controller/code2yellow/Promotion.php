<?php
namespace app\admin\controller\code2yellow;
use app\common\controller\Backend;
use think\Db;

class Promotion extends Backend
{
    public function index()
    {
        $this->view->assign('promotions', Db::name('code2yellow_promotion')->order('weigh asc,id desc')->select());
        return $this->view->fetch();
    }
    private function fieldError($field, $message)
    {
        $this->error($message, '', ['field' => $field, '__token__' => $this->request->token()]);
    }
    public function save()
    {
        if (!$this->request->isPost()) $this->error('请求方法错误');
        $this->token();
        $id = (int)$this->request->post('id', 0);
        $title = trim((string)$this->request->post('title', ''));
        $description = trim((string)$this->request->post('description', ''));
        $type = (string)$this->request->post('target_type', '');
        $target = trim((string)$this->request->post('target', ''));
        $weigh = (string)$this->request->post('weigh', '0');
        $existing = $id > 0 ? Db::name('code2yellow_promotion')->where('id', $id)->find() : null;
        if ($id > 0 && !$existing) $this->error('推广项目不存在');
        $gameAppid = trim((string)$this->request->post('game_appid', ''));
        $gameSecret = trim((string)$this->request->post('game_secret', ''));
        $gamePath = trim((string)$this->request->post('game_path', '?from=pc'));
        if ($type === 'game' && $gameSecret === '' && $existing && $existing['target_type'] === 'game' && $gameAppid === $existing['game_appid']) $gameSecret = (string)$existing['game_secret'];

        if ($title === '' || mb_strlen($title, 'UTF-8') > 40 || preg_match('/[\x00-\x1f\x7f]/', $title)) $this->fieldError('title', '名称须为 1 至 40 个字符');
        if (mb_strlen($description, 'UTF-8') > 80 || preg_match('/[\x00-\x1f\x7f]/', $description)) $this->fieldError('description', '简介不能超过 80 个字符');
        if (!ctype_digit($weigh) || (int)$weigh > 1000000) $this->fieldError('weigh', '排序须为 0 至 1000000 的整数');
        if (strlen($target) > 1000 || preg_match('/[\x00-\x20\x7f]/', $target)) $this->fieldError('target', '跳转地址无效');
        if ($type === 'game') {
            if (!preg_match('/^wx[0-9a-fA-F]{16}$/D', $gameAppid)) $this->fieldError('game_appid', '请输入有效的小游戏 AppID');
            if (!preg_match('/^[a-zA-Z0-9]{16,128}$/D', $gameSecret)) $this->fieldError('game_secret', '请输入有效的小游戏 AppSecret');
            if (strlen($gamePath) > 128 || !preg_match('/^\?[^\s]+$/D', $gamePath)) $this->fieldError('game_path', '请输入不超过 128 字节的小游戏参数，例如 ?from=pc');
            if ($existing && $existing['target_type'] === 'game' && $existing['game_appid'] === $gameAppid && $existing['game_path'] === $gamePath && $existing['game_secret'] === $gameSecret && is_file(ROOT_PATH . 'public' . $existing['target'])) {
                $target = $existing['target'];
            } else {
                try { $target = (new \addons\code2yellow\library\GameQrService())->generate($gameAppid, $gameSecret, $gamePath); }
                catch (\Throwable $e) { $this->fieldError('game_appid', '小游戏二维码生成失败，请检查凭据、发布状态和网络'); }
            }
        } elseif ($type === 'web') {
            $url = parse_url($target);
            if (!$url || ($url['scheme'] ?? '') !== 'https' || empty($url['host']) || isset($url['user']) || isset($url['pass'])) $this->fieldError('target', '网页链接须为 HTTPS 地址');
        } elseif ($type === 'wechat') {
            $url = parse_url($target);
            $local = preg_match('#^/uploads/[A-Za-z0-9_./%-]+$#D', $target) && strpos($target, '..') === false;
            $remote = $url && strtolower($url['scheme'] ?? '') === 'https' && !empty($url['host']) && !isset($url['user']) && !isset($url['pass']);
            if (!$local && !$remote) $this->fieldError('target', '请上传小程序二维码图片');
        } elseif ($type === 'app') {
            $url = parse_url($target);
            if (!$url || strtolower($url['scheme'] ?? '') !== 'https' || empty($url['host']) || isset($url['user']) || isset($url['pass'])) $this->fieldError('target', '请输入有效的 HTTPS stable 清单地址');
        } else $this->fieldError('target_type', '跳转类型无效');
        $data = ['game_appid'=>$type === 'game' ? $gameAppid : '', 'game_secret'=>$type === 'game' ? $gameSecret : '', 'game_path'=>$type === 'game' ? $gamePath : '', 'title'=>$title, 'description'=>$description, 'target_type'=>$type, 'target'=>$target,
            'weigh'=>(int)$weigh, 'enabled'=>(int)$this->request->post('enabled', 0) === 1 ? 1 : 0, 'updatetime'=>time()];
        if ($id) {
            if (!Db::name('code2yellow_promotion')->where('id', $id)->find()) $this->error('推广项目不存在');
            Db::name('code2yellow_promotion')->where('id', $id)->update($data);
        } else { $data['createtime'] = time(); Db::name('code2yellow_promotion')->insert($data); }
        $this->success('保存成功');
    }
}
