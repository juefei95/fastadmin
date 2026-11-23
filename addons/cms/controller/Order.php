<?php

namespace addons\cms\controller;

use addons\cms\library\OrderException;
use addons\cms\model\Archives;
use addons\epay\library\Service;
use Exception;

/**
 * 订单控制器
 * Class Order
 * @package addons\cms\controller
 */
class Order extends Base
{

    /**
     * 创建订单并发起支付请求
     */
    public function submit()
    {
        if (!$this->request->isPost()) {
            $this->error("请求方式错误");
        }
        $config = get_addon_config('cms');
        //是否需要登录后才可以支付
        if ($config['ispaylogin'] && !$this->auth->isLogin()) {
            $this->error("请登录后再进行操作!", "index/user/login");
        }

        $id = $this->request->request('id');
        $paytype = $this->request->request('paytype');
        $archives = Archives::get($id);
        if (!$archives || ($archives['user_id'] != $this->auth->id && $archives['status'] != 'normal') || $archives['deletetime']) {
            $this->error('未找到指的文档');
        }

        // 判断作者无需支付
        if ($archives->user_id && $archives->user_id == $this->auth->id) {
            $this->redirect($archives->url);
        }

        try {
            $response = \addons\cms\library\Order::submit($id, $paytype ? $paytype : $config['defaultpaytype']);
        } catch (OrderException $e) {
            if ($e->getCode() == 1) {
                $this->success($e->getMessage(), $archives->url);
            } else {
                $this->error($e->getMessage(), $archives->url);
            }
        } catch (Exception $e) {
            $this->error($e->getMessage(), $archives->url);
        }

        return $response;
    }

    /**
     * 企业支付通知和回调
     */
    public function epay()
    {
        $type = $this->request->param('type');
        $paytype = $this->request->param('paytype');
        if ($type == 'notify') {
            $pay = Service::checkNotify($paytype);
            if (!$pay) {
                return json(['code' => 'FAIL', 'message' => '失败'], 500, ['Content-Type' => 'application/json']);
            }

            // 获取回调数据，V3和V2的回调接收不同
            $data = Service::isVersionV3() ? $pay->callback() : $pay->verify();

            try {
                //微信支付V3返回和V2不同
                if (Service::isVersionV3() && $paytype === 'wechat') {
                    $data = $data['resource']['ciphertext'];
                    $data['total_fee'] = $data['amount']['total'];
                }

                //获取支付金额、订单号
                $payamount = $paytype == 'alipay' ? $data['total_amount'] : $data['total_fee'] / 100;
                $out_trade_no = $data['out_trade_no'];
                \addons\cms\library\Order::settle($out_trade_no, $payamount);
            } catch (Exception $e) {
                \think\Log::record("回调逻辑处理错误:" . $e->getMessage(), "error");
            }

            //下面这句必须要执行,且在此之前不能有任何输出
            if (Service::isVersionV3()) {
                return $pay->success()->getBody()->getContents();
            } else {
                return $pay->success()->send();
            }

        } else {
            $order = \addons\cms\model\Order::getByOrderid($this->request->param('orderid'));
            if (!$order->archives) {
                $this->error('未找到文档信息!');
            }
            $this->redirect($order->archives->url);
        }
        return;
    }
}
