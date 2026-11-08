<?php
namespace app\admin\controller\checkin;

use app\common\controller\Backend;
use think\Db;

class Announcement extends Backend
{
    public function index()
    {
        $this->view->assign('announcements', Db::name('checkin_announcement')->order('id desc')->select());
        return $this->view->fetch();
    }

    public function save()
    {
        if (!$this->request->isPost()) $this->error('请求方法错误');
        $this->token();
        $id = (int)$this->request->post('id', 0);
        $title = trim((string)$this->request->post('title', ''));
        $content = trim((string)$this->request->post('content', ''));
        $weigh = (string)$this->request->post('weigh', '0');
        if ($title === '' || mb_strlen($title, 'UTF-8') > 100 || preg_match('/[\x00-\x1f\x7f]/', $title)) $this->error('标题须为 1 至 100 个字符');
        if ($content === '' || mb_strlen($content, 'UTF-8') > 5000 || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $content)) $this->error('正文须为 1 至 5000 个字符');
        if (!ctype_digit($weigh) || (int)$weigh > 1000000) $this->error('排序须为 0 至 1000000 的整数');
        $start = $this->parseTime('starttime');
        $end = $this->parseTime('endtime');
        if ($end && $end <= $start) $this->error('结束时间须晚于开始时间');
        $data = ['title' => $title, 'content' => $content, 'weigh' => (int)$weigh,
            'enabled' => (int)$this->request->post('enabled', 0) === 1 ? 1 : 0,
            'starttime' => $start, 'endtime' => $end, 'updatetime' => time()];
        if ($id) {
            if (!Db::name('checkin_announcement')->where('id', $id)->find()) $this->error('公告不存在');
            Db::name('checkin_announcement')->where('id', $id)->update($data);
        } else {
            $data['createtime'] = time();
            Db::name('checkin_announcement')->insert($data);
        }
        $this->success('保存成功');
    }

    public function reset()
    {
        if (!$this->request->isPost()) $this->error('请求方法错误');
        $this->token();
        $id = (int)$this->request->post('id', 0);
        if ($id < 1 || !Db::name('checkin_announcement')->where('id', $id)->find()) $this->error('公告不存在');
        Db::name('checkin_announcement')->where('id', $id)->setInc('version');
        $this->success('已重新开启弹出');
    }

    private function parseTime($field)
    {
        $value = trim((string)$this->request->post($field, ''));
        if ($value === '') return 0;
        $tz = new \DateTimeZone('Asia/Shanghai');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, $tz);
        if (!$date || $date->format('Y-m-d\TH:i') !== $value) $this->error('时间格式无效');
        return $date->getTimestamp();
    }
}
