<?php
declare(strict_types=1);

namespace App\Plugin\TelegramBot\Controller;

use App\Controller\Base\View\ManagePlugin;
use App\Interceptor\ManageSession;
use App\Plugin\TelegramBot\Library\Bot;
use App\Util\Plugin as PluginUtil;
use Illuminate\Database\Capsule\Manager as DB;
use Kernel\Annotation\Interceptor;
use Kernel\Exception\ViewException;

#[Interceptor(ManageSession::class)]
class Admin extends ManagePlugin
{
    /**
     * @throws ViewException
     */
    public function dashboard(): string
    {
        $cfg = PluginUtil::getConfig('TelegramBot');
        $stats = [
            'tg_users'   => DB::table('plugin_telegrambot_user')->count(),
            'bound'      => DB::table('plugin_telegrambot_user')->where('user_id', '>', 0)->count(),
            'orders'     => DB::table('plugin_telegrambot_order')->count(),
            'topics'     => DB::table('plugin_telegrambot_user')->where('message_thread_id', '>', 0)->count(),
            'msg_maps'   => DB::table('plugin_telegrambot_msgmap')->count(),
        ];
        $botInfo = null;
        $webhookInfo = null;
        $err = null;
        if (!empty($cfg['bot_token'])) {
            try {
                $bot = new Bot($cfg);
                $botInfo = $bot->api()->getMe();
                $webhookInfo = $bot->api()->getWebhookInfo();
            } catch (\Throwable $e) {
                $err = $e->getMessage();
            }
        }
        $offset = (int)(\App\Plugin\TelegramBot\Library\State::kvGet('update_offset') ?: 0);

        return $this->render(
            title:      'Telegram Bot 控制台',
            template:   'Dashboard.html',
            data:       [
                'cfg'         => $cfg,
                'stats'       => $stats,
                'bot'         => $botInfo,
                'webhookInfo' => $webhookInfo,
                'err'         => $err,
                'offset'      => $offset,
                'mode'        => (string)($cfg['run_mode'] ?? 'webhook'),
            ],
            controller: true,
        );
    }

    /**
     * @throws ViewException
     */
    public function wiki(): string
    {
        $cfg = PluginUtil::getConfig('TelegramBot');
        $base = trim((string)($cfg['webhook_domain'] ?? '')) ?: (string)\App\Model\Config::get('shop_url') ?: (string)\App\Model\Config::get('callback_domain');
        $base = rtrim((string)$base, '/');

        return $this->render(
            title:      'Telegram Bot 使用文档',
            template:   'Wiki.html',
            data:       [
                'cfg'           => $cfg,
                'webhookUrl'    => $base ? ($base . '/plugin/TelegramBot/webhook/recv') : '（请先填写 webhook_domain 或站点配置）',
                'mode'          => (string)($cfg['run_mode'] ?? 'webhook'),
            ],
            controller: true,
        );
    }
}
