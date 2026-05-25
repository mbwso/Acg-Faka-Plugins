<?php
declare(strict_types=1);

namespace App\Plugin\PaypalCallback\Controller;
require(__DIR__ . "/../Vendor/autoload.php");

use App\Controller\Base\View\UserPlugin;
use App\Interceptor\Waf;
use App\Service\Order;
use App\Util\Client;
use App\Util\Plugin;
use Illuminate\Database\Capsule\Manager as DB;
use Kernel\Annotation\Inject;
use Kernel\Annotation\Interceptor;
use Kernel\Exception\JSONException;

#[Interceptor(Waf::class, Interceptor::TYPE_VIEW)]
class Callback extends UserPlugin
{

    #[Inject]
    private Order $order;

    public function confirm(): string
    {
        $config = Plugin::getConfig("PaypalCallback");
        $paymentID = (string)$_GET['paymentId'];
        $payerId = (string)$_GET['PayerID'];
        $oAuth = new \PayPal\Auth\OAuthTokenCredential($config['client_id'], $config['secret']);
        $apiContext = new \PayPal\Rest\ApiContext($oAuth);
        $apiContext->setConfig(['mode' => 'live']);
        $payment = \PayPal\Api\Payment::get($paymentID, $apiContext);
        $execute = new \PayPal\Api\PaymentExecution();
        $execute->setPayerId($payerId);
        try {
            $payment = $payment->execute($execute, $apiContext);//执行,从paypal获取支付结果
            $paymentState = $payment->getState();//Possible values: created, approved, failed.
            $tradeNo = $payment->getTransactions()[0]->getInvoiceNumber();
            $payNum = $payment->getTransactions()[0]->getRelatedResources()[0]->getSale()->getId();//这是支付的流水单号，必须保存，在退款时会使用到
            $total = $payment->getTransactions()[0]->getRelatedResources()[0]->getSale()->getAmount()->getTotal();//支付总金额
            $transactionState = $payment->getTransactions()[0]->getRelatedResources()[0]->getSale()->getState();//Possible values: completed, partially_refunded, pending, refunded, denied.
            if ($paymentState == 'approved' && $transactionState == 'completed') {
                DB::connection()->getPdo()->exec("set session transaction isolation level serializable");
                DB::transaction(function () use ($tradeNo) {
                    //获取订单
                    $order = \App\Model\Order::query()->where("trade_no", $tradeNo)->first();
                    if (!$order) {
                        throw new JSONException("order not found");
                    }
                    if ($order->status != 0) {
                        throw new JSONException("order status error");
                    }
                    $this->order->orderSuccess($order);
                });
                //处理完成，跳转查询订单
                Client::redirect("/user/index/query?tradeNo=" . $tradeNo, "请稍后..", 0);
            } else {
                Client::redirect("/user/index/query?tradeNo=" . $tradeNo, "请稍后..", 0);
                return "error";
            }
        } catch (\Exception | JSONException $e) {
            Client::redirect("/user/index/query?tradeNo=" . $tradeNo, "请稍后..", 0);
            return $e->getMessage();
        }
    }
}