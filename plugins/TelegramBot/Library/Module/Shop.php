<?php
declare(strict_types=1);

namespace App\Plugin\TelegramBot\Library\Module;

use App\Model\Card;
use App\Model\Category;
use App\Model\Commodity;
use App\Model\Order as ShopOrder;
use App\Model\Pay;
use App\Model\User as ShopUser;
use App\Model\UserGroup;
use App\Plugin\TelegramBot\Library\Renderer;
use App\Plugin\TelegramBot\Library\State;
use App\Plugin\TelegramBot\Library\TelegramApi;
use App\Service\Order as OrderService;
use App\Util\Ini;
use App\Util\Plugin as PluginUtil;
use Kernel\Container\Di;

class Shop
{
    private TelegramApi $api;
    private array $config;

    public function __construct(TelegramApi $api, array $config)
    {
        $this->api = $api;
        $this->config = $config;
    }

    /** 主菜单 → 商品分类 */
    public function showCategories(array $tgUser): void
    {
        $rows = Category::query()
            ->where('status', 1)
            ->where('hide', 0)
            ->orderBy('sort', 'asc')
            ->get();

        $list = [];
        foreach ($rows as $cat) {
            $cnt = Commodity::query()
                ->where('category_id', $cat->id)
                ->where('status', 1)
                ->where('hide', 0)
                ->count();
            if ($cnt > 0) {
                $list[] = ['id' => (int)$cat->id, 'name' => (string)$cat->name, 'count' => $cnt];
            }
        }

        $msg = Renderer::categoryList($list);
        $this->editOrSend($tgUser, $msg);
    }

    /** 商品列表 */
    public function showCommodityList(array $tgUser, int $categoryId): void
    {
        $rows = Commodity::query()
            ->where('category_id', $categoryId)
            ->where('status', 1)
            ->where('hide', 0)
            ->orderBy('sort', 'asc')
            ->get();

        $list = [];
        foreach ($rows as $c) {
            $stock = $this->getStock($c);
            $list[] = [
                'id'    => (int)$c->id,
                'name'  => (string)$c->name,
                'stock' => $stock,
                'price' => (string)$c->price,
            ];
        }

        $msg = Renderer::commodityList($list, $categoryId);
        $this->editOrSend($tgUser, $msg);
    }

    /** 商品详情 */
    public function showCommodityDetail(array $tgUser, int $commodityId, array $partialCart = []): void
    {
        $c = Commodity::query()->find($commodityId);
        if (!$c || $c->status != 1) {
            $this->editOrSend($tgUser, ['text' => '❌ 商品不存在或已下架', 'reply_markup' => ['inline_keyboard' => [[['text' => '🔙 返回', 'callback_data' => 'shop:categories']]]]]);
            return;
        }

        // merge cart
        $cart = State::getCart($tgUser);
        if (!isset($cart['item_id']) || (int)$cart['item_id'] !== (int)$c->id) {
            // 切换了商品：清空旧 cart
            $cart = ['item_id' => (int)$c->id, 'num' => 1];
        }
        foreach ($partialCart as $k => $v) {
            $cart[$k] = $v;
        }
        if (empty($cart['num']) || (int)$cart['num'] < 1) {
            $cart['num'] = 1;
        }

        // 自动取已绑定用户的邮箱/手机做联系方式
        if (empty($cart['contact'])) {
            $shopUser = State::getShopUser($tgUser);
            if ($shopUser) {
                $cart['contact'] = (string)($shopUser->email ?: $shopUser->phone ?: $shopUser->username);
            }
        }

        State::setCart((int)$tgUser['tg_user_id'], $cart);

        // 解析 race / sku 列表
        $config = Ini::toArray((string)$c->config);
        $races = [];
        if (!empty($config['category']) && is_array($config['category'])) {
            foreach ($config['category'] as $raceName => $price) {
                $races[] = ['code' => (string)$raceName, 'price' => (string)$price];
            }
        }
        $skuOptions = [];
        if (!empty($config['sku']) && is_array($config['sku'])) {
            foreach ($config['sku'] as $k => $vs) {
                $skuOptions[(string)$k] = array_values((array)$vs);
            }
        }

        // 算价
        $shopUser = State::getShopUser($tgUser);
        $userGroup = $shopUser ? UserGroup::get((float)$shopUser->recharge) : null;
        $amount = '0.00';
        try {
            /** @var OrderService $orderSvc */
            $orderSvc = Di::inst()->make(OrderService::class);
            $amount = $orderSvc->valuation(
                $c,
                (int)$cart['num'],
                $cart['race'] ?? null,
                $cart['sku'] ?? [],
                (int)($cart['card_id'] ?? 0) ?: null,
                null,
                $userGroup
            );
        } catch (\Throwable $e) {
            // 可能是必须先选 race / sku，此时返回基础价
            $amount = (string)($c->price * (int)$cart['num']);
        }

        // 支付通道
        $pays = $this->listPays();

        $sold = ShopOrder::query()->where('commodity_id', $c->id)->where('status', 1)->count();
        $msg = Renderer::commodityDetail([
            'commodity' => [
                'id'           => (int)$c->id,
                'name'         => (string)$c->name,
                'description'  => (string)$c->description,
                'stock'        => $this->getStock($c),
                'price'        => (string)$c->price,
                'delivery_way' => (int)$c->delivery_way,
                'category_id'  => (int)$c->category_id,
                'sold'         => $sold,
            ],
            'races'       => $races,
            'sku'         => $skuOptions,
            'cart'        => $cart,
            'pays'        => $pays,
            'amount'      => $amount,
            'draftStatus' => (int)$c->draft_status === 1,
        ]);

        $this->editOrSend($tgUser, $msg);
    }

    /** 进入"等待联系方式"状态 */
    public function askContact(array $tgUser, int $commodityId): void
    {
        State::setState((int)$tgUser['tg_user_id'], 'await_contact', ['item_id' => $commodityId]);
        $this->api->sendMessage($tgUser['tg_chat_id'], '请将您的联系方式（邮箱 / 手机号 / QQ）发送给我');
    }

    /** 进入"等待数量"状态 */
    public function askNum(array $tgUser, int $commodityId): void
    {
        State::setState((int)$tgUser['tg_user_id'], 'await_num', ['item_id' => $commodityId]);
        $this->api->sendMessage($tgUser['tg_chat_id'], '请发送您要购买的数量（整数）');
    }

    /** 自助选号：列出可选 Card */
    public function askDraft(array $tgUser, int $commodityId): void
    {
        $c = Commodity::query()->find($commodityId);
        if (!$c || (int)$c->draft_status !== 1) return;
        $cards = Card::query()
            ->where('commodity_id', $c->id)
            ->where('status', 0)
            ->limit(20)
            ->get();
        $kb = [];
        $kb[] = [['text' => '🎲 不选号，随机发货', 'callback_data' => 'shop:draftpick:' . self::pack($commodityId, 0)]];
        foreach ($cards as $card) {
            $note = (string)($card->note ?: $card->draft ?: 'ID#' . $card->id);
            if (mb_strlen($note) > 30) $note = mb_substr($note, 0, 30) . '…';
            $kb[] = [['text' => $note, 'callback_data' => 'shop:draftpick:' . self::pack($commodityId, (int)$card->id)]];
        }
        $kb[] = [['text' => '🔙 返回', 'callback_data' => 'shop:item:' . $commodityId]];
        $msg = [
            'text' => '请选择您想要的具体卡密：',
            'reply_markup' => ['inline_keyboard' => $kb],
        ];
        $this->editOrSend($tgUser, $msg);
    }

    public function pickDraft(array $tgUser, int $commodityId, int $cardId): void
    {
        $this->showCommodityDetail($tgUser, $commodityId, ['card_id' => $cardId]);
    }

    /** 收到了文本（联系方式或数量），尝试推进购物 */
    public function handleText(array $tgUser, string $text): void
    {
        $state = (string)($tgUser['state'] ?? '');
        $data = State::getStateData($tgUser);
        $itemId = (int)($data['item_id'] ?? 0);
        if (!$itemId) return;

        if ($state === 'await_contact') {
            State::setState((int)$tgUser['tg_user_id'], null);
            $this->showCommodityDetail($tgUser, $itemId, ['contact' => trim($text)]);
            return;
        }
        if ($state === 'await_num') {
            $n = (int)preg_replace('/\D/', '', $text);
            if ($n < 1) {
                $this->api->sendMessage($tgUser['tg_chat_id'], '⚠️ 请输入≥1的整数');
                return;
            }
            State::setState((int)$tgUser['tg_user_id'], null);
            $this->showCommodityDetail($tgUser, $itemId, ['num' => $n]);
        }
    }

    /** 选择 race */
    public function chooseRace(array $tgUser, int $commodityId, string $race): void
    {
        $this->showCommodityDetail($tgUser, $commodityId, ['race' => $race]);
    }

    /** 选择 SKU */
    public function chooseSku(array $tgUser, int $commodityId, string $skuKey, string $skuVal): void
    {
        $cart = State::getCart($tgUser);
        $sku = (array)($cart['sku'] ?? []);
        $sku[$skuKey] = $skuVal;
        $this->showCommodityDetail($tgUser, $commodityId, ['sku' => $sku]);
    }

    public function choosePay(array $tgUser, int $commodityId, int $payId): void
    {
        $this->showCommodityDetail($tgUser, $commodityId, ['pay_id' => $payId]);
    }

    /** 下单：调主程序 OrderService::trade */
    public function checkout(array $tgUser, int $commodityId): array
    {
        $cart = State::getCart($tgUser);
        if ((int)($cart['item_id'] ?? 0) !== $commodityId) {
            return ['ok' => false, 'msg' => '⚠️ 购物上下文错乱，请重新选择'];
        }
        if (empty($cart['contact'])) {
            return ['ok' => false, 'msg' => '⚠️ 请先填写联系方式'];
        }
        if (empty($cart['pay_id'])) {
            return ['ok' => false, 'msg' => '⚠️ 请先选择支付方式'];
        }

        $c = Commodity::query()->find($commodityId);
        if (!$c) {
            return ['ok' => false, 'msg' => '❌ 商品不存在'];
        }

        $shopUser = State::getShopUser($tgUser);
        $userGroup = $shopUser ? UserGroup::get((float)$shopUser->recharge) : null;

        $map = [
            'item_id'    => $commodityId,
            'contact'    => (string)$cart['contact'],
            'num'        => (int)($cart['num'] ?? 1),
            'card_id'    => (int)($cart['card_id'] ?? 0),
            'pay_id'     => (int)$cart['pay_id'],
            'device'     => 3, // 标记为 TG 端
            'password'   => '',
            'coupon'     => '',
            'race'       => (string)($cart['race'] ?? ''),
            'sku'        => (array)($cart['sku'] ?? []),
            'request_no' => 'tg_' . $tgUser['tg_user_id'] . '_' . time(),
        ];

        try {
            /** @var OrderService $orderSvc */
            $orderSvc = Di::inst()->make(OrderService::class);
            $result = $orderSvc->trade($shopUser, $userGroup, $map);
        } catch (\Throwable $e) {
            PluginUtil::log('TelegramBot', 'trade failed: ' . $e->getMessage());
            return ['ok' => false, 'msg' => '❌ 下单失败：' . $e->getMessage()];
        }

        State::clearCart((int)$tgUser['tg_user_id']);

        // 记录订单 → tg_user 映射
        \Illuminate\Database\Capsule\Manager::table('plugin_telegrambot_order')->insert([
            'trade_no'       => $result['tradeNo'],
            'tg_user_id'     => (int)$tgUser['tg_user_id'],
            'tg_chat_id'     => (int)$tgUser['tg_chat_id'],
            'pay_url'        => (string)$result['url'],
            'amount'         => (float)$result['amount'],
            'commodity_name' => (string)$c->name,
            'create_time'    => \App\Util\Date::current(),
        ]);

        return [
            'ok'        => true,
            'tradeNo'   => $result['tradeNo'],
            'url'       => $result['url'],
            'amount'    => $result['amount'],
            'commodity' => $c,
            'secret'    => $result['secret'] ?? null,
        ];
    }

    public function getStock(Commodity $c): int
    {
        try {
            /** @var \App\Service\Shop $shopSvc */
            $shopSvc = Di::inst()->make(\App\Service\Shop::class);
            return (int)$shopSvc->getItemStock($c, null, []);
        } catch (\Throwable) {
            return (int)$c->stock;
        }
    }

    private function listPays(): array
    {
        $whitelist = (string)($this->config['pay_ids'] ?? '');
        $q = Pay::query()->where('commodity', 1)->orderBy('sort', 'asc');
        if ($whitelist !== '') {
            $ids = array_filter(array_map('intval', explode(',', $whitelist)));
            if ($ids) $q->whereIn('id', $ids);
        }
        $rows = $q->get();
        $out = [];
        foreach ($rows as $p) {
            $out[] = ['id' => (int)$p->id, 'name' => (string)$p->name, 'code' => (int)$p->code, 'handle' => (string)$p->handle];
        }
        return $out;
    }

    private function editOrSend(array $tgUser, array $msg): void
    {
        $chatId = (int)$tgUser['tg_chat_id'];
        $curMsgId = (int)($tgUser['current_message_id'] ?? 0);
        $extra = isset($msg['reply_markup']) ? ['reply_markup' => $msg['reply_markup']] : [];
        if ($curMsgId > 0) {
            $r = $this->api->editMessageText($chatId, $curMsgId, $msg['text'], $extra);
            if ($r !== null) return;
        }
        $sent = $this->api->sendMessage($chatId, $msg['text'], $extra);
        if (is_array($sent) && isset($sent['message_id'])) {
            State::setCurrentMessageId((int)$tgUser['tg_user_id'], (int)$sent['message_id']);
        }
    }

    public static function pack(int $c, int $card): string
    {
        return $c . '_' . $card;
    }

    public static function unpack(string $s): array
    {
        $a = explode('_', $s);
        return ['c' => (int)($a[0] ?? 0), 'card' => (int)($a[1] ?? 0)];
    }
}
