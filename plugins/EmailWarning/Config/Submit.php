<?php
declare (strict_types=1);

return [
    [
        "title" => "邮箱",
        "name" => "email",
        "type" => "input",
        "placeholder" => "请输入接受邮件通知的邮箱"
    ],[
        "title" => "全局预警量",
        "name" => "num",
        "type" => "input",
        "placeholder" => "如果没有对商品单独设置预警量，那么就会以此处的设置为准",
        "default"=>"10"
    ]
];