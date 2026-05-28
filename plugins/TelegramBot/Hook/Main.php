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
use App\Plugin\TelegramBot\Library\TelegramApi;
use App\Util\Plugin as PluginUtil;
use Illuminate\Database\Capsule\Manager as DB;
use Kernel\Annotation\Hook as HookAttr;
use Kernel\Annotation\Plugin as PluginAttr;
use Kernel\Exception\JSONException;

class Main extends UserPlugin
{
    public const PLUGIN_KEY = 'TelegramBot';

    /** 启用：自检 + 注册 webhook */
    #[PluginAttr(state: PluginAttr::START)]
    public function onStart(): void
    {
        $cfg = PluginUtil::getConfig(self::PLUGIN_KEY, false);
        if (empty($cfg['bot_token'])) {
            throw new JSONException('启用失败：请先在配置里填写 Bot Token');
        }

        try {
            $bot = new Bot($cfg);
            $me = $bot->api()->getMe();
            if (!is_array($me) || empty($me['username'])) {
                throw new JSONException('Bot Token 校验失败，请检查');
            }
        } catch (\Throwable $e) {
            throw new JSONException('启用失败：' . $e->getMessage());
        }

        // 默认 webhook 模式；用户显式设置 polling 才退出 webhook 流程
        $mode = (string)($cfg['run_mode'] ?? 'webhook');
        if ($mode === 'webhook') {
            $this->ensureWebhookRegistered($cfg);
        } else {
            // 切到 polling 模式时，主动 deleteWebhook 避免漏消息
            try { $bot->api()->deleteWebhook(); } catch (\Throwable) {}
        }
    }

    /** 停用：取消 webhook，避免 TG 继续推送到已关闭的入口 */
    #[PluginAttr(state: PluginAttr::STOP)]
    public function onStop(): void
    {
        $cfg = PluginUtil::getConfig(self::PLUGIN_KEY, false);
        if (empty($cfg['bot_token'])) return;
        try {
            $bot = new Bot($cfg);
            $bot->api()->deleteWebhook();
        } catch (\Throwable) {}
    }

    /** 后台保存配置后：根据新 mode 重新注册/取消 webhook */
    #[PluginAttr(state: PluginAttr::SAVE_CONFIG)]
    public function onSaveConfig(): void
    {
        $cfg = PluginUtil::getConfig(self::PLUGIN_KEY, false);
        if (empty($cfg['bot_token'])) return;
        $mode = (string)($cfg['run_mode'] ?? 'webhook');

        try {
            $bot = new Bot($cfg);
            if ($mode === 'webhook') {
                $this->ensureWebhookRegistered($cfg);
            } else {
                $bot->api()->deleteWebhook();
            }
        } catch (\Throwable $e) {
            PluginUtil::log(self::PLUGIN_KEY, 'SAVE_CONFIG webhook err: ' . $e->getMessage());
        }
    }

    #[PluginAttr(state: PluginAttr::INSTALL)]
    public function onInstall(): void {}

    #[PluginAttr(state: PluginAttr::UPGRADE)]
    public function onUpgrade(): void {}

    /**
     * 注册 webhook：
     * 1. URL = shop_url 或 callback_domain + /plugin/TelegramBot/webhook/recv
     * 2. secret_token = 第一次生成后写回 Config.php（用于校验 X-Telegram-Bot-Api-Secret-Token）
     */
    private function ensureWebhookRegistered(array $cfg): void
    {
        $base = $this->resolveSiteUrl();
        if ($base === '') {
            throw new JSONException(
                '启用失败：未能确定站点 URL。请先在「网站设置 → 回调域名 / 站点 URL」中配置 https:// 开头的公网域名，再启用本插件。'
            );
        }
        if (stripos($base, 'https://') !== 0) {
            throw new JSONException("启用失败：Telegram Webhook 仅支持 HTTPS。当前站点 URL：{$base}。请改用 HTTPS 后再启用，或在配置里把「运行模式」改为 polling 并通过 CLI 启动。");
        }

        // secret token：首次自动生成
        $secret = (string)($cfg['webhook_secret'] ?? '');
        if ($secret === '' || !preg_match('/^[A-Za-z0-9_-]{16,}$/', $secret)) {
            $secret = bin2hex(random_bytes(16));
            PluginUtil::setConfig(self::PLUGIN_KEY, 'webhook_secret', $secret);
        }

        $url = rtrim($base, '/') . '/plugin/TelegramBot/webhook/recv';
        $bot = new Bot(array_merge($cfg, ['webhook_secret' => $secret]));

        try {
            $bot->api()->setWebhook($url, $secret, ['message', 'edited_message', 'callback_query']);
        } catch (\Throwable $e) {
            throw new JSONException('启用失败：Telegram setWebhook 拒绝 — ' . $e->getMessage()
                . "。请确认 {$url} 已通过公网 HTTPS 可访问。");
        }

        PluginUtil::log(self::PLUGIN_KEY, "已注册 webhook：{$url}");
    }

    private function resolveSiteUrl(): string
    {
        // 优先级：插件配置 webhook_domain > 系统配置 shop_url > callback_domain > Client::getUrl()
        $cfg = PluginUtil::getConfig(self::PLUGIN_KEY, false);
        $url = trim((string)($cfg['webhook_domain'] ?? ''));
        if ($url === '') $url = (string)\App\Model\Config::get('shop_url');
        if ($url === '') $url = (string)\App\Model\Config::get('callback_domain');
        if ($url === '') {
            try {
                $url = (string)\App\Util\Client::getUrl();
            } catch (\Throwable) { $url = ''; }
        }
        return rtrim($url, '/');
    }

    /** 订单付款成功 → 给客户推送发货 + 给推广者推送返利 */
    #[HookAttr(point: Hook::USER_API_ORDER_PAY_AFTER)]
    public function onOrderPaid(Commodity $commodity, Order $order, Pay $pay): void
    {
        $cfg = PluginUtil::getConfig(self::PLUGIN_KEY);
        if (empty($cfg['bot_token'])) return;

        $row = DB::table('plugin_telegrambot_order')->where('trade_no', $order->trade_no)->first();
        try {
            $bot = new Bot($cfg);
        } catch (\Throwable $e) {
            return;
        }

        if ($row && !empty($cfg['enable_pay_notify'])) {
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

        try {
            $bot->promote()->notifyUpstreamRebate($order);
        } catch (\Throwable) {}

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
