<?php
declare(strict_types=1);

return [
    [
        "title" => "展位标题",
        "name" => "booth_title",
        "type" => "input",
        "placeholder" => "请输入展示在展位商品上的标题"
    ],
    [
        "title" => "API 地址",
        "name" => "api_url",
        "type" => "input",
        "placeholder" => "例如：https://www.getfollow.net/api/v3"
    ],
    [
        "title" => "APIKEY",
        "name" => "api_key",
        "type" => "input",
        "placeholder" => "请输入 Lumepanel APIKEY"
    ],
    [
        "title" => "缓存刷新口令",
        "name" => "cache_refresh_token",
        "type" => "input",
        "placeholder" => "用于刷新缓存链接中的 token 参数"
    ],
    [
        "title" => "加价率(%)",
        "name" => "premium_rate",
        "type" => "input",
        "placeholder" => "例如 20 表示在原价基础上加价20%"
    ]
];
