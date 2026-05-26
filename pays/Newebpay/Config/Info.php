<?php
declare (strict_types=1);

return [
    'version' => '1.0.1',
    'name' => '藍新金流',
    'author' => '荔枝',
    'website' => '#',
    'description' => '該插件為台灣藍新金流公司的支付插件。',
    'options' => [
        '0' => '收銀檯'
    ],
    'callback' => [
        \App\Consts\Pay::IS_SIGN => true,
        \App\Consts\Pay::IS_STATUS => true,
        \App\Consts\Pay::FIELD_STATUS_KEY => 'Status',
        \App\Consts\Pay::FIELD_STATUS_VALUE => 'SUCCESS',
        \App\Consts\Pay::FIELD_ORDER_KEY => 'MerchantOrderNo',
        \App\Consts\Pay::FIELD_AMOUNT_KEY => 'Amt',
        \App\Consts\Pay::FIELD_RESPONSE => 'success'
    ]
];