<?php
declare (strict_types=1);

return [
    [
        "title" => "安全入口地址",
        "name" => "location",
        "type" => "input",
        "placeholder" => "比如：/aq1314"
    ],
    [
        "title" => "白名单IP",
        "name" => "whitelist",
        "type" => "textarea",
        "placeholder" => "白名单IP地址，一行一个"
    ],
    [
        "title" => "白名单",
        "name" => "white",
        "type" => "switch",
        "text" => "启用白名单"
    ],
    [
        "title" => "说明",
        "name" => "explain",
        "type" => "explain",
        "placeholder" => "<p style='color:green;font-weight: bold;'>白名单功能：启用白名单后，只有白名单中的IP才可以访问后台。</p><p><b style='color: red;'>请牢记安全入口地址，如果遗忘，您将再也无法进入后台，但是可以打开插件目录这个文件：/app/Plugin/Entrance/Config/Config.php，查看您的安全入口地址。</b></p>",
    ]
];