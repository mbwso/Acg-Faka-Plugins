<?php
declare(strict_types=1);

namespace App\Pay\SuperPay\Impl;

use App\Entity\PayEntity;
use App\Pay\Base;
use App\Util\Client;
use App\Util\Http;
use Kernel\Exception\JSONException;

/**
 * Class Pay
 * @package App\Pay\Kvmpay\Impl
 */
class Pay extends Base implements \App\Pay\Pay
{

    /**
     * @return PayEntity
     * @throws JSONException
     */
    public function trade(): PayEntity
    {

        if (!$this->config['url'] ||
            !$this->config['pid'] ||
            !isset($this->config['version']) ||
            ($this->config['version'] == 1 && (!$this->config['private_key'] || !$this->config['platform_public_key'])) ||
            ($this->config['version'] == 0 && !$this->config['key'])) {
            throw new JSONException("重要参数缺失，请检查插件配置文件！");
        }

        $title = isset($this->config['order_title']) ? str_replace('${trade_no}', $this->tradeNo, $this->config['order_title']) : "商品订单号:{$this->tradeNo}";

        $param = [
            'pid' => $this->config['pid'],
            'channel_id' => $this->config['channel_id'],
            'subject' => $title,
            'paytype_code' => $this->code,
            'total_amount' => $this->amount,
            'out_trade_no' => $this->tradeNo,
            'notify_url' => $this->callbackUrl,
            'return_url' => $this->returnUrl,
            'timestamp' => time(),
            'client_ip' => $this->clientIp
        ];


        $url = trim($this->config['url'], "/") . "/openapi/pay/create";

        if ($this->config['version'] == 1) {
            $param['sign'] = Signature::rsa($param, $this->config['private_key']);
            $param['sign_type'] = "RSA";
        } elseif ($this->config['version'] == 0) {
            $param['sign'] = Signature::md5($param, $this->config['key']);
            $param['sign_type'] = "MD5";
        } else {
            throw new JSONException("支付接口出错，下单失败！");
        }


        try {
            $response = Http::make()->post($url, [
                "form_params" => $param
            ]);

            $json = json_decode($response->getBody()->getContents(), true);

            if (empty($json)) {
                throw new JSONException("下单失败#0");
            }

            if (!isset($json['code']) || $json['code'] != 1) {
                throw new JSONException($json['msg'] ?? "下单失败#1");
            }

            if (!isset($json['data']['pay_url'])) {
                throw new JSONException("下单失败#2");
            }

            $payEntity = new PayEntity();
            $payEntity->setUrl($json['data']['pay_url']);
            $payEntity->setType(self::TYPE_REDIRECT);
            return $payEntity;
        } catch (\Throwable $e) {
            throw new JSONException("支付接口出错，请查看插件日志");
        }
    }
}