<?php
namespace app\admin\controller\checkin;
use app\common\controller\Backend;
use think\Db;

class Promotion extends Backend
{
    public function index()
    {
        $this->view->assign('promotions', Db::name('checkin_promotion')->order('weigh asc,id desc')->select());
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
        if ($title === '' || mb_strlen($title, 'UTF-8') > 40 || preg_match('/[\x00-\x1f\x7f]/', $title)) $this->fieldError('title', '名称须为 1 至 40 个字符');
        if (mb_strlen($description, 'UTF-8') > 80 || preg_match('/[\x00-\x1f\x7f]/', $description)) $this->fieldError('description', '简介不能超过 80 个字符');
        if (!ctype_digit($weigh) || (int)$weigh > 1000000) $this->fieldError('weigh', '排序须为 0 至 1000000 的整数');
        if (strlen($target) > 1000 || preg_match('/[\x00-\x20\x7f]/', $target)) $this->fieldError('target', '跳转地址无效');
        if ($type === 'web') {
            $url = parse_url($target);
            if (!$url || ($url['scheme'] ?? '') !== 'https' || empty($url['host']) || isset($url['user']) || isset($url['pass'])) $this->fieldError('target', '网页链接须为 HTTPS 地址');
        } elseif ($type === 'wechat') {
            if (!preg_match('#^weixin://dl/business/\?(?:t=[A-Za-z0-9_-]+|appid=wx[0-9a-fA-F]{16}(?:&(?:path|query|env_version)=[A-Za-z0-9._~%+/-]*)*)$#D', $target)) $this->fieldError('target', '请输入有效的微信小程序 URL Scheme');
        } elseif ($type === 'app') {
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*(\.[A-Za-z][A-Za-z0-9_]*)+$/D', $target)) $this->fieldError('target', '请输入有效的 Android 包名');
        } else $this->fieldError('target_type', '跳转类型无效');
        $data = ['title'=>$title, 'description'=>$description, 'target_type'=>$type, 'target'=>$target,
            'weigh'=>(int)$weigh, 'enabled'=>(int)$this->request->post('enabled', 0) === 1 ? 1 : 0, 'updatetime'=>time()];
        if ($id) {
            if (!Db::name('checkin_promotion')->where('id', $id)->find()) $this->error('推广项目不存在');
            Db::name('checkin_promotion')->where('id', $id)->update($data);
        } else { $data['createtime'] = time(); Db::name('checkin_promotion')->insert($data); }
        $this->success('保存成功');
    }
}
