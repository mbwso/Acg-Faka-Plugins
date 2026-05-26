<?php
declare (strict_types=1);

return [
    'version' => '1.0.0',
    'name' => 'Paypal支付-SDK',
    'author' => '荔枝',
    'website' => 'https://developer.paypal.com',
    'description' => 'Paypal官方支付SDK，海外支付首选！	',
    'options' => [
        0 => 'Paypal-USD'
    ],
    'callback' => [
        \App\Consts\Pay::IS_SIGN => false,
        \App\Consts\Pay::IS_STATUS => false,
        \App\Consts\Pay::FIELD_STATUS_KEY => '#',
        \App\Consts\Pay::FIELD_STATUS_VALUE => '#',
        \App\Consts\Pay::FIELD_ORDER_KEY => '#',
        \App\Consts\Pay::FIELD_AMOUNT_KEY => '#',
        \App\Consts\Pay::FIELD_RESPONSE => '#'
    ]
];