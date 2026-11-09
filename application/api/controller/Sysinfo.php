<?php

namespace app\api\controller;

class Sysinfo extends Remote
{
    protected $noNeedLogin = ['index'];
    protected $noNeedRight = '*';

    public function index()
    {
        return $this->sysinfo();
    }
}
