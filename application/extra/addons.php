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
    'route' => [],
    'priority' => [],
    'domain' => '',
];
