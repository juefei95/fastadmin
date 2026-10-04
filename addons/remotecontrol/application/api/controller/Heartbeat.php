<?php

namespace app\api\controller;

class Heartbeat extends Remote
{
    protected $noNeedLogin = ['index'];
    protected $noNeedRight = '*';

    public function index()
    {
        return $this->heartbeat();
    }
}
