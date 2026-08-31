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
        if (!$this->request->isPost() || strtolower($this->request->pathinfo()) !== 'api/remote/order/create') {
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
}
