<?php
declare(strict_types=1);

namespace App\Plugin\TelegramBot\Library\Module;

use App\Model\Order as ShopOrder;
use App\Model\User as ShopUser;
use App\Plugin\TelegramBot\Library\State;
use App\Plugin\TelegramBot\Library\TelegramApi;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * 推广分销 —— 复用主程序 user.pid 关系；订单付款时主程序自动结算入账（见 Bind/Order::orderSuccess）。
 * 这里只做 UI：展示推广统计、生成推广链接、给上级推送返利通知。
 */
class Promote
{
    private TelegramApi $api;
    private array $config;

    public function __construct(TelegramApi $api, array $config)
    {
        $this->api = $api;
        $this->config = $config;
    }

    public function showHome(array $tgUser): void
    {
        $uid = (int)($tgUser['user_id'] ?? 0);
        if ($uid <= 0) {
            $this->edit($tgUser, '⚠️ 请先绑定或注册账号后再使用推广分销功能。', [
                'inline_keyboard' => [[['text' => '🔙 主菜单', 'callback_data' => 'main:reload']]],
            ]);
            return;
        }
        $user = ShopUser::query()->find($uid);

        $base = rtrim((string)\App\Model\Config::get('shop_url'), '/');
        if ($base === '') {
            $base = rtrim((string)\App\Util\Client::getUrl(), '/');
        }
        $link = $base . '?promotion_from=' . $uid;

        $subCount = ShopUser::query()->where('pid', $uid)->count();
        $totalDivide = DB::table('order')->where('from', $uid)->where('status', 1)->sum('divide_amount');

        $text = "🤝 推广分销\n\n"
            . "👤 推广人：<b>" . htmlspecialchars((string)$user->username) . "</b>\n"
            . "👥 我的下级：<b>{$subCount}</b> 人\n"
            . "💰 累计返利：¥ " . number_format((float)$totalDivide, 2) . "\n\n"
            . "🔗 您的专属推广链接：\n<code>" . htmlspecialchars($link) . "</code>\n\n"
            . "用户通过该链接进入商城下单成功后，您将自动获得返利。";

        $this->edit($tgUser, $text, [
            'inline_keyboard' => [
                [['text' => '🔗 复制链接', 'url' => $link]],
                [['text' => '🔙 主菜单', 'callback_data' => 'main:reload']],
            ],
        ]);
    }

    /**
     * 给"上级用户"推送返利通知（在订单付款成功 hook 里调用）。
     */
    public function notifyUpstreamRebate(ShopOrder $order): void
    {
        if (empty($this->config['enable_promote'])) return;
        if ((int)$order->from <= 0 || (float)$order->divide_amount <= 0) return;

        $row = DB::table('plugin_telegrambot_user')->where('user_id', (int)$order->from)->first();
        if (!$row) return;

        $msg = "🎉 推广返利到账！\n\n"
            . "📦 订单：<code>{$order->trade_no}</code>\n"
            . "💰 返利金额：¥ " . number_format((float)$order->divide_amount, 2) . "\n"
            . "🎊 感谢您的推广，继续加油！";
        $this->api->sendMessage((int)$row->tg_chat_id, $msg);
    }

    private function edit(array $tgUser, string $text, array $replyMarkup): void
    {
        $chatId = (int)$tgUser['tg_chat_id'];
        $cur = (int)($tgUser['current_message_id'] ?? 0);
        if ($cur > 0) {
            $r = $this->api->editMessageText($chatId, $cur, $text, ['reply_markup' => $replyMarkup]);
            if ($r !== null) return;
        }
        $sent = $this->api->sendMessage($chatId, $text, ['reply_markup' => $replyMarkup]);
        if (is_array($sent) && isset($sent['message_id'])) {
            State::setCurrentMessageId((int)$tgUser['tg_user_id'], (int)$sent['message_id']);
        }
    }
}
