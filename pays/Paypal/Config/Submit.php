<?php
declare (strict_types=1);

return [
    [
        "title" => "Client ID",
        "name" => "client_id",
        "type" => "input",
        "placeholder" => "Client ID"
    ],
    [
        "title" => "Secret",
        "name" => "secret",
        "type" => "input",
        "placeholder" => "Secret"
    ],
    [
        "title" => "汇率",
        "name" => "rate",
        "type" => "input",
        "placeholder" => "人民币转美元汇率",
        "default" => 6.3677
    ],
    [
        "title" => "汇率说明",
        "name" => "explain",
        "type" => "explain",
        "placeholder" => "这里的汇率算法：订单金额（人民币）÷汇率=Paypal支付金额（美元），请根据市场自己定制汇率"
    ]
];