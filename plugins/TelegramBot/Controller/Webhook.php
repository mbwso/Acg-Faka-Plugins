<?php
declare(strict_types=1);

namespace App\Plugin\TelegramBot\Controller;

use App\Controller\Base\API\UserPlugin;
use App\Plugin\TelegramBot\Library\Bot;
use App\Util\Plugin as PluginUtil;

/**
 * Telegram Webhook 接收端。
 * URL: https://你的站点/plugin/TelegramBot/webhook/recv
 *
 * 启用插件时插件自动给 Telegram 注册这个 URL，之后每条 update 都会被 Telegram POST 到这里。
 * 不需要任何常驻进程。
 */
class Webhook extends UserPlugin
{
    public function recv(): array
    {
        $cfg = PluginUtil::getConfig('TelegramBot');
        if (empty($cfg['bot_token'])) {
            return $this->json(403, 'bot not configured');
        }

        // secret_token 防伪造（启用插件时随机生成，写入 Telegram + 本地配置）
        $expected = (string)($cfg['webhook_secret'] ?? '');
        if ($expected !== '') {
            $got = (string)($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
            if (!hash_equals($expected, $got)) {
                return $this->json(403, 'invalid secret');
            }
        }

        $raw = file_get_contents('php://input') ?: '';
        $update = json_decode($raw, true);
        if (!is_array($update) || empty($update)) {
            return $this->json(400, 'empty body');
        }

        try {
            $bot = new Bot($cfg);
            $bot->dispatch($update);
        } catch (\Throwable $e) {
            PluginUtil::log('TelegramBot', 'webhook err: ' . $e->getMessage());
            // 返回 200 防止 Telegram 重试堵塞
        }

        return $this->json(200, 'ok');
    }

    /** 健康检查 GET /plugin/TelegramBot/webhook/info */
    public function info(): array
    {
        $cfg = PluginUtil::getConfig('TelegramBot');
        if (empty($cfg['bot_token'])) {
            return $this->json(403, 'bot not configured');
        }
        try {
            $bot = new Bot($cfg);
            $r = $bot->api()->getWebhookInfo();
            return $this->json(200, 'ok', $r ?: []);
        } catch (\Throwable $e) {
            return $this->json(500, $e->getMessage());
        }
    }
}
