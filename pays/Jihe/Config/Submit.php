<?php
declare (strict_types=1);

return [
    [
        "title" => "支付网关",
        "name" => "url",
        "type" => "input",
        "placeholder" => "支付网关地址(如:https://xxx.com)"
    ],
    [
        "title" => "商户ID",
        "name" => "appid",
        "type" => "input",
        "placeholder" => "商户ID"
    ],
    [
        "title" => "商户密钥",
        "name" => "secret_key",
        "type" => "input",
        "placeholder" => "商户密钥"
    ],
    [
        "title" => "RSA2私钥",
        "name" => "private_key",
        "type" => "textarea",
        "placeholder" => "RSA2私钥"
    ],
    [
        "title" => "RSA2公钥",
        "name" => "public_key",
        "type" => "textarea",
        "placeholder" => "RSA2公钥"
    ]
];