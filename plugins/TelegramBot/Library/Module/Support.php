<?php
declare(strict_types=1);

namespace App\Plugin\TelegramBot\Library\Module;

use App\Plugin\TelegramBot\Library\Renderer;
use App\Plugin\TelegramBot\Library\State;
use App\Plugin\TelegramBot\Library\TelegramApi;
use App\Util\Date;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * 双向客服（Topic 模式）。
 * 参考 MiHaKun/Telegram-interactive-bot：
 *   - 用户私聊 → Bot → 管理群对应 Topic（copyMessage 不显示来源）
 *   - 群里 Topic 回复 → Bot → 用户（copyMessage 同理）
 *   - 消息双向映射存 plugin_telegrambot_msgmap，支持回复链
 *   - /clear /broadcast /ban /unban 管理命令
 */
class Support
{
    private TelegramApi $api;
    private array $config;
    private int $adminGroupId;

    public function __construct(TelegramApi $api, array $config)
    {
        $this->api = $api;
        $this->config = $config;
        $this->adminGroupId = (int)($config['admin_group_id'] ?? 0);
    }

    public function isEnabled(): bool
    {
        return !empty($this->config['enable_support']) && $this->adminGroupId !== 0;
    }

    /** 用户点击「人工客服」按钮 */
    public function startForUser(array $tgUser): void
    {
        if (!$this->isEnabled()) {
            $this->api->sendMessage($tgUser['tg_chat_id'], '⚠️ 客服尚未开启，请联系管理员配置。');
            return;
        }
        $welcome = (string)($this->config['support_welcome_text'] ?? '已为您接入客服');
        $this->api->sendMessage($tgUser['tg_chat_id'], $welcome);
    }

    /** 用户私聊 → 镜像到群 Topic */
    public function forwardUserToAdmin(array $tgUser, array $message): void
    {
        if (!$this->isEnabled()) return;
        if (!empty($tgUser['is_banned'])) return;

        // 限频
        $limit = (int)($this->config['rate_limit_seconds'] ?? 2);
        if (State::rateLimited($tgUser, $limit)) {
            $this->api->sendMessage($tgUser['tg_chat_id'], '⏱ 您发送消息过于频繁，请稍等。');
            return;
        }
        State::touchLastMsg((int)$tgUser['tg_user_id']);

        // 找/建 Topic
        $threadId = (int)($tgUser['message_thread_id'] ?? 0);
        if ($threadId <= 0) {
            $name = trim((string)($tgUser['first_name'] ?? '') . ' ' . (string)($tgUser['last_name'] ?? ''));
            if ($name === '') $name = (string)($tgUser['username'] ?? 'user');
            $name .= '|' . $tgUser['tg_user_id'];
            $r = $this->api->createForumTopic($this->adminGroupId, $name);
            if (!is_array($r) || !isset($r['message_thread_id'])) {
                $this->api->sendMessage($tgUser['tg_chat_id'], '❌ 客服系统暂不可用，请稍后再试。');
                return;
            }
            $threadId = (int)$r['message_thread_id'];
            State::update((int)$tgUser['tg_user_id'], ['message_thread_id' => $threadId]);

            // 发联系人卡片
            $bindMark = (int)($tgUser['user_id'] ?? 0) > 0 ? '✅ 已绑定 user_id=' . $tgUser['user_id'] : '👤 游客';
            $uname = $tgUser['username'] ? '@' . $tgUser['username'] : '(无 username)';
            $card = "🆕 新客户接入\n姓名：" . Renderer::htmlEscape(trim((string)($tgUser['first_name'] ?? '') . ' ' . (string)($tgUser['last_name'] ?? '')))
                . "\n账号：{$uname}\nTG ID：<code>{$tgUser['tg_user_id']}</code>\n状态：{$bindMark}";
            $this->api->sendMessage($this->adminGroupId, $card, ['message_thread_id' => $threadId]);
        }

        // reply 链
        $copyExtra = ['message_thread_id' => $threadId];
        if (isset($message['reply_to_message']['message_id'])) {
            $row = DB::table('plugin_telegrambot_msgmap')
                ->where('user_chat_message_id', (int)$message['reply_to_message']['message_id'])
                ->where('tg_user_id', (int)$tgUser['tg_user_id'])
                ->first();
            if ($row) {
                $copyExtra['reply_parameters'] = ['message_id' => (int)$row->group_chat_message_id];
            }
        }

        $sent = $this->api->copyMessage(
            $this->adminGroupId,
            $tgUser['tg_chat_id'],
            (int)$message['message_id'],
            $copyExtra
        );

        if (is_array($sent) && isset($sent['message_id'])) {
            DB::table('plugin_telegrambot_msgmap')->insert([
                'tg_user_id'            => (int)$tgUser['tg_user_id'],
                'user_chat_message_id'  => (int)$message['message_id'],
                'group_chat_message_id' => (int)$sent['message_id'],
                'create_time'           => Date::current(),
            ]);
        }
    }

    /** 群里 Topic 回复 → 镜像给用户 */
    public function forwardAdminToUser(array $message): void
    {
        if (!$this->isEnabled()) return;
        $chatId = (int)($message['chat']['id'] ?? 0);
        if ($chatId !== $this->adminGroupId) return;

        $threadId = (int)($message['message_thread_id'] ?? 0);
        if ($threadId <= 0) return; // 忽略 General 顶层消息

        $tgUser = State::findByThread($threadId);
        if (!$tgUser) return;

        // 管理员命令
        $text = (string)($message['text'] ?? '');
        if ($text !== '' && str_starts_with($text, '/')) {
            $cmd = strtolower(trim(strtok($text, ' @') ?: ''));
            if (in_array($cmd, ['/ban', '/unban', '/clear', '/info'], true) && $this->isAdmin((int)$message['from']['id'])) {
                $this->handleAdminCommand($cmd, $tgUser, $message);
                return;
            }
        }

        $extra = [];
        if (isset($message['reply_to_message']['message_id'])) {
            $row = DB::table('plugin_telegrambot_msgmap')
                ->where('group_chat_message_id', (int)$message['reply_to_message']['message_id'])
                ->first();
            if ($row) {
                $extra['reply_parameters'] = ['message_id' => (int)$row->user_chat_message_id];
            }
        }

        $sent = $this->api->copyMessage(
            (int)$tgUser['tg_chat_id'],
            $this->adminGroupId,
            (int)$message['message_id'],
            $extra
        );

        if (is_array($sent) && isset($sent['message_id'])) {
            DB::table('plugin_telegrambot_msgmap')->insert([
                'tg_user_id'            => (int)$tgUser['tg_user_id'],
                'user_chat_message_id'  => (int)$sent['message_id'],
                'group_chat_message_id' => (int)$message['message_id'],
                'create_time'           => Date::current(),
            ]);
        }
    }

    public function isAdmin(int $tgUserId): bool
    {
        $raw = (string)($this->config['admin_user_ids'] ?? '');
        if ($raw === '') return false;
        $ids = array_filter(array_map('intval', preg_split('/[,\s]+/', $raw)));
        return in_array($tgUserId, $ids, true);
    }

    private function handleAdminCommand(string $cmd, array $tgUser, array $message): void
    {
        $threadId = (int)$message['message_thread_id'];
        switch ($cmd) {
            case '/ban':
                State::update((int)$tgUser['tg_user_id'], ['is_banned' => 1]);
                $this->api->sendMessage($this->adminGroupId, "🚫 已封禁 TG ID {$tgUser['tg_user_id']}", ['message_thread_id' => $threadId]);
                break;
            case '/unban':
                State::update((int)$tgUser['tg_user_id'], ['is_banned' => 0]);
                $this->api->sendMessage($this->adminGroupId, "✅ 已解禁 TG ID {$tgUser['tg_user_id']}", ['message_thread_id' => $threadId]);
                break;
            case '/clear':
                $rows = DB::table('plugin_telegrambot_msgmap')
                    ->where('tg_user_id', (int)$tgUser['tg_user_id'])->get();
                foreach ($rows as $r) {
                    $this->api->deleteMessage((int)$tgUser['tg_chat_id'], (int)$r->user_chat_message_id);
                }
                DB::table('plugin_telegrambot_msgmap')->where('tg_user_id', (int)$tgUser['tg_user_id'])->delete();
                $this->api->deleteForumTopic($this->adminGroupId, $threadId);
                State::update((int)$tgUser['tg_user_id'], ['message_thread_id' => 0]);
                break;
            case '/info':
                $info = "TG ID: <code>{$tgUser['tg_user_id']}</code>\n"
                    . "用户名: " . ($tgUser['username'] ? '@' . $tgUser['username'] : '-') . "\n"
                    . "姓名: " . Renderer::htmlEscape(trim((string)($tgUser['first_name'] ?? '') . ' ' . (string)($tgUser['last_name'] ?? ''))) . "\n"
                    . "已绑定 user_id: " . ($tgUser['user_id'] ?: '未绑定') . "\n"
                    . "封禁: " . ($tgUser['is_banned'] ? '是' : '否');
                $this->api->sendMessage($this->adminGroupId, $info, ['message_thread_id' => $threadId]);
                break;
        }
    }
}
