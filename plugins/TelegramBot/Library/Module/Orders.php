<?php
declare(strict_types=1);

namespace App\Plugin\TelegramBot\Library\Module;

use App\Model\Order as ShopOrder;
use App\Plugin\TelegramBot\Library\Renderer;
use App\Plugin\TelegramBot\Library\State;
use App\Plugin\TelegramBot\Library\TelegramApi;

class Orders
{
    private TelegramApi $api;

    public function __construct(TelegramApi $api)
    {
        $this->api = $api;
    }

    public function showMenu(array $tgUser): void
    {
        $text = "🧾 我的订单\n\n请选择查询时间范围：";
        $kb = [
            [['text' => '一周内', 'callback_data' => 'orders:range:7']],
            [['text' => '一月内', 'callback_data' => 'orders:range:30']],
            [['text' => '一年内', 'callback_data' => 'orders:range:365']],
            [['text' => '所有', 'callback_data' => 'orders:range:99999']],
            [['text' => '🔙 主菜单', 'callback_data' => 'main:reload']],
        ];
        $this->edit($tgUser, $text, ['inline_keyboard' => $kb]);
    }

    public function showRange(array $tgUser, int $days): void
    {
        $tgUserId = (int)$tgUser['tg_user_id'];
        $shopUserId = (int)($tgUser['user_id'] ?? 0);

        $since = date('Y-m-d H:i:s', time() - $days * 86400);

        // 关联 plugin_telegrambot_order 拿"我在 TG 创建的订单"
        $tradeNos = \Illuminate\Database\Capsule\Manager::table('plugin_telegrambot_order')
            ->where('tg_user_id', $tgUserId)
            ->where('create_time', '>=', $since)
            ->pluck('trade_no')->toArray();

        $q = ShopOrder::query()->where('create_time', '>=', $since);
        if ($shopUserId > 0) {
            $q->where(function ($w) use ($shopUserId, $tradeNos) {
                $w->where('owner', $shopUserId);
                if ($tradeNos) $w->orWhereIn('trade_no', $tradeNos);
            });
        } else {
            if (!$tradeNos) {
                $this->edit($tgUser, '🧾 您还没有订单。', ['inline_keyboard' => [[['text' => '🔙 返回', 'callback_data' => 'orders:menu']]]]);
                return;
            }
            $q->whereIn('trade_no', $tradeNos);
        }

        $orders = $q->orderBy('id', 'desc')->limit(20)->get();
        if (count($orders) === 0) {
            $this->edit($tgUser, '🧾 该时段没有订单。', ['inline_keyboard' => [[['text' => '🔙 返回', 'callback_data' => 'orders:menu']]]]);
            return;
        }

        $kb = [];
        foreach ($orders as $o) {
            $st = Renderer::orderStatusText((int)$o->status, (int)$o->delivery_status);
            $kb[] = [['text' => "{$st} ¥{$o->amount} {$o->trade_no}", 'callback_data' => 'orders:detail:' . $o->trade_no]];
        }
        $kb[] = [['text' => '🔙 返回', 'callback_data' => 'orders:menu']];

        $this->edit($tgUser, "🧾 最近 {$days} 天内的订单（最多显示20条）：", ['inline_keyboard' => $kb]);
    }

    public function showDetail(array $tgUser, string $tradeNo): void
    {
        $o = ShopOrder::query()->where('trade_no', $tradeNo)->first();
        if (!$o) {
            $this->edit($tgUser, '订单不存在', ['inline_keyboard' => [[['text' => '🔙 返回', 'callback_data' => 'orders:menu']]]]);
            return;
        }
        $tgOrder = \Illuminate\Database\Capsule\Manager::table('plugin_telegrambot_order')
            ->where('trade_no', $tradeNo)->first();

        $row = [
            'trade_no'        => (string)$o->trade_no,
            'amount'          => (float)$o->amount,
            'status'          => (int)$o->status,
            'delivery_status' => (int)$o->delivery_status,
            'commodity_name'  => $tgOrder->commodity_name ?? null,
            'secret'          => (string)$o->secret,
        ];
        if (!$row['commodity_name'] && $o->commodity) {
            $row['commodity_name'] = (string)$o->commodity->name;
        }
        $payUrl = $tgOrder->pay_url ?? null;

        $msg = Renderer::orderDetail($row, $payUrl ?: null);
        $this->edit($tgUser, $msg['text'], $msg['reply_markup']);
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
