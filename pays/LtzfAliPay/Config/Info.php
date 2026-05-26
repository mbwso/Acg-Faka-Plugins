<?php
declare (strict_types=1);


return [
    'version' => '1.0.2',
    'name' => '蓝兔支付-支付宝',
    'author' => '蓝兔支付',
    'website' => '#',
    'description' => '支付宝官方个人支付接口，无需营业执照，官方直连结算，资金实时到帐，解决个人收款痛点。',
    'options' => [
        1 => '扫码支付',
        2 => 'H5支付',
    ],
    'callback' => [
        \App\Consts\Pay::IS_SIGN => true,
        \App\Consts\Pay::IS_STATUS => true,
        \App\Consts\Pay::FIELD_STATUS_KEY => 'code',
        \App\Consts\Pay::FIELD_STATUS_VALUE => '0',
        \App\Consts\Pay::FIELD_ORDER_KEY => 'out_trade_no',
        \App\Consts\Pay::FIELD_AMOUNT_KEY => 'total_fee',
        \App\Consts\Pay::FIELD_RESPONSE => 'SUCCESS'
    ]
];