<?php
declare(strict_types=1);

namespace App\Plugin\TelegramBot\Library;

use App\Util\Http;
use App\Util\Plugin as PluginUtil;

/**
 * 轻量级 Telegram Bot API 客户端。
 * 不依赖第三方 SDK，全部用 Guzzle 直接调 https://api.telegram.org/bot{token}/{method}。
 */
class TelegramApi
{
    private string $token;
    private bool $sslVerify;
    /** @var \GuzzleHttp\Client */
    private $client;
    private string $pluginName;

    public function __construct(string $token, bool $sslVerify = true, string $pluginName = 'TelegramBot')
    {
        $this->token = $token;
        $this->sslVerify = $sslVerify;
        $this->pluginName = $pluginName;
        $this->client = Http::make([
            'timeout' => 60,
            'verify'  => $sslVerify,
            'headers' => ['Accept' => 'application/json'],
        ]);
    }

    /**
     * 调用 Bot API 方法，返回 result 字段；失败抛 \RuntimeException。
     */
    public function call(string $method, array $params = [], int $timeout = 35): mixed
    {
        $url = "https://api.telegram.org/bot{$this->token}/{$method}";
        $options = [
            'timeout' => $timeout,
            'verify'  => $this->sslVerify,
        ];

        // 区分 multipart 上传 vs JSON
        $hasFile = false;
        foreach ($params as $v) {
            if (is_resource($v) || (is_string($v) && str_starts_with($v, '@'))) {
                $hasFile = true;
                break;
            }
        }
        if ($hasFile) {
            $multipart = [];
            foreach ($params as $k => $v) {
                $multipart[] = ['name' => $k, 'contents' => is_resource($v) ? $v : fopen(substr($v, 1), 'r')];
            }
            $options['multipart'] = $multipart;
        } else {
            // 数组 / 对象用 JSON 编码（Telegram 要求嵌套字段是 JSON 字符串）
            $cleaned = [];
            foreach ($params as $k => $v) {
                if (is_array($v) || is_object($v)) {
                    $cleaned[$k] = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                } else {
                    $cleaned[$k] = $v;
                }
            }
            $options['form_params'] = $cleaned;
        }

        try {
            $resp = $this->client->post($url, $options);
            $body = (string)$resp->getBody();
            $json = json_decode($body, true);
            if (!is_array($json)) {
                throw new \RuntimeException("Bot API 返回非 JSON: {$body}");
            }
            if (!($json['ok'] ?? false)) {
                $desc = $json['description'] ?? 'unknown';
                $errCode = $json['error_code'] ?? 0;
                throw new TelegramApiException("[{$method}] {$desc}", (int)$errCode, $json);
            }
            return $json['result'];
        } catch (TelegramApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            PluginUtil::log($this->pluginName, "API[{$method}] 异常：" . $e->getMessage());
            throw new \RuntimeException("Telegram API 调用失败：" . $e->getMessage(), 0, $e);
        }
    }

    /** 静默调用，失败返回 null，不抛异常 */
    public function safeCall(string $method, array $params = [], int $timeout = 35): mixed
    {
        try {
            return $this->call($method, $params, $timeout);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getUpdates(int $offset, int $timeout = 30, array $allowedUpdates = []): array
    {
        $params = ['offset' => $offset, 'timeout' => $timeout];
        if ($allowedUpdates) {
            $params['allowed_updates'] = $allowedUpdates;
        }
        $res = $this->call('getUpdates', $params, $timeout + 10);
        return is_array($res) ? $res : [];
    }

    public function sendMessage(int|string $chatId, string $text, array $extra = []): ?array
    {
        return $this->safeCall('sendMessage', array_merge([
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ], $extra));
    }

    public function editMessageText(int|string $chatId, int $messageId, string $text, array $extra = []): ?array
    {
        return $this->safeCall('editMessageText', array_merge([
            'chat_id'    => $chatId,
            'message_id' => $messageId,
            'text'       => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ], $extra));
    }

    public function editMessageReplyMarkup(int|string $chatId, int $messageId, array $replyMarkup): ?array
    {
        return $this->safeCall('editMessageReplyMarkup', [
            'chat_id'      => $chatId,
            'message_id'   => $messageId,
            'reply_markup' => $replyMarkup,
        ]);
    }

    public function deleteMessage(int|string $chatId, int $messageId): ?array
    {
        return $this->safeCall('deleteMessage', [
            'chat_id'    => $chatId,
            'message_id' => $messageId,
        ]);
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $showAlert = false): ?array
    {
        return $this->safeCall('answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text'              => $text,
            'show_alert'        => $showAlert,
        ]);
    }

    public function copyMessage(int|string $toChatId, int|string $fromChatId, int $messageId, array $extra = []): ?array
    {
        return $this->safeCall('copyMessage', array_merge([
            'chat_id'      => $toChatId,
            'from_chat_id' => $fromChatId,
            'message_id'   => $messageId,
        ], $extra));
    }

    public function createForumTopic(int|string $chatId, string $name): ?array
    {
        return $this->safeCall('createForumTopic', [
            'chat_id' => $chatId,
            'name'    => mb_substr($name, 0, 128),
        ]);
    }

    public function editForumTopic(int|string $chatId, int $threadId, string $name): ?array
    {
        return $this->safeCall('editForumTopic', [
            'chat_id'           => $chatId,
            'message_thread_id' => $threadId,
            'name'              => mb_substr($name, 0, 128),
        ]);
    }

    public function deleteForumTopic(int|string $chatId, int $threadId): ?array
    {
        return $this->safeCall('deleteForumTopic', [
            'chat_id'           => $chatId,
            'message_thread_id' => $threadId,
        ]);
    }

    public function getMe(): ?array
    {
        return $this->safeCall('getMe');
    }
}
