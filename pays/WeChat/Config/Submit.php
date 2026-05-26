<?php
declare (strict_types=1);

return [
    [
        "title" => "商户号",
        "name" => "mch_id",
        "type" => "input",
        "placeholder" => "微信支付分配的商户号"
    ],
    [
        "title" => "API密钥",
        "name" => "key",
        "type" => "input",
        "placeholder" => "API密钥"
    ],
    [
        "title" => "AppID",
        "name" => "app_id",
        "type" => "input",
        "placeholder" => "微信平台分配给开发者的应用ID"
    ],
    [
        "title" => "",
        "name" => "explain",
        "type" => "explain",
        "placeholder" => '<b style="color: #229208;">┏━ <b style="color: red;">‹ JSAPI › </b> 如果不使用JSAPI则不需要配置</b>',
    ],
    [
        "title" => "收款方",
        "name" => "payee",
        "type" => "input",
        "placeholder" => "收银台中显示的收款方"
    ],
    [
        "title" => "AppSecret",
        "name" => "app_secret",
        "type" => "input",
        "placeholder" => "AppSecret(JSAPI支付才需要)"
    ],
    [
        "title" => "网页授权域名",
        "name" => "http_url",
        "type" => "input",
        "placeholder" => "网页授权域名(JSAPI支付才需要),需要带https://"
    ],
    [
        "title" => "",
        "name" => "explain",
        "type" => "explain",
        "placeholder" => '<b style="color: #229208;">┏━ <b style="color: red;">‹ H5 › </b> 如果不使用H5则不需要配置</b>',
    ],
    [
        "title" => "H5域名",
        "name" => "wap_url",
        "type" => "input",
        "placeholder" => "H5网站URL地址，需要带https://"
    ],
    [
        "title" => "H5网站名",
        "name" => "wap_name",
        "type" => "input",
        "placeholder" => "H5网站名"
    ],
];