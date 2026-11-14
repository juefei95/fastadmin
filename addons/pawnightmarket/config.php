<?php

return [
    [
        'name' => 'wechat_appid',
        'title' => '微信小游戏 AppID',
        'type' => 'string',
        'content' => [],
        'value' => '',
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
        'value' => '',
        'rule' => '',
        'msg' => '',
        'tip' => '填写微信小游戏的 AppSecret，请勿提交已填写的配置文件',
        'ok' => '',
        'extend' => 'autocomplete="new-password"',
    ],
];
