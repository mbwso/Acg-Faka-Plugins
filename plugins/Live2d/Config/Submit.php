<?php
declare (strict_types=1);

return [
    [
        "title" => "模型选择",
        "name" => "model",
        "type" => "select",
        "dict" => [
            ["id" => "https://unpkg.com/live2d-widget-model-chitose@1.0.5/assets/chitose.model.json", "name" => "模型1"],
            ["id" => "https://unpkg.com/live2d-widget-model-haruto@1.0.5/assets/haruto.model.json", "name" => "模型2"],
            ["id" => "https://unpkg.com/live2d-widget-model-hibiki@1.0.5/assets/hibiki.model.json", "name" => "模型3"],
            ["id" => "https://unpkg.com/live2d-widget-model-hijiki@1.0.5/assets/hijiki.model.json", "name" => "模型4"],
            ["id" => "https://unpkg.com/live2d-widget-model-izumi@1.0.5/assets/izumi.model.json", "name" => "模型5"],
            ["id" => "https://unpkg.com/live2d-widget-model-koharu@1.0.5/assets/koharu.model.json", "name" => "模型6"],
            ["id" => "https://unpkg.com/live2d-widget-model-miku@1.0.5/assets/miku.model.json", "name" => "模型7"],
            ["id" => "https://unpkg.com/live2d-widget-model-ni-j@1.0.5/assets/ni-j.model.json", "name" => "模型8"],
            ["id" => "https://unpkg.com/live2d-widget-model-shizuku@1.0.5/assets/shizuku.model.json", "name" => "模型9"],
            ["id" => "https://unpkg.com/live2d-widget-model-tororo@1.0.5/assets/tororo.model.json", "name" => "模型10"],
            ["id" => "https://unpkg.com/live2d-widget-model-tsumiki@1.0.5/assets/tsumiki.model.json", "name" => "模型11"],
            ["id" => "https://unpkg.com/live2d-widget-model-unitychan@1.0.5/assets/unitychan.model.json", "name" => "模型12"],
            ["id" => "https://unpkg.com/live2d-widget-model-z16@1.0.5/assets/z16.model.json", "name" => "模型13"],
            ["id" => "https://unpkg.com/live2d-widget-model-nico@1.0.5/assets/nico.model.json", "name" => "模型14"],
            ["id" => "https://unpkg.com/live2d-widget-model-nipsilon@1.0.5/assets/nipsilon.model.json", "name" => "模型15"],
            ["id" => "https://unpkg.com/live2d-widget-model-nito@1.0.5/assets/nito.model.json", "name" => "模型16"],
            ["id" => "https://unpkg.com/live2d-widget-model-wanko@1.0.5/assets/wanko.model.json", "name" => "模型17"],
        ],
        "placeholder" => "请选择你喜欢的人物模型",
        "default" => "https://unpkg.com/live2d-widget-model-shizuku@1.0.5/assets/shizuku.model.json"
    ],
    [
        "title" => "位置",
        "name" => "position",
        "type" => "radio",
        "dict" => [
            ["id" => "left", "name" => "左下角"],
            ["id" => "right", "name" => "右下角"],
            ["id" => "top", "name" => "左上角"]
        ],
        "default" => "left"
    ],
    [
        "title" => "高度",
        "name" => "height",
        "type" => "input",
        "placeholder" => "小人物的身高",
        "default" => 300
    ],
    [
        "title" => "宽度",
        "name" => "width",
        "type" => "input",
        "placeholder" => "小人物的宽度",
        "default" => 150
    ],
    [
        "title" => "横向偏移",
        "name" => "hOffset",
        "type" => "input",
        "placeholder" => "横向偏移，支持负数",
        "default" => 0
    ],
    [
        "title" => "纵向偏移",
        "name" => "vOffset",
        "type" => "input",
        "placeholder" => "纵向偏移，支持负数",
        "default" => -20
    ],
    [
        "title" => "手机展示",
        "name" => "mobile",
        "type" => "switch",
        "text" => "显示",
        "default" => 1
    ],
    [
        "title" => "手机缩放",
        "name" => "mobile_scale",
        "type" => "input",
        "placeholder" => "手机显示时，缩放比例",
        "default" => 0.5
    ]
];