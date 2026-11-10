<?php
namespace app\admin\controller\checkin;

use app\common\controller\Backend;
use think\Db;
use addons\checkin\library\RechargeQuery;

class Recharge extends Backend
{
    public function index()
    {
        $this->view->assign('packages', Db::name('checkin_recharge_package')->order('weigh asc,id asc')->select());
        return $this->view->fetch();
    }

    public function orders()
    {
        $keyword = trim((string)$this->request->get('keyword', ''));
        $query = Db::name('checkin_recharge_order');
        if ($keyword !== '') {
            if (ctype_digit($keyword) && strlen($keyword) < 10) $query->where('user_id', (int)$keyword);
            else $query->where('order_no', $keyword);
        }
        $page = $query->order('id desc')->paginate(30, false, ['query' => ['keyword' => $keyword]]);
        $this->view->assign('orders', $page->items());
        $this->view->assign('pager', $page->render());
        $this->view->assign('keyword', $keyword);
        return $this->view->fetch();
    }

    public function verifyorder()
    {
        if (!$this->request->isPost()) $this->error('请求方法错误');
        $this->token();
        $orderNo = trim((string)$this->request->post('order_no', ''));
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $orderNo)) $this->error('订单号无效');
        $order = Db::name('checkin_recharge_order')->where('order_no', $orderNo)->find();
        if (!$order) $this->error('订单不存在');
        if ($order['status'] === 'paid') $this->success('订单已支付并到账');
        if ($order['status'] !== 'pending' || $order['pay_type'] !== 'wechat') $this->error('该订单不能查询微信支付');
        try {
            $result = RechargeQuery::reconcile($order);
            $current = Db::name('checkin_recharge_order')->where('order_no', $orderNo)->find();
        } catch (\Throwable $e) {
            \think\Log::record('checkin recharge admin query failed: ' . get_class($e), 'error');
            $this->error('微信查单或核对失败，请检查支付配置和服务器日志');
        }
        if ($current && $current['status'] === 'paid') $this->success('微信支付已核实，金币已到账');
        if ($current && $current['status'] === 'closed') $this->success('微信订单已关闭');
        if ($result === 'throttled') $this->success('查询过于频繁，请稍后重试');
        $this->success('微信尚未确认支付成功，订单保持待支付');
    }

    public function closeorder()
    {
        if (!$this->request->isPost()) $this->error('请求方法错误');
        $this->token();
        $orderNo = trim((string)$this->request->post('order_no', ''));
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $orderNo)) $this->error('订单号无效');
        $order = Db::name('checkin_recharge_order')->where('order_no', $orderNo)->find();
        if (!$order) $this->error('订单不存在');
        if ($order['status'] === 'paid') $this->success('订单已支付并到账，未关闭');
        if ($order['status'] === 'closed') $this->success('订单已关闭');
        if ($order['status'] !== 'pending' || $order['pay_type'] !== 'wechat') $this->error('该订单不能关闭');
        try {
            RechargeQuery::close($order);
            $current = Db::name('checkin_recharge_order')->where('order_no', $orderNo)->find();
        } catch (\Throwable $e) {
            \think\Log::record('checkin recharge admin close failed: ' . get_class($e), 'error');
            $this->error('无法确认订单状态，请稍后核对');
        }
        if (($current['status'] ?? '') === 'paid') $this->success('订单已支付并到账，未关闭');
        if (($current['status'] ?? '') === 'closed') $this->success('订单已关闭');
        $this->error('无法确认订单状态，请稍后核对');
    }

    public function edit($ids = null)
    {
        if (!$this->request->isPost()) $this->error('请求方法错误');
        $this->token();
        $id = (int)$this->request->post('id');
        $data = $this->packageData();
        if (!Db::name('checkin_recharge_package')->where('id', $id)->find()) $this->error('套餐不存在');
        $data['updatetime'] = time();
        Db::name('checkin_recharge_package')->where('id', $id)->update($data);
        $this->success('保存成功');
    }

    public function createpackage()
    {
        if (!$this->request->isPost()) $this->error('请求方法错误');
        $this->token();
        $data = $this->packageData();
        $data['createtime'] = $data['updatetime'] = time();
        Db::name('checkin_recharge_package')->insert($data);
        $this->success('添加成功');
    }

    public function deletepackage()
    {
        if (!$this->request->isPost()) $this->error('请求方法错误');
        $this->token();
        $id = (int)$this->request->post('id');
        if ($id < 1) $this->error('套餐不存在');
        try {
            Db::transaction(function () use ($id) {
                $package = Db::name('checkin_recharge_package')->where('id', $id)->lock(true)->find();
                if (!$package) throw new \RuntimeException('套餐不存在');
                if (Db::name('checkin_recharge_order')->where('package_id', $id)->find()) throw new \RuntimeException('套餐已有订单，请停用套餐以保留订单记录');
                Db::name('checkin_recharge_package')->where('id', $id)->delete();
            });
        } catch (\RuntimeException $e) { $this->error($e->getMessage()); }
        $this->success('删除成功');
    }

    private function packageData()
    {
        $name = trim((string)$this->request->post('name', ''));
        $description = trim((string)$this->request->post('description', ''));
        $weigh = (string)$this->request->post('weigh', '0');
        $coins = (string)$this->request->post('coins', '');
        $price = (string)$this->request->post('price_fen', '');
        $original = (string)$this->request->post('original_price_fen', '');
        if ($name === '' || mb_strlen($name, 'UTF-8') > 60 || preg_match('/[\x00-\x1f\x7f]/', $name)) $this->error('套餐名称须为 1 至 60 个字符');
        if (mb_strlen($description, 'UTF-8') > 120 || preg_match('/[\x00-\x1f\x7f]/', $description)) $this->error('展示说明不能超过 120 个字符');
        if (!ctype_digit($weigh) || (int)$weigh > 1000000) $this->error('排序权重须为 0 至 1000000 的整数');
        if (!ctype_digit($coins) || !ctype_digit($price) || !ctype_digit($original)
            || (int)$coins < 1 || (int)$price < 1 || (int)$original < (int)$price
            || (int)$coins > 100000000 || (int)$original > 100000000) $this->error('套餐参数无效');
        return [
            'name' => $name, 'coins' => (int)$coins, 'price_fen' => (int)$price, 'original_price_fen' => (int)$original,
            'description' => $description, 'recommended' => (int)$this->request->post('recommended', 0) === 1 ? 1 : 0,
            'weigh' => (int)$weigh,
            'enabled' => (int)$this->request->post('enabled', 0) === 1 ? 1 : 0,
        ];
    }
}
