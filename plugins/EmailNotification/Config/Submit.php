<?php
declare (strict_types=1);

return [
    [
        "title" => "邮箱",
        "name" => "email",
        "type" => "input",
        "placeholder" => "请输入接受邮件通知的邮箱"
    ],
    [
        "title" => "下单话术",
        "name" => "trade_content",
        "type" => "input",
        "placeholder" => "请输入下单后发给你邮箱的话术",
        "default" => "您好，客户[contact]，在您的店铺下单了[card_num]件[name]，使用的[pay]进行支付。"
    ],
    [
        "title" => "说明",
        "name" => "explain",
        "type" => "explain",
        "placeholder" => "支持变量：[contact]为客户联系方式，[card_num]为购买数量，[name]为商品名称，[pay]为支付方式。",
    ],
    [
        "title" => "下单通知",
        "name" => "trade",
        "type" => "switch",
        "text" => "启用"
    ],
    [
        "title" => "付款话术",
        "name" => "payment_content",
        "type" => "input",
        "placeholder" => "请输入付款后发给你邮箱的话术",
        "default" => "您好，客户[contact]，在您的店铺下单了[card_num]件[name]，并且通过[pay]成功付款。"
    ],
    [
        "title" => "说明",
        "name" => "explain2",
        "type" => "explain",
        "placeholder" => "支持变量：[contact]为客户联系方式，[card_num]为购买数量，[name]为商品名称，[pay]为支付方式。",
    ],
    [
        "title" => "付款通知",
        "name" => "payment",
        "type" => "switch",
        "text" => "启用"
    ]
];