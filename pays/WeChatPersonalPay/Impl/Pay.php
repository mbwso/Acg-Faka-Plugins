<?php
declare(strict_types=1);

namespace App\Pay\WeChatPersonalPay\Impl;

use App\Entity\PayEntity;
use App\Pay\Base;
use App\Plugin\WeChatPersonal\Service\Order;
use Kernel\Container\Di;

/**
 * Class Pay
 * @package App\Pay\Kvmpay\Impl
 */
class Pay extends Base implements \App\Pay\Pay
{
    /**
     * @return PayEntity
     * @throws \ReflectionException
     */
    public function trade(): PayEntity
    {
        /**
         * @var Order $order
         */
        $order = Di::inst()->make(Order::class);
        $trade = $order->create($this->tradeNo, $this->amount, $this->code, $this->returnUrl, $this->callbackUrl);
        $payEntity = new PayEntity();
        $payEntity->setType(self::TYPE_REDIRECT);
        $payEntity->setUrl($trade['url']);
        return $payEntity;
    }
}