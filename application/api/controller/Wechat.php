<?php

namespace app\api\controller;

/**
 * 兼容旧接口：/api/wechat/* 仍转发到微信扫码登录插件。
 */
class Wechat extends \addons\wechatqrcodelogin\controller\Index
{
}