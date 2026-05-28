<?php
declare(strict_types=1);

namespace App\Plugin\TelegramBot\Library;

/**
 * inline keyboard 与统一文案。
 */
class Renderer
{
    /** 主菜单（未绑定状态） */
    public static function mainMenuGuest(string $userName, string $welcomeText): array
    {
        $text = strtr($welcomeText, ['{name}' => self::htmlEscape($userName)]);
        return [
            'text' => $text,
            'reply_markup' => [
                'inline_keyboard' => [
                    [['text' => '🛍 开始购物', 'callback_data' => 'shop:categories']],
                    [['text' => '🧾 我的订单', 'callback_data' => 'orders:menu']],
                    [['text' => '🫂 绑定账号', 'callback_data' => 'account:bind']],
                    [['text' => '👤 注册账号', 'callback_data' => 'account:register']],
                    [['text' => '👩‍🚀 人工客服', 'callback_data' => 'support:start']],
                    [['text' => '🫧 重载菜单', 'callback_data' => 'main:reload']],
                ],
            ],
        ];
    }

    /** 主菜单（已绑定状态） */
    public static function mainMenuMember(string $userName, string $shopUsername, float $balance, string $welcomeText): array
    {
        $text = strtr($welcomeText, ['{name}' => self::htmlEscape($userName)])
              . "\n\n🪪 当前账号：<b>" . self::htmlEscape($shopUsername) . "</b>"
              . "\n💰 账户余额：¥ " . number_format($balance, 2);
        return [
            'text' => $text,
            'reply_markup' => [
                'inline_keyboard' => [
                    [['text' => '🛍 开始购物', 'callback_data' => 'shop:categories']],
                    [['text' => '🧾 我的订单', 'callback_data' => 'orders:menu']],
                    [['text' => '🌐 一键登录网页端', 'callback_data' => 'account:web']],
                    [['text' => '🤝 推广分销', 'callback_data' => 'promote:home']],
                    [['text' => '👩‍🚀 人工客服', 'callback_data' => 'support:start']],
                    [['text' => '🚪 解绑账号', 'callback_data' => 'account:unbind']],
                    [['text' => '🫧 重载菜单', 'callback_data' => 'main:reload']],
                ],
            ],
        ];
    }

    /** 分类列表 */
    public static function categoryList(array $categories): array
    {
        $kb = [];
        foreach ($categories as $cat) {
            $count = (int)($cat['count'] ?? 0);
            $kb[] = [['text' => "» {$cat['name']}({$count})", 'callback_data' => 'shop:category:' . $cat['id']]];
        }
        $kb[] = [['text' => '✖️取消', 'callback_data' => 'main:reload']];
        return [
            'text' => '🪅商品分类',
            'reply_markup' => ['inline_keyboard' => $kb],
        ];
    }

    /** 商品列表 */
    public static function commodityList(array $commodities, int $categoryId): array
    {
        $kb = [];
        foreach ($commodities as $c) {
            $stock = (int)($c['stock'] ?? 0);
            $kb[] = [['text' => "• {$c['name']} ({$stock}) - ¥{$c['price']}", 'callback_data' => 'shop:item:' . $c['id']]];
        }
        $kb[] = [
            ['text' => '🔙返回上一级', 'callback_data' => 'shop:categories'],
            ['text' => '✖️取消', 'callback_data' => 'main:reload'],
        ];
        return [
            'text' => '🫘 商品列表',
            'reply_markup' => ['inline_keyboard' => $kb],
        ];
    }

    /**
     * 商品详情页（含 SKU/race/数量/支付通道）。
     * @param array $ctx
     *   - commodity: array
     *   - races:     [['code'=>'普通','price'=>90], ...]  (race)
     *   - sku:       ['颜色' => ['红','蓝'], ...]
     *   - cart:      ['race'=>'普通', 'sku'=>['颜色'=>'红'], 'card_id'=>0, 'contact'=>'', 'num'=>1, 'pay_id'=>0]
     *   - pays:      [['id'=>1,'name'=>'TRC20','code'=>1], ...]
     *   - amount:    总价
     *   - draftStatus: 是否启用自选卡密(账号类)
     */
    public static function commodityDetail(array $ctx): array
    {
        $c = $ctx['commodity'];
        $cart = $ctx['cart'];
        $title = '👜 ' . $c['name'];

        $kb = [];
        // 状态行
        $kb[] = [
            ['text' => $c['delivery_way'] == 0 ? '☑ 自动发货' : '⏳ 手动发货', 'callback_data' => 'noop'],
            ['text' => '☑ 已售 ' . (int)($c['sold'] ?? 0), 'callback_data' => 'noop'],
            ['text' => '☑ 库存 ' . (int)$c['stock'], 'callback_data' => 'noop'],
        ];

        // race
        $races = $ctx['races'] ?? [];
        if ($races) {
            $kb[] = [['text' => '⬇ 选择宝贝类型 ⬇', 'callback_data' => 'noop']];
            foreach ($races as $r) {
                $selected = ($cart['race'] ?? '') === $r['code'];
                $kb[] = [['text' => ($selected ? '● ' : '○ ') . "{$r['code']} - ¥{$r['price']}", 'callback_data' => 'shop:race:' . self::b64(['c' => $c['id'], 'r' => $r['code']])]];
            }
        }

        // sku
        $sku = $ctx['sku'] ?? [];
        if ($sku) {
            $kb[] = [['text' => '⬇ 选择规格 ⬇', 'callback_data' => 'noop']];
            foreach ($sku as $skuKey => $skuVals) {
                $cur = $cart['sku'][$skuKey] ?? '';
                $row = [['text' => "{$skuKey}：" . ($cur ?: '未选'), 'callback_data' => 'noop']];
                $kb[] = $row;
                $line = [];
                foreach ($skuVals as $v) {
                    $sel = $cur === $v;
                    $line[] = ['text' => ($sel ? '● ' : '○ ') . $v, 'callback_data' => 'shop:sku:' . self::b64(['c' => $c['id'], 'k' => $skuKey, 'v' => $v])];
                    if (count($line) === 2) { $kb[] = $line; $line = []; }
                }
                if ($line) $kb[] = $line;
            }
        }

        // 自助选号（账号类）
        if (!empty($ctx['draftStatus'])) {
            $kb[] = [['text' => '⬇ 自助选号 ⬇', 'callback_data' => 'noop']];
            $cardId = (int)($cart['card_id'] ?? 0);
            $label = $cardId > 0 ? "已选号(ID:{$cardId}) - 点击重选" : '点击开始选号,不选择则随机发货';
            $kb[] = [['text' => $label, 'callback_data' => 'shop:draft:' . $c['id']]];
        }

        // 收货人
        $kb[] = [['text' => '⬇ 收货人信息 ⬇', 'callback_data' => 'noop']];
        $contact = (string)($cart['contact'] ?? '');
        if ($contact !== '') {
            $kb[] = [['text' => "* 联系方式: {$contact} 📝", 'callback_data' => 'shop:contact:' . $c['id']]];
        } else {
            $kb[] = [['text' => '* 点击输入您的联系方式', 'callback_data' => 'shop:contact:' . $c['id']]];
        }
        $num = (int)($cart['num'] ?? 1);
        $kb[] = [['text' => "* 购买数量:{$num} 📝", 'callback_data' => 'shop:num:' . $c['id']]];

        // 付款
        $kb[] = [['text' => '⬇ 付款 ¥' . number_format((float)$ctx['amount'], 2) . ' ⬇', 'callback_data' => 'noop']];
        foreach ($ctx['pays'] as $p) {
            $sel = (int)($cart['pay_id'] ?? 0) === (int)$p['id'];
            $kb[] = [['text' => ($sel ? '» ' : '') . $p['name'] . ($sel ? ' «' : ''), 'callback_data' => 'shop:pay:' . self::b64(['c' => $c['id'], 'p' => (int)$p['id']])]];
        }

        $kb[] = [
            ['text' => '🔙返回上一级', 'callback_data' => 'shop:category:' . (int)$c['category_id']],
            ['text' => '✖️取消', 'callback_data' => 'main:reload'],
        ];

        if (!empty($cart['pay_id']) && !empty($cart['contact']) && $num > 0) {
            $kb[] = [['text' => '✅ 立即下单', 'callback_data' => 'shop:checkout:' . $c['id']]];
        }

        $text = $title;
        if (!empty($c['description'])) {
            $desc = strip_tags((string)$c['description']);
            if (mb_strlen($desc) > 200) $desc = mb_substr($desc, 0, 200) . '…';
            $text .= "\n\n" . self::htmlEscape($desc);
        }

        return [
            'text' => $text,
            'reply_markup' => ['inline_keyboard' => $kb],
        ];
    }

    /** 订单详情面板 */
    public static function orderDetail(array $order, ?string $payUrl = null): array
    {
        $status = self::orderStatusText((int)$order['status'], (int)($order['delivery_status'] ?? 0));
        $text = "🧾 订单 <code>{$order['trade_no']}</code>\n"
              . "🛒 商品：" . self::htmlEscape((string)($order['commodity_name'] ?? '-')) . "\n"
              . "💰 金额：¥" . number_format((float)$order['amount'], 2) . "\n"
              . "📦 状态：{$status}\n";

        if (!empty($order['secret']) && (int)$order['status'] === 1) {
            $text .= "\n📤 发货内容：\n<code>" . self::htmlEscape((string)$order['secret']) . "</code>";
        }

        $kb = [];
        if ((int)$order['status'] === 0 && $payUrl) {
            $kb[] = [['text' => '💳 去支付', 'url' => $payUrl]];
            $kb[] = [['text' => '🔄 刷新状态', 'callback_data' => 'orders:refresh:' . $order['trade_no']]];
        } else {
            $kb[] = [['text' => '🔄 刷新状态', 'callback_data' => 'orders:refresh:' . $order['trade_no']]];
        }
        $kb[] = [['text' => '🔙 我的订单', 'callback_data' => 'orders:menu']];

        return [
            'text' => $text,
            'reply_markup' => ['inline_keyboard' => $kb],
        ];
    }

    public static function orderStatusText(int $status, int $deliveryStatus = 0): string
    {
        if ($status === 0) return '⏳ 待支付';
        if ($status === 1 && $deliveryStatus === 1) return '✅ 已发货';
        if ($status === 1) return '✅ 已支付';
        return '❓ 未知';
    }

    public static function htmlEscape(?string $s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function b64(array $payload): string
    {
        return rtrim(strtr(base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
    }

    public static function b64decode(string $s): ?array
    {
        $padded = $s . str_repeat('=', (4 - strlen($s) % 4) % 4);
        $json = base64_decode(strtr($padded, '-_', '+/'));
        if ($json === false) return null;
        $d = json_decode($json, true);
        return is_array($d) ? $d : null;
    }
}
