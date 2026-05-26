<?php
declare(strict_types=1);

namespace App\Pay\Paypal\Impl;

use App\Entity\PayEntity;
use App\Pay\Base;
use App\Util\Client;
use Kernel\Exception\JSONException;

/**
 * Class Pay
 * @package App\Pay\Kvmpay\Impl
 */
class Pay extends Base implements \App\Pay\Pay
{

    /**
     * @return PayEntity
     * @throws \Kernel\Exception\JSONException
     */
    public function trade(): PayEntity
    {
        $subTotal = $this->amount / (float)$this->config['rate']; //最终支付金额(USD)
        //添加物品
        $item = new \PayPal\Api\Item();
        $item->setName($this->tradeNo)->setCurrency("USD")->setQuantity(1)->setPrice($subTotal);
        //添加物品到列表
        $itemList = new \PayPal\Api\ItemList();
        $itemList->addItem($item);
        //设置账单
        $details = new \PayPal\Api\Details();
        $details->setShipping(0)->setTax(0)->setSubtotal($subTotal);
        //设置金额
        $amount = new \PayPal\Api\Amount();
        $amount->setCurrency("USD")->setTotal($subTotal)->setDetails($details);
        //创建订单
        $transaction = new \PayPal\Api\Transaction();
        $transaction->setAmount($amount)->setItemList($itemList)->setDescription($this->tradeNo)
            ->setInvoiceNumber($this->tradeNo);
        $payer = new \PayPal\Api\Payer();
        $payer->setPaymentMethod('paypal');
        $redirectUrls = new \PayPal\Api\RedirectUrls();
        $redirectUrl = Client::getUrl() . "/plugin/paypalCallback/callback/confirm";//支付成功跳转的回调
        $cancelUrl = $this->returnUrl;//取消支付的回调
        $redirectUrls->setReturnUrl($redirectUrl)->setCancelUrl($cancelUrl);
        $payment = new \PayPal\Api\Payment();
        $payment->setIntent("sale")->setPayer($payer)->setRedirectUrls($redirectUrls)->addTransaction($transaction);
        try {
            $clientId = $this->config['client_id'];
            $secret = $this->config['secret'];
            $oAuth = new \PayPal\Auth\OAuthTokenCredential($clientId, $secret);
            $apiContext = new \PayPal\Rest\ApiContext($oAuth);
            //DEBUG
            // if (env('APP_DEBUG') === false) {
            $apiContext->setConfig(['mode' => 'live']);//设置线上环境
            //}
            $payment->create($apiContext);
            $approvalUrl = $payment->getApprovalLink();
            $payEntity = new PayEntity();
            $payEntity->setType(self::TYPE_REDIRECT);
            $payEntity->setUrl($approvalUrl);
            return $payEntity;
        } catch (\Exception $e) {
            throw new JSONException($e->getMessage());
        }
    }
}