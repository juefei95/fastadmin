<?php

return [
    [
        'name' => 'wechat_app_id',
        'title' => '微信小游戏 AppID',
        'type' => 'string',
        'content' => [],
        'value' => '',
        'rule' => 'required',
        'msg' => '',
        'tip' => '填写微信公众平台中该小游戏的 AppID。',
        'ok' => '',
        'extend' => '',
    ],
    [
        'name' => 'wechat_app_secret',
        'title' => '微信小游戏 AppSecret',
        'type' => 'password',
        'content' => [],
        'value' => '',
        'rule' => 'required',
        'msg' => '',
        'tip' => '填写微信公众平台中该小游戏的 AppSecret，仅保存在服务端。',
        'ok' => '',
        'extend' => '',
    ],
];
