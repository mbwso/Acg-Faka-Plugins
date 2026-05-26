<?php
declare (strict_types=1);

return [
    [
        "title" => "特別説明",
        "name" => "explain",
        "type" => "explain",
        "placeholder" => '<b style="color:red;">這個平臺僅支持整數金額，如果一訂單出現小數點，將無法回調成功。</b>'
    ],
    [
        "title" => "下單網関",
        "name" => "url",
        "type" => "input",
        "placeholder" => "下單網關，只需要填寫：https://xxx.com，不需要/后的路徑"
    ],
    [
        "title" => "商店代號",
        "name" => "merchantId",
        "type" => "input",
        "placeholder" => "商店代號"
    ],
    [
        "title" => "HashKey",
        "name" => "aes_key",
        "type" => "input",
        "placeholder" => "HashKey"
    ],
    [
        "title" => "HashIV",
        "name" => "aes_iv",
        "type" => "input",
        "placeholder" => "HashIV"
    ],
];