<?php

return [
    'autoload' => false,
    'hooks' => [
        'user_delete_successed' => [
            'gamerank',
            'pawchess',
            'third',
        ],
        'upgrade' => [
            'gamerank',
            'pawchess',
        ],
        'app_init' => [
            'pawchess',
        ],
        'app_dispatch' => [
            'pawchess',
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
