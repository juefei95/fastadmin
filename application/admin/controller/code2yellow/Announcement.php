<?php
namespace app\admin\controller\code2yellow;

use app\common\controller\Backend;
use think\Db;

class Announcement extends Backend
{
    protected $searchFields = 'title';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\code2yellow\Announcement;
    }

    public function add()
    {
        return $this->saveAnnouncement(null);
    }

    public function edit($ids = null)
    {
        $id = (int)$ids;
        $row = $id > 0 ? Db::name('code2yellow_announcement')->where('id', $id)->find() : null;
        if (!$row) $this->error('公告不存在');
        return $this->saveAnnouncement($row);
    }

    protected function saveAnnouncement($existing)
    {
        if (!$this->request->isPost()) {
            $row = $existing ?: ['title'=>'', 'content'=>'', 'status'=>'draft', 'mode'=>'all', 'min_version'=>'', 'max_version'=>'', 'is_pinned'=>0, 'is_urgent'=>0, 'start_at'=>0, 'end_at'=>0];
            foreach (['start_at', 'end_at'] as $key) $row[$key] = $row[$key] ? date('Y-m-d\TH:i', $row[$key]) : '';
            $this->view->assign('row', $row);
            $this->view->assign('editing', (bool)$existing);
            return $this->view->fetch('code2yellow/announcement/form');
        }
        try {
            $input = $this->request->post('row/a', []);
            $data = $this->validatedData($input);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
        }
        $now = time();
        $data['updated_at'] = $now;
        if ($data['status'] === 'published' && (!$existing || $existing['status'] !== 'published')) $data['published_at'] = $now;
        if ($existing) {
            if (!empty($input['renotify'])) $data['revision'] = Db::raw('revision + 1');
            Db::name('code2yellow_announcement')->where('id', $existing['id'])->update($data);
        } else {
            $data['created_at'] = $now;
            Db::name('code2yellow_announcement')->insert($data);
        }
        $this->success('保存成功');
    }

    protected function validatedData(array $input)
    {
        foreach (['title', 'content', 'status', 'mode', 'min_version', 'max_version', 'start_at', 'end_at', 'is_pinned', 'is_urgent', 'renotify'] as $field) {
            if (isset($input[$field]) && !is_scalar($input[$field])) throw new \InvalidArgumentException('公告字段无效');
        }
        $title = trim((string)($input['title'] ?? ''));
        $content = trim((string)($input['content'] ?? ''));
        if ($title === '' || $content === '' || ($titleLength = preg_match_all('/./us', $title)) === false || $titleLength > 120 || ($contentLength = preg_match_all('/./us', $content)) === false || $contentLength > 10000) throw new \InvalidArgumentException('标题须为 1 至 120 字，正文须为 1 至 10000 字');
        $status = (string)($input['status'] ?? 'draft');
        $mode = (string)($input['mode'] ?? 'all');
        if (!in_array($status, ['draft', 'published', 'disabled'], true) || !in_array($mode, ['all', 'green', 'yellow'], true)) throw new \InvalidArgumentException('状态或模式无效');
        $min = trim((string)($input['min_version'] ?? ''));
        $max = trim((string)($input['max_version'] ?? ''));
        foreach ([$min, $max] as $v) if ($v !== '' && (strlen($v) > 32 || !preg_match('/^\d+\.\d+\.\d+$/D', $v))) throw new \InvalidArgumentException('版本须为数字格式，例如 3.0.2');
        if ($min !== '' && $max !== '' && version_compare($min, $max, '>')) throw new \InvalidArgumentException('最低版本不能大于最高版本');
        $times = [];
        foreach (['start_at', 'end_at'] as $key) {
            $value = trim((string)($input[$key] ?? ''));
            $time = $value === '' ? 0 : strtotime($value);
            if ($time === false || $time < 0 || $time > 4294967295) throw new \InvalidArgumentException('展示时间无效');
            $times[$key] = $time;
        }
        if ($times['start_at'] && $times['end_at'] && $times['start_at'] > $times['end_at']) throw new \InvalidArgumentException('开始时间不能晚于结束时间');
        return ['title'=>$title, 'content'=>$content, 'status'=>$status, 'mode'=>$mode, 'min_version'=>$min, 'max_version'=>$max, 'is_pinned'=>empty($input['is_pinned']) ? 0 : 1, 'is_urgent'=>empty($input['is_urgent']) ? 0 : 1] + $times;
    }

    public function multi($ids = null) { $this->error('请逐条编辑公告'); }
    public function import() { $this->error('不支持导入公告'); }
    public function dragsort() { $this->error('不支持排序'); }
    public function destroy($ids = null) { $this->error('不支持此操作'); }
    public function restore($ids = null) { $this->error('不支持此操作'); }
}
