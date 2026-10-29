<?php

return [
    'autoload' => false,
    'hooks' => [
        'user_delete_successed' => [
            'third',
            'wallet',
        ],
        'user_logout_successed' => [
            'third',
        ],
        'module_init' => [
            'third',
        ],
        'action_begin' => [
            'third',
        ],
        'config_init' => [
            'third',
        ],
        'view_filter' => [
            'third',
        ],
        'app_init' => [
            'wallet',
        ],
        'user_register_successed' => [
            'wallet',
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
