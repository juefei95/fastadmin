<?php

namespace app\api\controller;

use addons\remotecontrol\library\RemoteOrderService;
use addons\remotecontrol\library\RemotePaymentService;
use think\Config;
use think\Exception;

class Remote extends remote\Index
{
    protected $noNeedLogin = ['login', 'packages', 'payment', 'client'];
    protected $noNeedRight = '*';

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
        if ($pathinfo === 'api/remote/order/cancel') {
            $this->cancelOrder();
            return;
        }

        $this->error(__('Invalid parameters'));
    }

    public function payment()
    {
        if (!Config::get('fastadmin.usercenter')) {
            $this->error(__('User center already closed'));
        }

        $pathinfo = strtolower(trim($this->request->pathinfo(), '/'));
        if (!preg_match('#^api/remote/payment/notify/(wechat|alipay)$#', $pathinfo, $matches) || !$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }

        try {
            $service = new RemotePaymentService();
            return $service->handleNotify($matches[1]);
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
    }

    public function client()
    {
        $pathinfo = strtolower(trim($this->request->pathinfo(), '/'));
        if ($pathinfo !== 'api/remote/client/config' || !$this->request->isGet()) {
            $this->error(__('Invalid parameters'));
        }

        $config = get_addon_config('remotecontrol');
        $this->success('', [
            'id_server'      => (string)($config['id_server'] ?? ''),
            'relay_server'   => (string)($config['relay_server'] ?? ''),
            'public_key'     => (string)($config['public_key'] ?? ''),
            'latest_version' => (string)($config['latest_version'] ?? ''),
            'min_version'    => (string)($config['min_version'] ?? ''),
        ]);
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
            $paymentService = new RemotePaymentService($service);
            $paymentService->ensurePaymentAvailable($payType);
            $order = $service->createPendingOrder($this->auth->id, $packageId, $payType);
            try {
                $payment = $paymentService->createPaymentParams($order);
            } catch (Exception $e) {
                $service->closePendingOrder($order['order_no']);
                throw $e;
            }
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
            'payment' => $payment,
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

        $paymentStatus = '';
        if ((int)$order['status'] === 0) {
            try {
                $paymentStatus = (new RemotePaymentService($service))->getPaymentStatus($order);
                \think\Log::info('[REMOTE-PAYMENT-STATUS] order=' . $order['order_no'] . ' local=0 provider=' . $paymentStatus);
            } catch (Exception $e) {
                \think\Log::error('[REMOTE-PAYMENT-STATUS] order=' . $order['order_no'] . ' query_failed=' . $e->getMessage());
            }
        }

        $this->success('', [
            'order' => [
                'order_no'    => $order['order_no'],
                'status'      => (int)$order['status'],
                'status_text' => $service->getStatusText($order['status']),
                'payment_status' => $paymentStatus,
            ],
        ]);
    }

    protected function cancelOrder()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }

        $orderNo = (string)$this->request->post('order_no', '');
        try {
            $service = new RemoteOrderService();
            $order = $service->getUserOrder($this->auth->id, $orderNo);
            if ((int)$order['status'] === 0) {
                $paymentService = new RemotePaymentService($service);
                $paymentService->closePaymentOrder($order);
                $order = $service->closePendingOrder($order['order_no']);
            }
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
