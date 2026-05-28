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
        $err = null;
        if (!empty($cfg['bot_token'])) {
            try {
                $bot = new Bot($cfg);
                $botInfo = $bot->api()->getMe();
            } catch (\Throwable $e) {
                $err = $e->getMessage();
            }
        }
        $offset = (int)(\App\Plugin\TelegramBot\Library\State::kvGet('update_offset') ?: 0);

        return $this->render(
            title:      'Telegram Bot 控制台',
            template:   'Dashboard.html',
            data:       [
                'cfg'     => $cfg,
                'stats'   => $stats,
                'bot'     => $botInfo,
                'err'     => $err,
                'offset'  => $offset,
            ],
            controller: true,
        );
    }
}
