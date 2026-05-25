<?php
declare(strict_types=1);

namespace App\Plugin\SharedStock\Controller;

use App\Controller\Base\API\Shared;
use App\Interceptor\SharedValidation;
use App\Interceptor\Waf;
use App\Model\Card;
use App\Model\Category;
use App\Model\Commodity;
use App\Model\Config;
use App\Service\Order;
use App\Util\Ini;
use Illuminate\Database\Eloquent\Relations\Relation;
use Kernel\Annotation\Inject;
use Kernel\Annotation\Interceptor;
use Kernel\Context\Interface\Request;
use Kernel\Exception\JSONException;
use Kernel\Exception\RuntimeException;
use Kernel\Waf\Filter;

#[Interceptor([Waf::class, SharedValidation::class], Interceptor::TYPE_API)]
class Api extends Shared
{

    #[Inject]
    private Order $order;


    #[Inject]
    private Request $request;


    /**
     * @return array
     * @throws RuntimeException
     */
    public function connect(): array
    {
        $shopName = Config::get("shop_name");
        return $this->json(200, "success", ["shopName" => $shopName, "balance" => $this->getUser()->balance]);
    }


    /**
     * @param string|null $sharedCode
     * @return array
     * @throws JSONException
     */
    private function getItems(?string $sharedCode = null): array
    {
        $items = Category::query()->with(['children' => function (Relation $relation) use ($sharedCode) {
            $relation->where("api_status", 1)->where("status", 1);
            if ($sharedCode) {
                $relation->where("code", $sharedCode);
            }
        }])->where("status", 1)->get();


        $list = $items->toArray();

        $userGroup = $this->getUserGroup();
        $userId = $this->getUser()->id;

        foreach ($list as $key => $item) {
            if (count($item['children']) == 0) {
                unset($list[$key]);
                continue;
            }
            foreach ($item['children'] as $index => $child) {
                $commodity = $items[$key]['children'][$index]; //直接拿到商品对象
                if (!$commodity || $commodity->id != $child['id']) {
                    unset($list[$key]['children'][$index]);
                    continue;
                }

                $parseGroupConfig = Commodity::parseGroupConfig($child['level_price'], $userGroup);
                if ($child['hide'] == 1 && (!$parseGroupConfig || !isset($parseGroupConfig['show']) || $parseGroupConfig['show'] != 1)) {
                    unset($list[$key]['children'][$index]);
                    continue;
                }
                unset($list[$key]['children'][$index]['leave_message'], $list[$key]['children'][$index]['delivery_message']);
                //去掉原来的成本，准备计算拿货价
                $configs = Ini::toArray((string)$child['config']);
                if (array_key_exists("category_factory", $configs)) {
                    unset($configs['category_factory']);
                }
                //检测是否设置了种类
                if (array_key_exists("category", $configs)) {
                    //挨个计算成本
                    $categorys = $configs['category'];
                    $factorys = [];
                    //这里ck = race种类名称，cv=单价
                    foreach ($categorys as $ck => $cv) {
                        //计算当前种类的成本
                        try {
                            $factorys[$ck] = $this->order->calcAmount(owner: $userId, num: 1, disableSubstation: true, group: $userGroup, commodity: $commodity, race: $ck);
                        } catch (\Error|\Exception $e) {
                            unset($configs['category'][$ck]);
                            continue;
                        }
                    }
                    if (count($factorys) != 0) {
                        //覆盖成本
                        $configs['category_factory'] = $factorys;
                    }
                    //将config array转换为配置文件
                    $list[$key]['children'][$index]['config'] = Ini::toConfig($configs);
                    $list[$key]['children'][$index]['factory_price'] = 0;
                } else {
                    //没有设置种类，计算会员价
                    $list[$key]['children'][$index]['factory_price'] = $this->order->calcAmount(owner: $userId, num: 1, disableSubstation: true, group: $userGroup, commodity: $commodity);
                }

                if ($child['delivery_way'] == 0) { //stock
                    $list[$key]['children'][$index]['stock'] = Card::query()->where("status", 0)->where("commodity_id", $child['id'])->count(); //定义库存数量
                } else {
                    $list[$key]['children'][$index]['stock'] = 999;
                }
            }
            //重组
            $list[$key]['children'] = array_values($list[$key]['children']);
        }

        return array_values($list);
    }

    /**
     * @return array
     * @throws JSONException
     */
    public function items(): array
    {
        return $this->json(200, 'success', $this->getItems());
    }

    /**
     * @return array
     * @throws JSONException
     */
    public function item(): array
    {
        $sharedCode = $_POST['code'] ?? null;
        if (!$sharedCode) {
            throw new JSONException("对接CODE不能为空");
        }
        return $this->json(200, 'success', $this->getItems($sharedCode));
    }


    /**
     * @return array
     * @throws JSONException
     */
    public function stock(): array
    {
        $map = $this->request->post(flags: Filter::NORMAL);
        $race = $map['race'] ?? null;

        /**
         * @var Commodity $commodity
         */
        $commodity = Commodity::with(['shared'])->where("code", $map['code'])->first();

        if (!$commodity) throw new JSONException("商品不存在");

        //对接商品
        if ($commodity->shared) {
            return $this->json(code: 200, data: ["stock" => 999]);
        } else if ($commodity->delivery_way == 0) {
            //库存
            $card = Card::query()->where("commodity_id", $commodity->id)->where("status", 0);
            if ($race) $card = $card->where("race", $race);
            return $this->json(code: 200, data: ["stock" => (string)$card->count()]);
        }

        return $this->json(code: 200, data: ["stock" => 999]);
    }


    /**
     * @return array
     * @throws JSONException
     */
    public function valuation(): array
    {
        $commodity = Commodity::query()->where("code", $this->request->post("code"))->first();

        /**
         * @var Commodity $commodity
         */
        if (!$commodity) throw new JSONException("商品不存在");
        $result = $this->order->getTradeAmount(
            $this->getUser(),
            $this->getUserGroup(),
            (int)$this->request->post("card_id"),
            (int)$this->request->post("num"), "",
            $commodity->id,
            (string)$this->request->post("race")
        );

        return $this->json(code: 200, data: $result);
    }
}