<?php
declare(strict_types=1);

namespace App\View\User\Theme\Magic;

use App\Consts\Render;

interface Config
{

    const INFO = [
        "NAME" => "原初之黑",
        "AUTHOR" => "荔枝",
        "VERSION" => "1.1.0",
        "WEB_SITE" => "#",
        "DESCRIPTION" => "该模板是以魔法元素为主，看上去就好似有魔法一样。",
        "RENDER" => Render::ENGINE_PHP
    ];


    const THEME = [
        "INDEX" => "Index.php",
        "ITEM" => "Index.php",
        "QUERY" => "Query.php",
    ];

}