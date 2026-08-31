<?php

namespace addons\remotecontrol\library;

use think\Db;
use think\Exception;

class RemoteOrderService
{
    protected $packageService;
    protected $memberService;

    public function __construct(RemotePackageService $packageService = null, RemoteMemberService $memberService = null)
    {
        $this->packageService = $packageService ?: new RemotePackageService();
        $this->memberService = $memberService ?: new RemoteMemberService();
    }

    public function createPendingOrder($userId, $packageId, $payType = '')
    {
        $userId = (int)$userId;
        if ($userId <= 0) {
            throw new Exception('User ID must be greater than 0');
        }

        $package = $this->packageService->getEnabledPackage($packageId);
        $snapshot = $this->packageService->snapshot($package);
        $now = time();
        $orderNo = $this->buildOrderNo();

        Db::name('remote_order')->insert([
            'order_no'     => $orderNo,
            'user_id'      => $userId,
            'package_id'   => $snapshot['package_id'],
            'package_name' => $snapshot['package_name'],
            'days'         => $snapshot['days'],
            'amount'       => $snapshot['amount'],
            'pay_type'     => $payType,
            'status'       => 0,
            'createtime'   => $now,
            'updatetime'   => $now,
        ]);

        return Db::name('remote_order')->where('order_no', $orderNo)->find();
    }

    public function markPaid($orderNo, $paidAt = null)
    {
        $paidAt = $paidAt ?: time();

        Db::startTrans();
        try {
            $order = Db::name('remote_order')->where('order_no', $orderNo)->lock(true)->find();
            if (!$order) {
                throw new Exception('Order not found');
            }

            if ((int)$order['status'] === 1) {
                Db::commit();
                return $order;
            }

            if ((int)$order['status'] !== 0) {
                throw new Exception('Only pending orders can be paid');
            }

            Db::name('remote_order')->where('id', (int)$order['id'])->update([
                'status'     => 1,
                'paid_at'    => $paidAt,
                'updatetime' => time(),
            ]);

            $this->memberService->addDays($order['user_id'], $order['days'], $order['amount'], $paidAt, false);
            Db::commit();

            return Db::name('remote_order')->where('id', (int)$order['id'])->find();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    public function closePendingOrder($orderNo)
    {
        $order = $this->getOrder($orderNo);
        if ((int)$order['status'] !== 0) {
            return $order;
        }

        Db::name('remote_order')->where('id', (int)$order['id'])->update([
            'status'     => 2,
            'updatetime' => time(),
        ]);

        return Db::name('remote_order')->where('id', (int)$order['id'])->find();
    }

    public function getUserOrder($userId, $orderNo)
    {
        $userId = (int)$userId;
        $orderNo = trim((string)$orderNo);
        if ($userId <= 0) {
            throw new Exception('User ID must be greater than 0');
        }
        if ($orderNo === '') {
            throw new Exception('Order number is required');
        }

        $order = Db::name('remote_order')
            ->where('user_id', $userId)
            ->where('order_no', $orderNo)
            ->find();
        if (!$order) {
            throw new Exception('Order not found');
        }

        return $order;
    }

    public function getOrder($orderNo)
    {
        $orderNo = trim((string)$orderNo);
        if ($orderNo === '') {
            throw new Exception('Order number is required');
        }

        $order = Db::name('remote_order')->where('order_no', $orderNo)->find();
        if (!$order) {
            throw new Exception('Order not found');
        }

        return $order;
    }

    public function getStatusText($status)
    {
        $list = [
            0 => '待支付',
            1 => '已支付',
            2 => '已关闭',
            3 => '已退款',
        ];

        return $list[(int)$status] ?? '未知';
    }

    public function getStatusList()
    {
        return [
            0 => '待支付',
            1 => '已支付',
            2 => '已关闭',
            3 => '已退款',
        ];
    }

    protected function buildOrderNo()
    {
        return date('YmdHis') . mt_rand(100000, 999999);
    }
}
