<?php
declare(strict_types=1);

namespace App\Pay\WeChat\Impl;


use App\Entity\PayEntity;
use App\Pay\Base;
use App\Pay\WeChat\Entity\Request;
use App\Pay\WeChat\Service\Wechat;
use GuzzleHttp\Exception\GuzzleException;
use Kernel\Exception\JSONException;

class Pay extends Base implements \App\Pay\Pay
{

    /**
     * @return PayEntity
     * @throws JSONException
     * @throws GuzzleException
     */
    public function trade(): PayEntity
    {
        $request = new Request($this->config['mch_id'], $this->config['key'], $this->config['app_id'], $this->config['app_secret']);
        $request->setBody("商品购买-订单号:{$this->tradeNo}");
        $request->setNotifyUrl($this->callbackUrl);
        $request->setReturnUrl($this->returnUrl);
        $request->setTradeNo($this->tradeNo);
        $request->setAmount($this->amount);

        $wechat = Wechat::make();

        if ($this->code == 3) {
            $request->setWebUrl($this->config['http_url']);
        } elseif ($this->code == 2) {
            $request->setOptions([
                'scene_info' => json_encode([
                    'h5_info' => [
                        'type' => 'Wap',
                        'wap_url' => $this->config['wap_url'],
                        'wap_name' => $this->config['wap_name']
                    ]
                ])
            ]);
        }

        return match ((int)$this->code) {
            1 => $wechat->native($request, $this->clientIp),
            2 => $wechat->h5($request, $this->clientIp),
            3 => $wechat->jsapi($request)
        };
    }

}