<?php

return [
    [
        'name' => 'wechat_appid',
        'title' => '微信小游戏 AppID',
        'type' => 'string',
        'content' => [],
        'value' => 'wx1df8f085e67d8ec5',
        'rule' => '',
        'msg' => '',
        'tip' => '填写微信小游戏的 AppID，需与前端项目一致',
        'ok' => '',
        'extend' => '',
    ],
    [
        'name' => 'wechat_secret',
        'title' => '微信小游戏 AppSecret',
        'type' => 'password',
        'content' => [],
        'value' => '1396db835a4b585858ece16c869bc3dd',
        'rule' => '',
        'msg' => '',
        'tip' => '填写微信小游戏的 AppSecret，请勿提交已填写的配置文件',
        'ok' => '',
        'extend' => 'autocomplete="new-password"',
    ],
];
