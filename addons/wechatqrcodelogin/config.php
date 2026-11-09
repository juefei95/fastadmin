<?php

return array (
  0 => 
  array (
    'name' => 'appid',
    'title' => '微信公众号 AppID',
    'type' => 'string',
    'content' => 
    array (
    ),
    'value' => '',
    'rule' => 'required',
    'msg' => '',
    'tip' => '',
    'ok' => '',
    'extend' => '',
  ),
  1 => 
  array (
    'name' => 'secret',
    'title' => '微信公众号 AppSecret',
    'type' => 'password',
    'content' => 
    array (
    ),
    'value' => '',
    'rule' => 'required',
    'msg' => '',
    'tip' => '',
    'ok' => '',
    'extend' => '',
  ),
  2 => 
  array (
    'name' => 'token',
    'title' => '微信公众号服务器配置 Token',
    'type' => 'string',
    'content' => 
    array (
    ),
    'value' => '',
    'rule' => 'required',
    'msg' => '',
    'tip' => '',
    'ok' => '',
    'extend' => '',
  ),
  3 => 
  array (
    'name' => 'aes_key',
    'title' => '微信公众号消息加解密 Key',
    'type' => 'string',
    'content' => 
    array (
    ),
    'value' => '',
    'rule' => '',
    'msg' => '',
    'tip' => '明文模式可留空',
    'ok' => '',
    'extend' => '',
  ),
  4 => 
  array (
    'name' => 'http_verify',
    'title' => 'SSL 证书校验',
    'type' => 'radio',
    'content' => 
    array (
      1 => '开启',
      0 => '关闭',
    ),
    'value' => '0',
    'rule' => 'required',
    'msg' => '',
    'tip' => '正式环境建议开启，本地联调可关闭',
    'ok' => '',
    'extend' => '',
  ),
  5 => 
  array (
    'name' => 'http_proxy',
    'title' => '微信 API HTTP 代理',
    'type' => 'string',
    'content' => 
    array (
    ),
    'value' => '',
    'rule' => '',
    'msg' => '',
    'tip' => '留空则直连；格式：http://用户名:密码@主机:端口',
    'ok' => '',
    'extend' => '',
  ),
);
