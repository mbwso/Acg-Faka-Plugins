<?php
declare (strict_types=1);

return [
    'version' => '1.0.1',
    'name' => '几何支付',
    'author' => '荔枝',
    'website' => '#',
    'description' => '几何支付专用插件',
    'options' => [
        'ALIPAY_QR' => '支付宝扫码'
    ],
    'callback' => [
        \App\Consts\Pay::IS_SIGN => true,
        \App\Consts\Pay::IS_STATUS => true,
        \App\Consts\Pay::FIELD_STATUS_KEY => 'trade_status',
        \App\Consts\Pay::FIELD_STATUS_VALUE => 'TRADE_SUCCESS',
        \App\Consts\Pay::FIELD_ORDER_KEY => 'out_trade_no',
        \App\Consts\Pay::FIELD_AMOUNT_KEY => 'amount',
        \App\Consts\Pay::FIELD_RESPONSE => 'success'
    ]
];