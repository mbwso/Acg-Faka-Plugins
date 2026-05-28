<?php
declare(strict_types=1);

namespace App\Plugin\TelegramBot\Hook;

use App\Consts\Hook;
use App\Controller\Base\View\UserPlugin;
use App\Model\Commodity;
use App\Model\Order;
use App\Model\Pay;
use App\Plugin\TelegramBot\Library\Bot;
use App\Plugin\TelegramBot\Library\Renderer;
use App\Util\Plugin as PluginUtil;
use Illuminate\Database\Capsule\Manager as DB;
use Kernel\Annotation\Hook as HookAttr;
use Kernel\Annotation\Plugin as PluginAttr;
use Kernel\Exception\JSONException;

class Main extends UserPlugin
{
    /** 启用前校验 */
    #[PluginAttr(state: PluginAttr::START)]
    public function onStart(): void
    {
        $cfg = PluginUtil::getConfig('TelegramBot', false);
        if (empty($cfg['bot_token'])) {
            throw new JSONException('启用失败：请先在配置里填写 Bot Token');
        }
        // 自检
        try {
            $bot = new Bot($cfg);
            $me = $bot->api()->getMe();
            if (!is_array($me) || empty($me['username'])) {
                throw new JSONException('Bot Token 校验失败，请检查');
            }
        } catch (\Throwable $e) {
            throw new JSONException('启用失败：' . $e->getMessage());
        }
    }

    #[PluginAttr(state: PluginAttr::STOP)]
    public function onStop(): void {}

    #[PluginAttr(state: PluginAttr::INSTALL)]
    public function onInstall(): void {}

    #[PluginAttr(state: PluginAttr::UPGRADE)]
    public function onUpgrade(): void {}

    /** 订单付款成功 → 给客户推送发货 + 给推广者推送返利 */
    #[HookAttr(point: Hook::USER_API_ORDER_PAY_AFTER)]
    public function onOrderPaid(Commodity $commodity, Order $order, Pay $pay): void
    {
        $cfg = PluginUtil::getConfig('TelegramBot');
        if (empty($cfg['bot_token'])) return;

        // 找 Bot 订单映射
        $row = DB::table('plugin_telegrambot_order')->where('trade_no', $order->trade_no)->first();
        try {
            $bot = new Bot($cfg);
        } catch (\Throwable $e) {
            return;
        }

        if ($row && empty($cfg['enable_pay_notify']) === false) {
            $text = "🎉 支付成功！\n\n"
                . "🧾 订单号：<code>{$order->trade_no}</code>\n"
                . "🛒 商品：" . Renderer::htmlEscape((string)$commodity->name) . "\n"
                . "💰 金额：¥ " . number_format((float)$order->amount, 2) . "\n";
            if ((int)$order->delivery_status === 1 && !empty($order->secret)) {
                $text .= "\n📤 发货内容：\n<code>" . Renderer::htmlEscape((string)$order->secret) . "</code>";
            } else {
                $text .= "\n⏳ 等待人工发货，请耐心等待。";
            }
            $bot->api()->sendMessage((int)$row->tg_chat_id, $text);

            DB::table('plugin_telegrambot_order')->where('trade_no', $order->trade_no)->update(['notified' => 1]);
        }

        // 推广返利
        try {
            $bot->promote()->notifyUpstreamRebate($order);
        } catch (\Throwable) {}

        // 管理群通知
        if (!empty($cfg['notify_admin_new_order']) && !empty($cfg['admin_group_id'])) {
            $alert = "✅ 订单已支付\n\n🧾 <code>{$order->trade_no}</code>\n💰 ¥ " . number_format((float)$order->amount, 2);
            $extra = [];
            if ($row && (int)$row->tg_user_id) {
                $tg = DB::table('plugin_telegrambot_user')->where('tg_user_id', (int)$row->tg_user_id)->first();
                if ($tg && (int)$tg->message_thread_id) {
                    $extra['message_thread_id'] = (int)$tg->message_thread_id;
                }
            }
            $bot->api()->sendMessage((int)$cfg['admin_group_id'], $alert, $extra);
        }
    }

    /** 后台菜单：加一项"Telegram Bot 控制台" */
    #[HookAttr(point: Hook::ADMIN_VIEW_NAV)]
    public function adminNav(): string
    {
        return '<a href="/plugin/TelegramBot/admin/dashboard" class="layui-btn layui-btn-xs layui-btn-warm" target="_blank">📱 TG Bot</a>';
    }
}
