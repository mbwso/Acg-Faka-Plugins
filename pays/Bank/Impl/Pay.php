<?php
declare(strict_types=1);

namespace App\Pay\Bank\Impl;

use App\Entity\PayEntity;
use App\Pay\Base;
use App\Util\Str;
use Kernel\Exception\JSONException;

/**
 * Class Pay
 * @package App\Pay\Kvmpay\Impl
 */
class Pay extends Base implements \App\Pay\Pay
{

    const PAY_URL = "https://qra.95516.com/pay/gateway";

    /**
     * @return PayEntity
     * @throws \Kernel\Exception\JSONException|\GuzzleHttp\Exception\GuzzleException
     */
    public function trade(): PayEntity
    {
        $data = [
            "service" => "unified.trade.native",
            'version' => '2.0',
            'sign_type' => 'MD5',
            'mch_id' => $this->config['mch_id'],
            'out_trade_no' => $this->tradeNo,
            'body' => $this->tradeNo,
            'total_fee' => $this->amount * 100,
            'mch_create_ip' => $this->clientIp,
            'notify_url' => $this->callbackUrl,
            'nonce_str' => Str::generateRandStr(16)
        ];

        $data['sign'] = Signature::generateSignature($data, $this->config['key']);
        $xml = '<xml>';

        foreach ($data as $key => $val) {
            $xml .= "<{$key}><![CDATA[{$val}]]></{$key}>";
        }

        $xml .= '</xml>';

        $response = $this->http()->post(self::PAY_URL, [
            'headers' => [
                'Content-Type' => 'application/xml'
            ],
            'body' => $xml
        ]);

        $contents = $response->getBody()->getContents();

        $res = Xml::toArray($contents);

        if (!$res['code_url']) {
            $this->log($contents);
            throw new JSONException("下单失败，详细请查看插件日志");
        }

        $payEntity = new PayEntity();
        $payEntity->setType(self::TYPE_LOCAL_RENDER);
        $payEntity->setUrl($res['code_url']);
        $payEntity->setOption(['returnUrl' => $this->returnUrl]);

        return $payEntity;
    }
}