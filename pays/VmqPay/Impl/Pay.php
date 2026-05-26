<?php
declare(strict_types=1);

namespace App\Pay\VmqPay\Impl;

use App\Entity\PayEntity;
use App\Pay\Base;
use Kernel\Exception\JSONException;
    /**
     * 获取URL地址
     * @return string
     */
    function getUrl(): string
    {
        if (strtolower((string)$_SERVER["HTTPS"]) == "on") {
            $_SERVER['REQUEST_SCHEME'] = "https";
        } elseif (!isset($_SERVER['REQUEST_SCHEME'])) {
            $_SERVER['REQUEST_SCHEME'] = "http";
        }
        return $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];
    }
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

        if (!$this->config['url']) {
            throw new JSONException("请配置V免签请求地址");
        }
        $appKey=$this->config['key'];
        if (!$appKey) {
            throw new JSONException("请配置V免签通信密钥");
        }
 
        $param = [
            'payId' => $this->tradeNo,
            'type' => $this->code,
            'price' => $this->amount,
            'notifyUrl' => $this->callbackUrl,
            'returnUrl' => $this->returnUrl,
            'isHtml' => 1,
        ];
        
        $param['sign'] = md5($param['payId'].$param['type'].$param['price'].$appKey);
        $payEntity = new PayEntity();
        $payEntity->setType(self::TYPE_SUBMIT);
        $payEntity->setOption($param);
        $payEntity->setUrl(trim($this->config['url'], "/") . "/createOrder");
        return $payEntity;
    }
}