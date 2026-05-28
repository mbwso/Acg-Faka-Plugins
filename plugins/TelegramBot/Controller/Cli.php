<?php
declare(strict_types=1);

namespace App\Plugin\TelegramBot\Controller;

use App\Controller\Base\API\UserPlugin;
use App\Plugin\TelegramBot\Library\Bot;
use App\Util\Plugin as PluginUtil;

/**
 * Long polling 入口 —— 用于 cron 单次轮询或浏览器手动触发。
 * URL: /plugin/telegrambot/cli/poll?token={cron_token}
 *
 * 推荐配合 supervisord 用 CLI 模式：php app/Plugin/TelegramBot/bot.php
 */
class Cli extends UserPlugin
{
    /** 单次拉取 */
    public function poll(): array
    {
        $cfg = PluginUtil::getConfig('TelegramBot');
        $providedToken = (string)($_GET['token'] ?? $_POST['token'] ?? '');
        $cronToken = (string)($cfg['cron_token'] ?? '');
        if ($cronToken === '') {
            // 没设token的情况下，要求请求来自本地
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            if (!in_array($ip, ['127.0.0.1', '::1', 'localhost'], true)) {
                return ['code' => 403, 'msg' => '请先设置 cron_token 配置或从本机访问'];
            }
        } elseif ($providedToken !== $cronToken) {
            return ['code' => 403, 'msg' => 'token 不匹配'];
        }

        @set_time_limit(0);
        @ignore_user_abort(true);

        try {
            $bot = new Bot($cfg);
            $batches = max(1, (int)($_GET['batches'] ?? 5));
            $timeout = max(0, min(30, (int)($_GET['timeout'] ?? 1)));
            $count = $bot->pollOnce($timeout, $batches);
            return ['code' => 200, 'msg' => 'ok', 'data' => ['processed' => $count]];
        } catch (\Throwable $e) {
            return ['code' => 500, 'msg' => $e->getMessage()];
        }
    }

    /** 长驻模式（HTTP 触发，最长 50 秒后退出） */
    public function run(): array
    {
        $cfg = PluginUtil::getConfig('TelegramBot');
        $providedToken = (string)($_GET['token'] ?? $_POST['token'] ?? '');
        $cronToken = (string)($cfg['cron_token'] ?? '');
        if ($cronToken === '' || $providedToken !== $cronToken) {
            return ['code' => 403, 'msg' => 'token 不匹配，仅允许 cli/run 通过 cron_token 触发'];
        }

        @set_time_limit(0);
        @ignore_user_abort(true);

        try {
            $bot = new Bot($cfg);
            $deadline = time() + 50;
            $count = 0;
            while (time() < $deadline) {
                $count += $bot->pollOnce(5, 1);
            }
            return ['code' => 200, 'msg' => 'ok', 'data' => ['processed' => $count]];
        } catch (\Throwable $e) {
            return ['code' => 500, 'msg' => $e->getMessage()];
        }
    }
}
