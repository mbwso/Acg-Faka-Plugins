<?php
declare (strict_types=1);

return [
    'version' => '1.0.4',
    'name' => '中国银联',
    'author' => '荔枝',
    'website' => 'https://up.95516.com/open/openapi',
    'description' => '中国银联统一扫码支付，条码支付综合前置平台！',
    'options' => [
        'wx' => '微信',
        'alipay' => '支付宝'
    ],
    'callback' => [
        \App\Consts\Pay::IS_SIGN => true,
        \App\Consts\Pay::IS_STATUS => false,
        \App\Consts\Pay::FIELD_STATUS_KEY => '',
        \App\Consts\Pay::FIELD_STATUS_VALUE => '',
        \App\Consts\Pay::FIELD_ORDER_KEY => 'out_trade_no',
        \App\Consts\Pay::FIELD_AMOUNT_KEY => 'total_fee',
        \App\Consts\Pay::FIELD_RESPONSE => 'success'
    ]
];