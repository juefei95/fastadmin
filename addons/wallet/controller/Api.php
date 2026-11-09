<?php
namespace addons\wallet\controller;

use addons\wallet\library\Wallet;
use app\common\controller\Api as BaseApi;
use think\Db;

class Api extends BaseApi
{
    protected $noNeedRight = '*';

    public function balance()
    {
        $currency = trim((string)$this->request->get('currency', 'coin'));
        if (!preg_match('/^[a-z][a-z0-9_]{0,15}$/', $currency)) $this->error('币种无效');
        $this->success('', ['currency' => $currency, 'balance' => Wallet::balance($this->auth->id, $currency)]);
    }

    public function ledger()
    {
        $currency = trim((string)$this->request->get('currency', 'coin'));
        if (!preg_match('/^[a-z][a-z0-9_]{0,15}$/', $currency)) $this->error('币种无效');
        $page = max(1, (int)$this->request->get('page', 1));
        $rows = Db::name('wallet_ledger')->where('user_id', $this->auth->id)->where('currency', $currency)->field('id,amount,`before`,`after`,type,memo,createtime')->order('id', 'desc')->page($page, 20)->select();
        $this->success('', ['rows' => $rows, 'page' => $page]);
    }
}
