<?php

return array (
  0 => 
  array (
    'name' => 'wxapp_appid',
    'title' => '微信小程序 AppID',
    'type' => 'string',
    'value' => '',
    'content' => 
    array (
    ),
    'rule' => '',
    'msg' => '',
    'tip' => '',
    'ok' => '',
    'extend' => '',
    'group' => '基本配置',
  ),
  1 => 
  array (
    'name' => 'wxapp_secret',
    'title' => '微信小程序 AppSecret',
    'type' => 'password',
    'value' => '',
    'content' => 
    array (
    ),
    'rule' => '',
    'msg' => '',
    'tip' => '',
    'ok' => '',
    'extend' => '',
    'group' => '基本配置',
  ),
  2 => 
  array (
    'name' => 'weekday_reward',
    'title' => '工作日签到金币',
    'type' => 'number',
    'value' => '3',
    'content' => 
    array (
    ),
    'rule' => 'required;integer',
    'msg' => '',
    'tip' => '周一至周五，指定节日除外',
    'ok' => '',
    'extend' => 'min="1" step="1"',
    'group' => '基本配置',
  ),
  3 => 
  array (
    'name' => 'weekend_reward',
    'title' => '周末签到金币',
    'type' => 'number',
    'value' => '5',
    'content' => 
    array (
    ),
    'rule' => 'required;integer',
    'msg' => '',
    'tip' => '周六、周日，指定节日除外',
    'ok' => '',
    'extend' => 'min="1" step="1"',
    'group' => '基本配置',
  ),
  4 => 
  array (
    'name' => 'holiday_reward',
    'title' => '指定节日签到金币',
    'type' => 'number',
    'value' => '7',
    'content' => 
    array (
    ),
    'rule' => 'required;integer',
    'msg' => '',
    'tip' => '元旦、春节（农历正月初一至初七）、劳动节、国庆节，优先于周末',
    'ok' => '',
    'extend' => 'min="1" step="1"',
    'group' => '基本配置',
  ),
  5 => 
  array (
    'name' => 'initial_coin',
    'title' => '新会员初始金币',
    'type' => 'number',
    'value' => '10',
    'content' => 
    array (
    ),
    'rule' => 'required;integer',
    'msg' => '',
    'tip' => '仅新注册会员生效，已有会员余额不变',
    'ok' => '',
    'extend' => 'min="0" step="1"',
    'group' => '基本配置',
  ),
  6 => 
  array (
    'name' => 'payment_mobile_appid',
    'title' => '微信移动应用支付 AppID',
    'type' => 'string',
    'value' => '',
    'content' => 
    array (
    ),
    'rule' => '',
    'msg' => '',
    'tip' => '预留给微信 APP 支付：填写时使用此 AppID，留空时使用 epay 微信配置中的 APP 的 appid。目前尚未接入 APP 支付，此配置暂不参与下单。',
    'ok' => '',
    'extend' => '',
    'group' => '微信支付',
  ),
  7 => 
  array (
    'name' => 'payment_appid',
    'title' => '微信扫码支付 AppID',
    'type' => 'string',
    'value' => '',
    'content' => 
    array (
    ),
    'rule' => '',
    'msg' => '',
    'tip' => '填写与商户号绑定且可用于 Native 扫码支付的 AppID（如公众号 AppID）；留空时使用 epay 微信配置中的公众号 app_id。',
    'ok' => '',
    'extend' => '',
    'group' => '微信支付',
  ),
  8 => 
  array (
    'name' => 'payment_method',
    'title' => '默认支付方式',
    'type' => 'select',
    'value' => 'wechat',
    'content' => 
    array (
      'wechat' => '微信支付',
      'alipay' => '支付宝',
    ),
    'rule' => '',
    'msg' => '',
    'tip' => '预留配置；微信和支付宝支付功能目前均未接通。',
    'ok' => '',
    'extend' => '',
    'group' => '基本配置',
  ),
  9 => 
  array (
    'name' => 'payment_wechat_enabled',
    'title' => '微信支付开关',
    'type' => 'switch',
    'value' => '1',
    'content' => 
    array (
    ),
    'rule' => '',
    'msg' => '',
    'tip' => '控制打卡微信 Native 扫码下单；启用前请完成支付配置和回调联调。',
    'ok' => '',
    'extend' => '',
    'group' => '微信支付',
  ),
  10 => 
  array (
    'name' => 'payment_alipay_enabled',
    'title' => '支付宝开关',
    'type' => 'switch',
    'value' => '0',
    'content' => 
    array (
    ),
    'rule' => '',
    'msg' => '',
    'tip' => '预留开关；支付宝尚未接入。',
    'ok' => '',
    'extend' => '',
    'group' => '支付宝',
  ),
  11 => 
  array (
    'name' => 'payment_notify_url',
    'title' => '微信支付回调地址',
    'type' => 'string',
    'value' => '',
    'content' => 
    array (
    ),
    'rule' => 'required;url',
    'msg' => '',
    'tip' => '填写公网 HTTPS 完整地址，例如 https://example.com/addons/checkin/payment/wechatnotify；不能携带参数。',
    'ok' => '',
    'extend' => '',
    'group' => '微信支付',
  ),
);
