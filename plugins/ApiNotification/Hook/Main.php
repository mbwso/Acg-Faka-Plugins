<?php
declare(strict_types=1);

namespace App\Plugin\ApiNotification\Hook;

use App\Model\Commodity;
use App\Model\Order;
use App\Model\Pay;
use App\Util\Http;
use App\Util\Plugin;
use App\Util\Str;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;
use Kernel\Annotation\Hook;

/**
 *
 */
class Main
{

    /**
     * 更新或安装时，安装数据库支持
     */
    private function InstallDB(): void
    {
        //判断字段是否存在，不存在则创建字段
        $extend = Manager::schema()->hasColumn("commodity", "extend");
        if (!$extend) {
            Manager::schema()->table("commodity", function (Blueprint $blueprint) {
                $blueprint->text("extend")->nullable(true)->default(null);
            });
        }
    }

    #[\Kernel\Annotation\Plugin(state: \Kernel\Annotation\Plugin::INSTALL)]
    public function Install(): void
    {
        $this->InstallDB();
    }

    #[\Kernel\Annotation\Plugin(state: \Kernel\Annotation\Plugin::UPGRADE)]
    public function Update(): void
    {
        $this->InstallDB();
    }

    #[Hook(point: \App\Consts\Hook::USER_API_ORDER_PAY_AFTER)]
    public function Notification(Commodity $commodity, Order $order, Pay $pay): void
    {
        $config = Plugin::getConfig("ApiNotification");
        $commoditys = $config['commodity'];
        $isLog = (bool)$config['log'];

        try {
            if (!in_array((string)$commodity->id, $commoditys)) {
                return;
            }

            if ($isLog) {
                Plugin::log("ApiNotification", "捕获到订单支付成功：{$order->trade_no}，准备请求API：" . $config['url']);
            }

            $client = Http::make(['timeout' => 5, 'headers' => (array)json_decode((string)$config['headers'], true)]);
            $data = [];
            $data['data'] = json_encode([
                "commodity" => $commodity->toArray(),
                "order" => $order->toArray(),
                "pay" => $pay->toArray()
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if ($isLog) {
                Plugin::log("ApiNotification", "准备商品：{$commodity->name}");
            }

            $param = json_decode((string)$config['param'], true);

            if (!empty($param)) {
                foreach ($param as $key => $val) {
                    $data[$key] = $val;
                }
            }

            $data['sign'] = Str::generateSignature($data, $config['key']);

            if ($isLog) {
                Plugin::log("ApiNotification", "本地生成签名：" . $data['sign']);
            }

            if ((int)$config['request_type'] == 0) {
                $response = $client->post($config['url'], [
                    "form_params" => $data
                ]);
            } else {
                $response = $client->get($config['url'], [
                    "query" => $data
                ]);
            }

            if ($isLog) {
                Plugin::log("ApiNotification", "请求完成，返回结果：" . $response->getBody()->getContents());
            }

            //通知结束，将订单改成已发货
            $order->delivery_status = 1;
            $order->save();

        } catch (\Error | \Exception $e) {
            if ($isLog) {
                Plugin::log("ApiNotification", "请求失败，错误原因：" . $e->getMessage());
            }
        }
    }


    #[Hook(point: \App\Consts\Hook::ADMIN_VIEW_COMMODITY_POST)]
    public function CommodityPost(): void
    {
        echo '{title: "API通知", name: "extend", type: "json", tips: "该扩展为API开发提供数据支持", default: ""},';
    }
}