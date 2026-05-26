<?php
declare(strict_types=1);

namespace App\Pay\LtzfAliPay\Impl;

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
     * @throws JSONException
     * @throws GuzzleException
     */
    public function trade(): PayEntity
    {
        
        if (!$this->config['mch_id']) {
            throw new JSONException("请配置商户号");
        }

        if (!$this->config['mch_key']) {
            throw new JSONException("请配置商户密钥");
        }

        $param = [
            'mch_id' => $this->config['mch_id'],//商户号
            'out_trade_no' => $this->tradeNo,//商户订单号，只能是数字、大小写字母_-且在同一个商户号下唯一。
            'total_fee' => $this->amount,//支付金额
            'body' => $this->tradeNo,//商品描述
            'timestamp' => time(),//当前时间戳
            'notify_url' => $this->callbackUrl,//支付通知地址，通知URL必须为直接可访问的URL，不允许携带查询串，要求必须为http或https地址，回调通知参数请参考《支付通知》。
        ];

        $param['sign'] = Signature::generateSignature($param, $this->config['mch_key']);
        $param['time_expire'] = '30m';//订单失效时间
        $param['developer_appid'] = '1071675594217739';//开发者应用ID

        if (!Signature::isMobile()) {
            //扫码支付
            $url = $this->config['url'] . '/api/alipay/native';
        }else{
            //H5支付
            $param['return_url'] = $this->returnUrl;//回跳地址，支付成功后或取消支付自动跳转到该地址，跳转不会携带任何参数，如需携带参数请自行拼接。
            $url = $this->config['url'] . '/api/alipay/h5';
            
        }

        $json = Signature::httpPost($url, $param);
        
        if(!isset($json['code'])){
            throw new JSONException("支付接口调用失败");
        }
        if ($json['code'] != 0) {
            throw new JSONException((string)$json['msg']);
        }

        $payEntity = new PayEntity();
 
        if (!Signature::isMobile()) {
            $payEntity->setType(self::TYPE_LOCAL_RENDER);
            $payEntity->setUrl($json['data']);
        } else {
            //H5支付
            $payEntity->setType(self::TYPE_REDIRECT);
            $payEntity->setUrl($json['data']['order_url']);
            
        }
        return $payEntity;
    }
}