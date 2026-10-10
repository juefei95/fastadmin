<?php

return [
    'wechat' => [
        'app_id' => '',
        'miniapp_id' => '',
        'mch_id' => '',
        'key' => '',
        'key_v3' => '',
        'public_key_id' => '',
        'notify_url' => 'https://api.example.com/api/remote/payment/notify/wechat',
    ],
    'alipay' => [
        'app_id' => '',
        'private_key' => '',
        'notify_url' => 'https://api.example.com/api/remote/payment/notify/alipay',
        'return_url' => 'https://example.com',
    ],
];
