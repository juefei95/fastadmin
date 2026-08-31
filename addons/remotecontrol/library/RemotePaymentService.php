<?php

namespace addons\remotecontrol\library;

use addons\epay\library\Collection;
use addons\epay\library\RedirectResponse;
use addons\epay\library\Response;
use think\Exception;

class RemotePaymentService
{
    protected $orderService;
    protected $config;

    public function __construct(RemoteOrderService $orderService = null, array $config = null)
    {
        $this->orderService = $orderService ?: new RemoteOrderService();
        $this->config = $config === null ? get_addon_config('remotecontrol') : $config;
    }

    public function ensurePaymentAvailable($payType)
    {
        $this->normalizePayType($payType);
        $addon = function_exists('get_addon_info') ? get_addon_info('epay') : null;
        if (!$addon || empty($addon['state']) || !class_exists('\\addons\\epay\\library\\Service')) {
            throw new Exception('请先安装并启用 FastAdmin 微信支付宝整合插件 epay');
        }
    }

    public function createPaymentParams(array $order)
    {
        $payType = $this->normalizePayType($order['pay_type'] ?? '');
        if ((int)$order['status'] !== 0) {
            throw new Exception('Only pending orders can be paid');
        }
        $this->ensurePaymentAvailable($payType);

        $params = [
            'amount'    => $this->formatAmount($order['amount']),
            'orderid'   => $order['order_no'],
            'type'      => $payType,
            'title'     => '远控套餐-' . $order['package_name'],
            'notifyurl' => $this->getNotifyUrl($payType),
            'returnurl' => $this->getReturnUrl($order),
            'method'    => $this->getPayMethod(),
        ];

        $result = \addons\epay\library\Service::submitOrder($params);

        return [
            'provider'   => 'epay',
            'pay_type'   => $payType,
            'order_no'   => $order['order_no'],
            'amount'     => $this->formatAmount($order['amount']),
            'method'     => $params['method'],
            'notify_url' => $params['notifyurl'],
            'return_url' => $params['returnurl'],
            'result'     => $this->normalizePaymentResult($result),
        ];
    }

    public function handleNotify($payType = null)
    {
        $payType = $this->normalizePayType($payType ?: request()->param('paytype', request()->param('type', '')));
        $this->ensurePaymentAvailable($payType);

        $pay = \addons\epay\library\Service::checkNotify($payType);
        if (!$pay) {
            throw new Exception('Payment notify verification failed');
        }

        $data = \addons\epay\library\Service::isVersionV3() ? $pay->callback() : $pay->verify();
        if (\addons\epay\library\Service::isVersionV3() && $payType === 'wechat') {
            $data = $data['resource']['ciphertext'];
            $data['total_fee'] = $data['amount']['total'];
        }

        return $this->handleVerifiedNotify($payType, $data, $pay);
    }

    public function handleVerifiedNotify($payType, array $data, $pay = null)
    {
        $payType = $this->normalizePayType($payType);
        $orderNo = $this->getNotifyOrderNo($data);
        $payAmount = $this->getNotifyAmount($payType, $data);
        $order = $this->orderService->getOrder($orderNo);
        if ($payType !== $order['pay_type']) {
            throw new Exception('Pay type mismatch');
        }
        if ($this->formatAmount($payAmount) !== $this->formatAmount($order['amount'])) {
            throw new Exception('Payment amount mismatch');
        }

        $order = $this->orderService->markPaid($orderNo);
        if (!$pay) {
            return $order;
        }

        if (\addons\epay\library\Service::isVersionV3()) {
            return $pay->success()->getBody()->getContents();
        }
        return $pay->success()->send();
    }

    protected function normalizePaymentResult($result)
    {
        if ($result instanceof Collection) {
            return [
                'type' => 'collection',
                'data' => $result->all(),
            ];
        }
        if ($result instanceof RedirectResponse) {
            return [
                'type' => 'redirect',
                'url'  => $result->getTargetUrl(),
                'html' => $result->getContent(),
            ];
        }
        if ($result instanceof Response) {
            return [
                'type' => 'html',
                'html' => $result->getContent(),
            ];
        }

        return [
            'type' => 'raw',
            'data' => $result,
        ];
    }

    protected function getPayMethod()
    {
        $method = strtolower(trim((string)$this->getConfig('payment_method', 'scan')));
        return $method ?: 'scan';
    }

    protected function getNotifyUrl($payType)
    {
        $url = trim((string)$this->getConfig('payment_notify_url', ''));
        if ($url === '') {
            $url = request()->domain() . '/api/remote/payment/notify';
        }

        return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query(['paytype' => $payType]);
    }

    protected function getReturnUrl(array $order)
    {
        $url = trim((string)$this->getConfig('payment_return_url', ''));
        if ($url !== '') {
            return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query(['order_no' => $order['order_no']]);
        }

        return request()->domain() . '/api/remote/order/status?' . http_build_query(['order_no' => $order['order_no']]);
    }

    protected function getNotifyOrderNo(array $data)
    {
        $orderNo = trim((string)($data['out_trade_no'] ?? $data['orderid'] ?? ''));
        if ($orderNo === '') {
            throw new Exception('Payment order number is missing');
        }

        return $orderNo;
    }

    protected function getNotifyAmount($payType, array $data)
    {
        $field = $payType === 'alipay' ? 'total_amount' : 'total_fee';
        $amount = $data[$field] ?? $data['amount'] ?? null;
        if ($amount === null || $amount === '') {
            throw new Exception('Payment amount is missing');
        }

        if ($payType === 'wechat' && isset($data['total_fee']) && is_numeric($data['total_fee'])) {
            return ((float)$data['total_fee']) / 100;
        }

        return $amount;
    }

    protected function normalizePayType($payType)
    {
        $payType = strtolower(trim((string)$payType));
        if (!in_array($payType, ['wechat', 'alipay'], true)) {
            throw new Exception('Unsupported pay type');
        }

        return $payType;
    }

    protected function getConfig($name, $default = null)
    {
        return array_key_exists($name, $this->config) ? $this->config[$name] : $default;
    }

    protected function formatAmount($amount)
    {
        return number_format((float)$amount, 2, '.', '');
    }
}
