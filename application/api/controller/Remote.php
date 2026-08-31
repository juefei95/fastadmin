<?php

namespace app\api\controller;

use addons\remotecontrol\library\RemoteOrderService;
use think\Config;
use think\Exception;

class Remote extends remote\Index
{
    public function order()
    {
        if (!Config::get('fastadmin.usercenter')) {
            $this->error(__('User center already closed'));
        }

        $pathinfo = strtolower(trim($this->request->pathinfo(), '/'));
        if ($pathinfo === 'api/remote/order/create') {
            $this->createOrder();
            return;
        }
        if ($pathinfo === 'api/remote/order/status') {
            $this->orderStatus();
            return;
        }

        $this->error(__('Invalid parameters'));
    }

    protected function createOrder()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }

        $packageId = (int)$this->request->post('package_id');
        $payType = (string)$this->request->post('pay_type', '');
        if ($packageId <= 0) {
            $this->error(__('Invalid parameters'));
        }

        try {
            $service = new RemoteOrderService();
            $order = $service->createPendingOrder($this->auth->id, $packageId, $payType);
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }

        $this->success('', [
            'order' => [
                'id'           => (int)$order['id'],
                'order_no'     => $order['order_no'],
                'package_id'   => (int)$order['package_id'],
                'package_name' => $order['package_name'],
                'days'         => (int)$order['days'],
                'amount'       => (string)$order['amount'],
                'pay_type'     => $order['pay_type'],
                'status'       => (int)$order['status'],
                'createtime'   => (int)$order['createtime'],
            ],
        ]);
    }

    protected function orderStatus()
    {
        if (!$this->request->isGet()) {
            $this->error(__('Invalid parameters'));
        }

        $orderNo = (string)$this->request->get('order_no', '');
        try {
            $service = new RemoteOrderService();
            $order = $service->getUserOrder($this->auth->id, $orderNo);
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }

        $this->success('', [
            'order' => [
                'order_no'    => $order['order_no'],
                'status'      => (int)$order['status'],
                'status_text' => $service->getStatusText($order['status']),
            ],
        ]);
    }
}
