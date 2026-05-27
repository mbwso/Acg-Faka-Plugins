<?php
declare(strict_types=1);

namespace App\View\User\Theme\FengLing;

use App\Consts\Render;

interface Config
{
    const INFO = [
        "NAME" => "风铃发卡",
        "AUTHOR" => "Acg-Faka-Plugins",
        "VERSION" => "1.0.0",
        "WEB_SITE" => "#",
        "DESCRIPTION" => "风铃发卡——单页响应式商城模板，PC 与移动端共用一套布局，专注前台下单体验。",
        "RENDER" => Render::ENGINE_SMARTY,
    ];

    const SUBMIT = [
        [
            "title" => "主题色",
            "name" => "primary_color",
            "type" => "input",
            "placeholder" => "#648ff7",
            "default" => "#648ff7",
        ],
        [
            "title" => "强调色",
            "name" => "accent_color",
            "type" => "input",
            "placeholder" => "#f97c73",
            "default" => "#f97c73",
        ],
        [
            "title" => "商品列表样式",
            "name" => "list_type",
            "type" => "radio",
            "dict" => [
                ["id" => "select", "name" => "下拉框"],
                ["id" => "button", "name" => "按钮组"],
            ],
            "default" => "select",
        ],
    ];

    const THEME = [
        "INDEX" => "Index.html",
        "ITEM" => "Index.html",
        "QUERY" => "Query.html",
    ];
}
