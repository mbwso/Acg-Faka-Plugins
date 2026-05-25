<?php
declare (strict_types=1);

return [
    [
        "title" => "视频来源",
        "name" => "type",
        "type" => "radio",
        "dict" => [
            ["id" => 0, "name" => "远程视频地址"],
            ["id" => 1, "name" => "本地视频"]
        ],
        "default" => 0
    ],
    [
        "title" => "远程视频地址",
        "name" => "url",
        "type" => "input",
        "placeholder" => "远程MP4视频地址"
    ],
    [
        "title" => "本地视频",
        "name" => "video_url",
        "type" => "file",
        "placeholder" => "请选择MP4视频文件"
    ],
    [
        "title" => "视频音量",
        "name" => "volume",
        "type" => "input",
        "placeholder" => "0~1，小数",
        "default" => 0.1
    ],
];