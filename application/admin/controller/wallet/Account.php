<?php
namespace app\admin\controller\wallet;

use addons\wallet\library\Wallet as WalletService;
use app\common\controller\Backend;
use think\Db;

class Account extends Backend
{
    public function index()
    {
        $this->view->assign('wallets', Db::name('wallet_account')->alias('w')->join('__USER__ u', 'u.id=w.user_id')->field('w.user_id,w.currency,w.balance,u.username,u.nickname')->order('w.user_id', 'desc')->limit(1000)->select());
        return $this->view->fetch();
    }

    public function ledger()
    {
        $userId = max(0, (int)$this->request->get('user_id', 0));
        $currency = trim((string)$this->request->get('currency', 'coin'));
        $type = trim((string)$this->request->get('type', ''));
        if (!preg_match('/^[a-z][a-z0-9_]{0,15}$/', $currency) || ($type !== '' && !preg_match('/^[a-z][a-z0-9_]{0,31}$/', $type))) {
            $this->error('筛选参数无效');
        }
        $query = Db::name('wallet_ledger')->alias('l')
            ->join('__USER__ u', 'u.id=l.user_id', 'LEFT')
            ->field('l.id,l.user_id,l.currency,l.amount,l.`before`,l.`after`,l.type,l.memo,l.source_type,l.source_id,l.createtime,u.username,u.nickname')
            ->where('l.currency', $currency);
        if ($userId) $query->where('l.user_id', $userId);
        if ($type !== '') $query->where('l.type', $type);
        $page = $query->order('l.id', 'desc')->paginate(30, false, ['query' => ['user_id' => $userId, 'currency' => $currency, 'type' => $type]]);
        $rows = $page->items();
        $typeNames = ['checkin' => '每日打卡', 'virtual_location' => '虚拟定位', 'ad_reward' => '广告奖励', 'admin_adjust' => '管理员调整', 'legacy' => '旧数据', 'purchase' => '购买'];
        $typeColors = ['checkin' => 'success', 'virtual_location' => 'warning', 'ad_reward' => 'success', 'admin_adjust' => 'info', 'legacy' => 'default', 'purchase' => 'primary'];
        foreach ($rows as &$row) {
            $row['time_text'] = date('Y-m-d H:i:s', (int)$row['createtime']);
            $row['type_text'] = $typeNames[$row['type']] ?? $row['type'];
            $row['type_color'] = $typeColors[$row['type']] ?? 'default';
        }
        unset($row);
        $this->view->assign('ledger', $rows);
        $this->view->assign('pager', $page->render());
        $this->view->assign('filters', ['user_id' => $userId ?: '', 'currency' => $currency, 'type' => $type]);
        return $this->view->fetch();
    }

    public function adjust()
    {
        if (!$this->request->isPost()) $this->error('请求方法错误');
        $userId = (int)$this->request->post('user_id');
        $amount = (int)$this->request->post('amount');
        $memo = trim((string)$this->request->post('memo'));
        if ($userId <= 0 || $amount === 0 || strlen($memo) < 2 || strlen($memo) > 100) $this->error('请填写用户、非零调整数量和原因');
        if (!Db::name('user')->where('id', $userId)->find()) $this->error('用户不存在');
        try {
            $balance = Db::transaction(function () use ($userId, $amount, $memo) { return WalletService::change($userId, $amount, 'admin_adjust', $memo); });
        } catch (\Throwable $e) { $this->error('调整失败：' . $e->getMessage()); }
        $this->success('调整成功', null, ['balance' => $balance]);
    }
}
