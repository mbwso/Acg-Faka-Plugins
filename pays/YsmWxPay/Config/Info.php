<?php
declare (strict_types=1);


return [
    'version' => '1.0.2',
    'name' => '易收米-微信支付',
    'author' => '易收米',
    'website' => '#',
    'description' => '微信支付官方个人支付接口，无需营业执照，无需挂机，官方直连结算，资金实时到帐个人账户，专业解决个人网站收款难题。',
    'options' => [
        "js" => '微信内支付',
        "h5" => '微信H5支付',
        "pc" => '微信扫码支付',
    ],
    'callback' => [
        \App\Consts\Pay::IS_SIGN => true,
        \App\Consts\Pay::IS_STATUS => true,
        \App\Consts\Pay::FIELD_STATUS_KEY => 'state',
        \App\Consts\Pay::FIELD_STATUS_VALUE => 'SUCCESS',
        \App\Consts\Pay::FIELD_ORDER_KEY => 'mch_orderid',
        \App\Consts\Pay::FIELD_AMOUNT_KEY => 'total_fee',
        \App\Consts\Pay::FIELD_RESPONSE => 'success'
    ]
];