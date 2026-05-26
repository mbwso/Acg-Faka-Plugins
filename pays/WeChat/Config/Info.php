<?php
declare (strict_types=1);


return [
    'version' => '1.0.7',
    'name' => '微信官方支付-SDK',
    'author' => '荔枝',
    'website' => 'https://open.weixin.qq.com',
    'description' => '微信官方支付！',
    'options' => [
        1 => '扫码支付',
        2 => 'H5支付',
        3 => 'JSAPI支付'
    ],
    'callback' => [
        \App\Consts\Pay::IS_SIGN => true,
        \App\Consts\Pay::IS_STATUS => false,
        \App\Consts\Pay::FIELD_STATUS_KEY => '',
        \App\Consts\Pay::FIELD_STATUS_VALUE => '',
        \App\Consts\Pay::FIELD_ORDER_KEY => 'out_trade_no',
        \App\Consts\Pay::FIELD_AMOUNT_KEY => 'total_fee',
        \App\Consts\Pay::FIELD_RESPONSE => "<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>"
    ]
];