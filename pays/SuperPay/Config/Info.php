<?php
declare (strict_types=1);

return [
    'version' => '1.0.0',
    'name' => '超级支付',
    'author' => '#',
    'website' => '#',
    'description' => '支持超级支付协议',
    'options' => [
        'alipay' => '支付宝',
        'wxpay' => '微信',
        'qqpay' => 'QQ钱包',
        'usdt' => 'USDT',
        'bank' => '网银支付',
        'jdpay' => '京东钱包',
        'unionpay' => '云闪付',
        'paypal' => 'PayPal',
        'douyinpay' => '抖音支付',
    ],
    'callback' => [
        \App\Consts\Pay::IS_SIGN => true,
        \App\Consts\Pay::IS_STATUS => true,
        \App\Consts\Pay::FIELD_STATUS_KEY => 'trade_status',
        \App\Consts\Pay::FIELD_STATUS_VALUE => 'TRADE_SUCCESS',
        \App\Consts\Pay::FIELD_ORDER_KEY => 'out_trade_no',
        \App\Consts\Pay::FIELD_AMOUNT_KEY => 'total_amount',
        \App\Consts\Pay::FIELD_RESPONSE => 'success'
    ]
];