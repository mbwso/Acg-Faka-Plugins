<?php
declare (strict_types=1);

namespace App\Plugin\Refund\Controller;

use App\Controller\Base\API\ManagePlugin;
use App\Interceptor\ManageSession;
use App\Interceptor\Waf;
use App\Model\Bill;
use App\Model\Order;
use App\Model\User;
use Illuminate\Database\Capsule\Manager as DB;
use Kernel\Annotation\Interceptor;
use Kernel\Exception\JSONException;

#[Interceptor([Waf::class, ManageSession::class], Interceptor::TYPE_API)]
class Api extends ManagePlugin
{

    /**
     * @throws JSONException
     */
    public function make(): array
    {
        $orderId = (int)$_POST['order_id'];

        if ($orderId == 0) {
            throw new JSONException("订单ID为空");
        }

        DB::transaction(function () use ($orderId) {
            $order = Order::query()->find($orderId);

            if (!$order) {
                throw new JSONException("订单不存在");
            }

            if ($order->status != 1) {
                throw new JSONException("该订单未支付");
            }

            if ($order->refund_status != 0) {
                throw new JSONException("该订单已经退过款了");
            }

            //退款
            $order->refund_status = 1;
            $order->save();

            $owner = User::query()->find($order->owner);

            if ($owner instanceof User) {
                Bill::create($owner, $order->amount, 1, "订单退款[{$order->trade_no}]", 0, false);
            }
        });

        return $this->json(200, "退款成功");
    }
}