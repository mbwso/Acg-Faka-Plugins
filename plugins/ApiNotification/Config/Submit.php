<?php
declare (strict_types=1);

return [
    [
        "title" => "请求方式",
        "name" => "request_type",
        "type" => "select",
        "dict" => [
            ["id" => 0, "name" => "POST"],
            ["id" => 1, "name" => "GET"]
        ],
        "default" => 0,
        "placeholder" => "请选择"
    ],
    [
        "title" => "通信密钥",
        "name" => "key",
        "type" => "input",
        "placeholder" => "用作与签名的通讯密钥",
        "default" => \App\Util\Str::generateRandStr(16)
    ],
    [
        "title" => "签名方式",
        "name" => "sign_type",
        "type" => "select",
        "dict" => [
            ["id" => 0, "name" => "MD5"]
        ],
        "default" => 0,
        "placeholder" => "请选择"
    ],
    [
        "title" => "签名说明",
        "name" => "explain2",
        "type" => "explain",
        "placeholder" => "签名算法详见/app/Util/Str.php类中的generateSignature方法。"
    ],
    [
        "title" => "API地址",
        "name" => "url",
        "type" => "input",
        "placeholder" => "请输入API地址"
    ],
    [
        "title" => "记录日志",
        "name" => "log",
        "type" => "switch",
        "text" => "开启"
    ],
    [
        "title" => "附加头",
        "name" => "headers",
        "type" => "json"
    ],
    [
        "title" => "附加参数",
        "name" => "param",
        "type" => "json"
    ],
    [
        "title" => "商品选择",
        "name" => "commodity",
        "type" => "checkbox",
        "placeholder" => "",
        "dict" => "commodity->owner=0,id,name"
    ],
    [
        "title" => "说明",
        "name" => "explain3",
        "type" => "explain",
        "placeholder" => "本插件启动后，添加/修改商品，将会在最底部新增商品信息扩展JSON栏，在POST发送时，将会携带该信息发送至你填写的API，届时你将可以通过该信息实现任何你想实现的功能。"
    ],
];