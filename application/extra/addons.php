<?php

return [
    'autoload' => false,
    'hooks' => [
        'epay_config_init' => [
            'epay',
        ],
        'addon_action_begin' => [
            'epay',
        ],
        'action_begin' => [
            'epay',
            'third',
        ],
        'user_delete_successed' => [
            'third',
        ],
        'user_logout_successed' => [
            'third',
        ],
        'module_init' => [
            'third',
        ],
        'config_init' => [
            'third',
        ],
        'view_filter' => [
            'third',
        ],
        'sms_send' => [
            'txsms',
        ],
        'sms_notice' => [
            'txsms',
        ],
        'sms_check' => [
            'txsms',
        ],
    ],
    'route' => [
        '/third$' => 'third/index/index',
        '/third/connect/[:platform]' => 'third/index/connect',
        '/third/callback/[:platform]' => 'third/index/callback',
        '/third/bind/[:platform]' => 'third/index/bind',
        '/third/unbind/[:platform]' => 'third/index/unbind',
    ],
    'priority' => [],
    'domain' => '',
];
