<?php
declare (strict_types=1);

return [
    'version' => '1.0.2',
    'name' => 'USDT-免挂版(接口)',
    'author' => '荔枝',
    'website' => '#',
    'description' => '这个支付扩展需要依赖通用扩展：USDT-免挂版(主程序)，使用前请先安装通用扩展',
    'options' => [
        0 => 'TRC20-USDT'
    ],
    'callback' => [
        \App\Consts\Pay::IS_SIGN => true,
        \App\Consts\Pay::IS_STATUS => true,
        \App\Consts\Pay::FIELD_STATUS_KEY => 'status',
        \App\Consts\Pay::FIELD_STATUS_VALUE => 1,
        \App\Consts\Pay::FIELD_ORDER_KEY => 'trade_no',
        \App\Consts\Pay::FIELD_AMOUNT_KEY => 'amount',
        \App\Consts\Pay::FIELD_RESPONSE => 'success'
    ]
];