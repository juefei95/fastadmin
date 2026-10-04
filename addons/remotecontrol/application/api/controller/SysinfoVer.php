<?php

namespace app\api\controller;

class SysinfoVer extends Remote
{
    protected $noNeedLogin = ['index'];
    protected $noNeedRight = '*';

    public function index()
    {
        return $this->sysinfover();
    }
}
