<?php
declare(strict_types=1);

namespace App\Plugin\TelegramBot\Library;

use App\Model\User as ShopUser;
use App\Util\Date;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Bot 用户表（plugin_telegrambot_user）的访问封装。
 * 不走 Eloquent Model（少建一个 class），直接用 DB 查询构造器。
 */
class State
{
    private const TABLE = 'plugin_telegrambot_user';

    public static function table()
    {
        return DB::table(self::TABLE);
    }

    /** 拿到一行；不存在则插入并返回 */
    public static function ensure(int $tgUserId, int $chatId, array $from): array
    {
        $row = self::table()->where('tg_user_id', $tgUserId)->first();
        if (!$row) {
            self::table()->insert([
                'tg_user_id'    => $tgUserId,
                'tg_chat_id'    => $chatId,
                'username'      => $from['username'] ?? null,
                'first_name'    => $from['first_name'] ?? null,
                'last_name'     => $from['last_name'] ?? null,
                'language_code' => $from['language_code'] ?? null,
                'create_time'   => Date::current(),
                'update_time'   => Date::current(),
            ]);
            $row = self::table()->where('tg_user_id', $tgUserId)->first();
        } else {
            // 更新昵称（如果变了）
            $update = [];
            foreach (['username', 'first_name', 'last_name', 'language_code'] as $f) {
                $new = $from[$f] ?? null;
                if ($new !== null && (string)($row->$f ?? '') !== (string)$new) {
                    $update[$f] = $new;
                }
            }
            if ($chatId && (int)($row->tg_chat_id ?? 0) !== $chatId) {
                $update['tg_chat_id'] = $chatId;
            }
            if ($update) {
                $update['update_time'] = Date::current();
                self::table()->where('tg_user_id', $tgUserId)->update($update);
                $row = self::table()->where('tg_user_id', $tgUserId)->first();
            }
        }
        return (array)$row;
    }

    public static function get(int $tgUserId): ?array
    {
        $row = self::table()->where('tg_user_id', $tgUserId)->first();
        return $row ? (array)$row : null;
    }

    public static function findByThread(int $threadId): ?array
    {
        if ($threadId <= 0) return null;
        $row = self::table()->where('message_thread_id', $threadId)->first();
        return $row ? (array)$row : null;
    }

    public static function update(int $tgUserId, array $fields): void
    {
        $fields['update_time'] = Date::current();
        self::table()->where('tg_user_id', $tgUserId)->update($fields);
    }

    /** 设置状态机：state + state_data(JSON) */
    public static function setState(int $tgUserId, ?string $state, array $data = []): void
    {
        self::update($tgUserId, [
            'state'      => $state,
            'state_data' => $state === null ? null : json_encode($data, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public static function getStateData(array $row): array
    {
        $raw = $row['state_data'] ?? null;
        if (!$raw) return [];
        $d = json_decode((string)$raw, true);
        return is_array($d) ? $d : [];
    }

    /** 当前购物上下文（item_id/race/sku/card_id/contact/num/pay_id） */
    public static function getCart(array $row): array
    {
        $raw = $row['cart'] ?? null;
        if (!$raw) return [];
        $d = json_decode((string)$raw, true);
        return is_array($d) ? $d : [];
    }

    public static function setCart(int $tgUserId, array $cart): void
    {
        self::update($tgUserId, ['cart' => $cart ? json_encode($cart, JSON_UNESCAPED_UNICODE) : null]);
    }

    public static function clearCart(int $tgUserId): void
    {
        self::update($tgUserId, ['cart' => null]);
    }

    public static function setCurrentMessageId(int $tgUserId, int $msgId): void
    {
        self::update($tgUserId, ['current_message_id' => $msgId]);
    }

    public static function bindUser(int $tgUserId, int $shopUserId): void
    {
        self::update($tgUserId, ['user_id' => $shopUserId]);
    }

    public static function unbindUser(int $tgUserId): void
    {
        self::update($tgUserId, ['user_id' => 0]);
    }

    public static function getShopUser(array $row): ?ShopUser
    {
        $uid = (int)($row['user_id'] ?? 0);
        if ($uid <= 0) return null;
        return ShopUser::query()->find($uid);
    }

    public static function rateLimited(array $row, int $seconds): bool
    {
        if ($seconds <= 0) return false;
        $last = (int)($row['last_msg_at'] ?? 0);
        return $last > 0 && (time() - $last) < $seconds;
    }

    public static function touchLastMsg(int $tgUserId): void
    {
        self::update($tgUserId, ['last_msg_at' => time()]);
    }

    /** key-value 状态存储（offset 等） */
    public static function kvGet(string $key): ?string
    {
        $row = DB::table('plugin_telegrambot_state')->where('k', $key)->first();
        return $row ? (string)$row->v : null;
    }

    public static function kvSet(string $key, string $value): void
    {
        $exists = DB::table('plugin_telegrambot_state')->where('k', $key)->exists();
        if ($exists) {
            DB::table('plugin_telegrambot_state')->where('k', $key)->update(['v' => $value, 'update_time' => Date::current()]);
        } else {
            DB::table('plugin_telegrambot_state')->insert(['k' => $key, 'v' => $value, 'update_time' => Date::current()]);
        }
    }
}
