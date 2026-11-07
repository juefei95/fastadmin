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
    'rule' => '',
    'msg' => '',
    'tip' => '全部凭据留空时使用公共扫码插件配置。',
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
    'rule' => '',
    'msg' => '',
    'tip' => '使用独立公众号时，与 AppID、Token 一并填写。',
    'ok' => '',
    'extend' => '',
  ),
  2 => 
  array (
    'name' => 'token',
    'title' => '微信公众号服务器配置 Token',
    'type' => 'password',
    'content' => 
    array (
    ),
    'value' => '',
    'rule' => '',
    'msg' => '',
    'tip' => '需与公众号后台服务器配置一致。',
    'ok' => '',
    'extend' => '',
  ),
  3 => 
  array (
    'name' => 'aes_key',
    'title' => '消息加解密 EncodingAESKey',
    'type' => 'password',
    'content' => 
    array (
    ),
    'value' => '',
    'rule' => '',
    'msg' => '',
    'tip' => '明文模式可留空。',
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
    'rule' => '',
    'msg' => '',
    'tip' => '仅用于独立公众号配置，正式环境建议开启。',
    'ok' => '',
    'extend' => '',
  ),
  5 => 
  array (
    'name' => 'http_proxy',
    'title' => '微信 API HTTP 代理',
    'type' => 'password',
    'content' => 
    array (
    ),
    'value' => '',
    'rule' => '',
    'msg' => '',
    'tip' => '仅用于独立公众号配置，留空直连。',
    'ok' => '',
    'extend' => '',
  ),
  6 => 
  array (
    'name' => 'update_manifest_url',
    'title' => '稳定版更新清单地址',
    'type' => 'string',
    'content' => 
    array (
    ),
    'value' => '',
    'rule' => '',
    'msg' => '',
    'tip' => '留空使用默认地址。客户端通过此 HTTPS 地址检查最新版本，清单需包含 version 和 windows.url。',
    'ok' => '',
    'extend' => '',
  ),
);
