<?php
declare(strict_types=1);

namespace App\Pay\Jihe\Impl;

use App\Entity\PayEntity;
use App\Pay\Base;
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
        $common = new Common(url: $this->config['url'], appid: $this->config['appid'], secretKey: $this->config['secret_key'], publicKey: $this->config['public_key'], privateKey: $this->config['private_key']);

        $request = $common->getRequest($this->tradeNo, [
            'payWay' => $this->code,
            'scene' => 'online',
            'subject' => $this->tradeNo,
            'amount' => $this->amount,
            'notify_url' => $this->callbackUrl,
            'return_url' => $this->returnUrl,
            'shopdate' => date("Y-m-d", time()),
            'timeout' => 10,
            'client_ip' => $this->clientIp,
            'sub_borrow' => 4
        ]);

        if ($request['code'] != 80000) {
            throw new JSONException("请求出错：" . $request['info']);
        }

        $payEntity = new PayEntity();
        $payEntity->setType(self::TYPE_LOCAL_RENDER);
        $payEntity->setOption(['returnUrl' => $this->returnUrl]);
        $payEntity->setUrl($request['data']['jsapi_pay_info']['source_qr_code_url']);
        return $payEntity;
    }
}