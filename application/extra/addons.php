<?php

return [
    'autoload' => false,
    'hooks' => [
        'user_register_successed' => [
            'checkin',
            'wallet',
        ],
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
        'app_init' => [
            'legacycheckin',
            'wallet',
        ],
        'app_dispatch' => [
            'legacycheckin',
        ],
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
        'config_init' => [
            'third',
        ],
        'view_filter' => [
            'third',
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
