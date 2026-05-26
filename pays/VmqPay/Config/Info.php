<?php
declare (strict_types=1);

return [
    'version' => '1.0.1',
    'name' => 'V免签',
    'author' => '可狗可乐',
    'website' => '#',
    'description' => 'V免签对接',
    'options' => [
        '2' => '支付宝',
        '1' => '微信',
    ],
    'callback' => [
        \App\Consts\Pay::IS_SIGN => true,
        \App\Consts\Pay::IS_STATUS => false,
        \App\Consts\Pay::FIELD_STATUS_KEY => 'status',
        \App\Consts\Pay::FIELD_STATUS_VALUE => 'TRADE_SUCCESS',
        \App\Consts\Pay::FIELD_ORDER_KEY => 'payId',
        \App\Consts\Pay::FIELD_AMOUNT_KEY => 'price',
        \App\Consts\Pay::FIELD_RESPONSE => 'success'
    ]
];