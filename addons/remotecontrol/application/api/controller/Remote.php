<?php

namespace app\api\controller;

use addons\remotecontrol\library\RemoteOrderService;
use addons\remotecontrol\library\RemotePaymentService;
use addons\remotecontrol\library\RemoteHeartbeatService;
use addons\remotecontrol\library\RemoteRdpTunnelService;
use think\Config;
use think\Exception;

class Remote extends remote\Index
{
    protected $noNeedLogin = ['login', 'packages', 'payment', 'client', 'heartbeat', 'sysinfo', 'sysinfover'];
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
            'api_server'     => (string)($config['api_server'] ?? ''),
            'public_key'     => (string)($config['public_key'] ?? ''),
            'latest_version' => (string)($config['latest_version'] ?? ''),
            'min_version'    => (string)($config['min_version'] ?? ''),
        ]);
    }

    public function rdp()
    {
        $pathinfo = strtolower(trim($this->request->pathinfo(), '/'));
        if (!preg_match('#^api/remote/rdp/(status|open|config|started|close)$#', $pathinfo, $matches)) {
            \think\Log::warning('[RDP-FRPC] unsupported path=' . $pathinfo);
            $this->error(__('Invalid parameters'));
        }
        $action = $matches[1];
        if ((in_array($action, ['status', 'config'], true) && !$this->request->isGet()) || (!in_array($action, ['status', 'config'], true) && !$this->request->isPost())) {
            \think\Log::warning('[RDP-FRPC] invalid method action=' . $action . ' method=' . $this->request->method());
            $this->error(__('Invalid parameters'));
        }
        $deviceId = in_array($action, ['status', 'config'], true) ? (string)$this->request->get('device_id', '') : (string)$this->request->post('device_id', '');
        \think\Log::info('[RDP-FRPC] action=' . $action . ' user_id=' . (int)$this->auth->id
            . ' device_id_present=' . ($deviceId === '' ? 'N' : 'Y'));
        try {
            $service = new RemoteRdpTunnelService();
            if ($action === 'status') {
                $data = $service->getTunnel($this->auth->id, $deviceId);
            } elseif ($action === 'open') {
                $data = $service->activate($this->auth->id, $deviceId);
            } elseif ($action === 'config') {
                $data = $service->getFrpcConfig($this->auth->id, $deviceId);
            } elseif ($action === 'started') {
                $data = $service->confirmStarted($this->auth->id, $deviceId);
            } else {
                $data = $service->deactivate($this->auth->id, $deviceId);
            }
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
        $this->success('', $data);
    }

    public function heartbeat()
    {
        try {
            (new RemoteHeartbeatService())->recordHeartbeat($this->getRemotePayload());
        } catch (Exception $e) {
            \think\Log::error('[REMOTE-HEARTBEAT] ' . $e->getMessage());
            return response('', 500);
        }

        return json(new \stdClass());
    }

    public function sysinfo()
    {
        try {
            (new RemoteHeartbeatService())->recordSysinfo($this->getRemotePayload());
        } catch (Exception $e) {
            \think\Log::error('[REMOTE-SYSINFO] ' . $e->getMessage());
            return response('', 500);
        }

        return response('SYSINFO_UPDATED');
    }

    public function sysinfover()
    {
        $id = (string)$this->request->post('id', '');
        return response((new RemoteHeartbeatService())->getSysinfoVersion($id));
    }

    protected function getRemotePayload()
    {
        $payload = $this->request->post();
        if ($payload) {
            return $payload;
        }

        $payload = json_decode($this->request->getInput(), true);
        return is_array($payload) ? $payload : [];
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
