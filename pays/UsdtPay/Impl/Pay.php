<?php
declare(strict_types=1);

namespace App\Pay\UsdtPay\Impl;

use App\Entity\PayEntity;
use App\Pay\Base;
use App\Plugin\Usdt\Service\Order;
use GuzzleHttp\Exception\GuzzleException;
use Kernel\Exception\JSONException;

/**
 * Class Pay
 * @package App\Pay\Kvmpay\Impl
 */
class Pay extends Base implements \App\Pay\Pay
{
    /**
     * @return PayEntity
     * @throws JSONException|GuzzleException
     */
    public function trade(): PayEntity
    {
        $order = new Order();
        $trade = $order->trade($this->tradeNo, $this->amount, (int)$this->code, $this->returnUrl, $this->callbackUrl);
        $payEntity = new PayEntity();
        $payEntity->setType(self::TYPE_REDIRECT);
        $payEntity->setUrl($trade['url']);
        return $payEntity;
    }
}