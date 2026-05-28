<?php
declare(strict_types=1);

namespace App\Plugin\TelegramBot\Library;

use App\Plugin\TelegramBot\Library\Module\Account;
use App\Plugin\TelegramBot\Library\Module\Orders;
use App\Plugin\TelegramBot\Library\Module\Promote;
use App\Plugin\TelegramBot\Library\Module\Shop;
use App\Plugin\TelegramBot\Library\Module\Support;
use App\Util\Plugin as PluginUtil;

class Bot
{
    public const PLUGIN = 'TelegramBot';

    private TelegramApi $api;
    private array $config;
    private Shop $shop;
    private Account $account;
    private Orders $orders;
    private Support $support;
    private Promote $promote;
    private int $adminGroupId;

    public function __construct(array $config)
    {
        $token = (string)($config['bot_token'] ?? '');
        if ($token === '') {
            throw new \RuntimeException('Bot Token 未配置');
        }
        $this->config = $config;
        $sslVerify = empty($config['disable_ssl_verify']);
        $this->api = new TelegramApi($token, $sslVerify, self::PLUGIN);
        $this->shop = new Shop($this->api, $config);
        $this->account = new Account($this->api, $config);
        $this->orders = new Orders($this->api);
        $this->support = new Support($this->api, $config);
        $this->promote = new Promote($this->api, $config);
        $this->adminGroupId = (int)($config['admin_group_id'] ?? 0);
    }

    public function api(): TelegramApi { return $this->api; }
    public function support(): Support { return $this->support; }
    public function promote(): Promote { return $this->promote; }

    /** 主循环（CLI 模式） */
    public function runForever(): void
    {
        $offset = (int)(State::kvGet('update_offset') ?: 0);
        PluginUtil::log(self::PLUGIN, "Bot 启动，offset={$offset}");
        while (true) {
            try {
                $updates = $this->api->getUpdates($offset, 30, ['message', 'edited_message', 'callback_query']);
                foreach ($updates as $u) {
                    $offset = (int)$u['update_id'] + 1;
                    $this->dispatch($u);
                }
                State::kvSet('update_offset', (string)$offset);
            } catch (\Throwable $e) {
                PluginUtil::log(self::PLUGIN, '主循环异常: ' . $e->getMessage());
                sleep(3);
            }
        }
    }

    /** 单批拉取（HTTP/cron 模式） */
    public function pollOnce(int $timeout = 1, int $maxBatches = 1): int
    {
        $offset = (int)(State::kvGet('update_offset') ?: 0);
        $count = 0;
        for ($i = 0; $i < $maxBatches; $i++) {
            $updates = $this->api->getUpdates($offset, $timeout, ['message', 'edited_message', 'callback_query']);
            if (!$updates) break;
            foreach ($updates as $u) {
                $offset = (int)$u['update_id'] + 1;
                $this->dispatch($u);
                $count++;
            }
        }
        State::kvSet('update_offset', (string)$offset);
        return $count;
    }

    public function dispatch(array $u): void
    {
        try {
            if (isset($u['callback_query'])) {
                $this->onCallback($u['callback_query']);
                return;
            }
            if (isset($u['message'])) {
                $this->onMessage($u['message']);
                return;
            }
        } catch (\Throwable $e) {
            PluginUtil::log(self::PLUGIN, 'dispatch err: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    private function onMessage(array $msg): void
    {
        $chat = $msg['chat'] ?? [];
        $chatType = (string)($chat['type'] ?? '');

        // 管理群消息 → 客服 A2U
        if ($chatType !== 'private') {
            if ((int)($chat['id'] ?? 0) === $this->adminGroupId) {
                $this->support->forwardAdminToUser($msg);
            }
            return;
        }

        // 私聊：先 upsert tg_user
        $from = $msg['from'] ?? [];
        if (!$from) return;
        $tgUserId = (int)($from['id'] ?? 0);
        $chatId = (int)($chat['id'] ?? 0);
        $tgUser = State::ensure($tgUserId, $chatId, $from);

        $text = (string)($msg['text'] ?? '');

        // /start 命令
        if ($text === '/start' || preg_match('/^\/start(@\S+)?$/', $text)) {
            State::setState($tgUserId, null);
            $this->sendMainMenu($tgUser);
            return;
        }
        if ($text === '/menu') {
            $this->sendMainMenu($tgUser);
            return;
        }
        if ($text === '/cancel') {
            State::setState($tgUserId, null);
            $this->api->sendMessage($chatId, '已取消当前操作。');
            return;
        }

        // 状态机处理：注册/绑定
        if ($this->account->handleText($tgUser, $text)) return;

        // 状态机处理：购物（contact / num）
        $state = (string)($tgUser['state'] ?? '');
        if (in_array($state, ['await_contact', 'await_num'], true) && $text !== '') {
            $this->shop->handleText($tgUser, $text);
            return;
        }

        // 默认：转发给客服
        if ($this->support->isEnabled()) {
            $this->support->forwardUserToAdmin($tgUser, $msg);
        } else {
            $this->api->sendMessage($chatId, '收到您的消息。客服功能未启用，建议发送 /start 进入菜单。');
        }
    }

    private function onCallback(array $cb): void
    {
        $from = $cb['from'] ?? [];
        $msg = $cb['message'] ?? [];
        $chat = $msg['chat'] ?? [];
        $chatId = (int)($chat['id'] ?? 0);
        $tgUserId = (int)($from['id'] ?? 0);

        $tgUser = State::ensure($tgUserId, $chatId, $from);
        // 同步当前消息id（callback总是针对当前菜单）
        $mid = (int)($msg['message_id'] ?? 0);
        if ($mid > 0 && (int)($tgUser['current_message_id'] ?? 0) !== $mid) {
            State::setCurrentMessageId($tgUserId, $mid);
            $tgUser['current_message_id'] = $mid;
        }

        $data = (string)($cb['data'] ?? '');
        $this->api->answerCallbackQuery((string)$cb['id']);
        $this->route($tgUser, $data);
    }

    private function route(array $tgUser, string $data): void
    {
        if ($data === '' || $data === 'noop') return;

        if ($data === 'main:reload') {
            State::setState((int)$tgUser['tg_user_id'], null);
            $this->sendMainMenu($tgUser, true);
            return;
        }

        $parts = explode(':', $data, 4);
        $mod = $parts[0] ?? '';
        $act = $parts[1] ?? '';
        $arg = $parts[2] ?? '';
        $arg2 = $parts[3] ?? '';

        try {
            switch ($mod) {
                case 'shop':
                    $this->routeShop($tgUser, $act, $arg, $arg2);
                    break;
                case 'orders':
                    $this->routeOrders($tgUser, $act, $arg);
                    break;
                case 'account':
                    $this->routeAccount($tgUser, $act);
                    break;
                case 'support':
                    $this->support->startForUser($tgUser);
                    break;
                case 'promote':
                    $this->promote->showHome($tgUser);
                    break;
            }
        } catch (\Throwable $e) {
            PluginUtil::log(self::PLUGIN, "route[{$data}] error: " . $e->getMessage());
            $this->api->sendMessage($tgUser['tg_chat_id'], '⚠️ 出现异常：' . $e->getMessage());
        }
    }

    private function routeShop(array $tgUser, string $act, string $arg, string $arg2): void
    {
        switch ($act) {
            case 'categories':
                $this->shop->showCategories($tgUser);
                break;
            case 'category':
                $this->shop->showCommodityList($tgUser, (int)$arg);
                break;
            case 'item':
                $this->shop->showCommodityDetail($tgUser, (int)$arg);
                break;
            case 'race':
                $p = Renderer::b64decode($arg);
                if ($p) $this->shop->chooseRace($tgUser, (int)$p['c'], (string)$p['r']);
                break;
            case 'sku':
                $p = Renderer::b64decode($arg);
                if ($p) $this->shop->chooseSku($tgUser, (int)$p['c'], (string)$p['k'], (string)$p['v']);
                break;
            case 'pay':
                $p = Renderer::b64decode($arg);
                if ($p) $this->shop->choosePay($tgUser, (int)$p['c'], (int)$p['p']);
                break;
            case 'contact':
                $this->shop->askContact($tgUser, (int)$arg);
                break;
            case 'num':
                $this->shop->askNum($tgUser, (int)$arg);
                break;
            case 'draft':
                $this->shop->askDraft($tgUser, (int)$arg);
                break;
            case 'draftpick':
                $u = \App\Plugin\TelegramBot\Library\Module\Shop::unpack($arg);
                $this->shop->pickDraft($tgUser, (int)$u['c'], (int)$u['card']);
                break;
            case 'checkout':
                $r = $this->shop->checkout($tgUser, (int)$arg);
                if (!$r['ok']) {
                    $this->api->sendMessage($tgUser['tg_chat_id'], $r['msg']);
                    return;
                }
                $this->showCheckoutResult($tgUser, $r);
                break;
        }
    }

    private function routeOrders(array $tgUser, string $act, string $arg): void
    {
        switch ($act) {
            case 'menu':
                $this->orders->showMenu($tgUser);
                break;
            case 'range':
                $this->orders->showRange($tgUser, (int)$arg);
                break;
            case 'detail':
            case 'refresh':
                $this->orders->showDetail($tgUser, $arg);
                break;
        }
    }

    private function routeAccount(array $tgUser, string $act): void
    {
        switch ($act) {
            case 'bind':
                $this->account->startBind($tgUser);
                break;
            case 'register':
                $this->account->startRegister($tgUser);
                break;
            case 'unbind':
                $this->account->unbind($tgUser);
                break;
            case 'web':
                $url = $this->account->generateWebLoginUrl($tgUser);
                if ($url) {
                    $this->api->sendMessage(
                        $tgUser['tg_chat_id'],
                        "🌐 一键登录网页端，10 分钟内有效：\n<a href=\"" . htmlspecialchars($url) . "\">点击此处直接登录</a>",
                        ['reply_markup' => ['inline_keyboard' => [[['text' => '🌐 点击打开', 'url' => $url]]]]]
                    );
                } else {
                    $this->api->sendMessage($tgUser['tg_chat_id'], '⚠️ 请先绑定账号');
                }
                break;
        }
    }

    private function showCheckoutResult(array $tgUser, array $result): void
    {
        $url = (string)$result['url'];
        $tradeNo = (string)$result['tradeNo'];
        $amount = number_format((float)$result['amount'], 2);

        $text = "✅ 下单成功！\n\n"
            . "🧾 订单号：<code>{$tradeNo}</code>\n"
            . "💰 应付金额：¥ {$amount}\n\n"
            . "请点击下方按钮前往支付。支付成功后将自动推送发货信息。";

        // 处理本地渲染 / submit 类型 URL（以 / 开头时需要补域名）
        if (str_starts_with($url, '/')) {
            $base = rtrim((string)\App\Util\Client::getUrl(), '/');
            $url = $base . $url;
        }

        $this->api->sendMessage($tgUser['tg_chat_id'], $text, [
            'reply_markup' => [
                'inline_keyboard' => [
                    [['text' => '💳 立即支付', 'url' => $url]],
                    [['text' => '🔄 查询订单状态', 'callback_data' => 'orders:detail:' . $tradeNo]],
                    [['text' => '🔙 主菜单', 'callback_data' => 'main:reload']],
                ],
            ],
        ]);

        // 通知管理群
        if (!empty($this->config['notify_admin_new_order']) && $this->adminGroupId !== 0) {
            $name = trim((string)($tgUser['first_name'] ?? '') . ' ' . (string)($tgUser['last_name'] ?? ''));
            $uname = $tgUser['username'] ? '@' . $tgUser['username'] : '-';
            $alert = "🛒 新订单\n\n"
                . "🧾 {$tradeNo}\n"
                . "👤 " . htmlspecialchars($name) . " ({$uname}) TG:<code>{$tgUser['tg_user_id']}</code>\n"
                . "💰 ¥ {$amount}";
            $threadId = (int)($tgUser['message_thread_id'] ?? 0);
            $extra = $threadId > 0 ? ['message_thread_id' => $threadId] : [];
            $this->api->sendMessage($this->adminGroupId, $alert, $extra);
        }
    }

    private function sendMainMenu(array $tgUser, bool $editIfPossible = false): void
    {
        $name = trim((string)($tgUser['first_name'] ?? '') . ' ' . (string)($tgUser['last_name'] ?? ''));
        if ($name === '') $name = (string)($tgUser['username'] ?? '朋友');

        $shopUser = State::getShopUser($tgUser);
        $welcome = (string)($this->config['welcome_text'] ?? '欢迎使用智能购物机器人');

        $msg = $shopUser
            ? Renderer::mainMenuMember($name, (string)$shopUser->username, (float)$shopUser->balance, $welcome)
            : Renderer::mainMenuGuest($name, $welcome);

        $chatId = (int)$tgUser['tg_chat_id'];
        $curMsgId = (int)($tgUser['current_message_id'] ?? 0);
        if ($editIfPossible && $curMsgId > 0) {
            $r = $this->api->editMessageText($chatId, $curMsgId, $msg['text'], ['reply_markup' => $msg['reply_markup']]);
            if ($r !== null) return;
        }
        $sent = $this->api->sendMessage($chatId, $msg['text'], ['reply_markup' => $msg['reply_markup']]);
        if (is_array($sent) && isset($sent['message_id'])) {
            State::setCurrentMessageId((int)$tgUser['tg_user_id'], (int)$sent['message_id']);
        }
    }
}
