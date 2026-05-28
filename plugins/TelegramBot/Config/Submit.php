<?php
declare(strict_types=1);

return [
    [
        "title"       => "Bot Token",
        "name"        => "bot_token",
        "type"        => "input",
        "placeholder" => "格式如 123456789:AAH...，从 @BotFather 获取",
    ],
    [
        "title"       => "客服管理群 ID",
        "name"        => "admin_group_id",
        "type"        => "input",
        "placeholder" => "形如 -1001234567890；需开启 Topic 的 supergroup 且 Bot 是管理员",
    ],
    [
        "title"       => "管理员 Telegram ID（多个用英文逗号分隔）",
        "name"        => "admin_user_ids",
        "type"        => "input",
        "placeholder" => "如：111111,222222，用于执行 /clear /broadcast 等管理命令",
    ],
    [
        "title" => "启用双向客服（Topic 模式）",
        "name"  => "enable_support",
        "type"  => "switch",
        "default" => "1",
    ],
    [
        "title" => "启用推广分销返利推送",
        "name"  => "enable_promote",
        "type"  => "switch",
        "default" => "1",
    ],
    [
        "title" => "支付成功后自动推送订单详情给用户",
        "name"  => "enable_pay_notify",
        "type"  => "switch",
        "default" => "1",
    ],
    [
        "title" => "新订单同步推送到管理群",
        "name"  => "notify_admin_new_order",
        "type"  => "switch",
        "default" => "1",
    ],
    [
        "title" => "支持的支付通道（按 ID，留空=全部启用商品支付的通道）",
        "name"  => "pay_ids",
        "type"  => "input",
        "placeholder" => "多个用逗号分隔，如：1,2,3",
    ],
    [
        "title" => "Bot 欢迎语",
        "name"  => "welcome_text",
        "type"  => "textarea",
        "default" => "你好，{name}，欢迎使用智能购物机器人，我们将在这里有一个美好的体验！\n\n⚠️您当前为游客身份，绑定账号后可享受更多优惠。点击下方菜单中的「注册账号」按钮，即可一键完成快速注册！",
    ],
    [
        "title" => "客户首次接入客服时的提示文案",
        "name"  => "support_welcome_text",
        "type"  => "textarea",
        "default" => "✨️ 尊敬的客户，您好！目前已为您接入人工客服，您可以直接在此与我们进行沟通。请您详细描述您遇到的问题，我们将尽快为您处理。",
    ],
    [
        "title" => "消息限频（秒，0=关闭）",
        "name"  => "rate_limit_seconds",
        "type"  => "number",
        "default" => "2",
    ],
    [
        "title" => "禁用 SSL 证书校验（部分服务器需要）",
        "name"  => "disable_ssl_verify",
        "type"  => "switch",
        "default" => "0",
    ],
    [
        "title" => "Cron Token（仅当用 HTTP 拉取模式启动 Bot 时需要）",
        "name"  => "cron_token",
        "type"  => "input",
        "placeholder" => "建议填一串随机字符；用 supervisord / CLI 启动可留空",
    ],
];
