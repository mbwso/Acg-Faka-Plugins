<?php
declare(strict_types=1);

namespace App\Pay\YsmAliPay\Impl;

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
        
        if (!$this->config['appid']) {
            throw new JSONException("请先配置商户APPID");
        }

        if (!$this->config['secret']) {
            throw new JSONException("请先配置商户AppSecret");
        }
        
        $url = 'https://www.yishoumi.cn/u/payment';
        $params = array();
        
        $params['appid'] = $this->config['appid'];
        $params['mch_orderid'] = $this->tradeNo;//订单号
        $params['description'] = $this->tradeNo;//订单标题
        $params['total'] = (int) strval($this->amount * 100);//订单金额
        $params['notify_url'] = $this->callbackUrl;//支付结果通知地址
        $params['nopay_url'] = $this->returnUrl;//支付成功或取消支跳转到该地址
        $params['callback_url'] = $this->returnUrl;//支付成功或取消支跳转到该地址
        $params['time'] = time();//当前时间戳
        $params['nonce_str'] = bin2hex(random_bytes(16));//随机字符串
        $params['payType'] = 11;//支付方式
        $params['plugin'] = 'acg';//插件辨识
        $params['sign'] = Signature::HashSign($params, $this->config['secret']);
        $result = Signature::HttpPost($url, json_encode($params));
        
        if(!isset($result['code'])){
            throw new JSONException("支付接口调用失败");
        }
        if ($result['code'] != 0) {
            throw new JSONException((string)$result['msg']);
        }

        $payEntity = new PayEntity();
 
        if (!Signature::isMobile()) {
            $payEntity->setType(self::TYPE_LOCAL_RENDER);
            $payEntity->setUrl($result['url']);
        } else {
            //H5支付
            $payEntity->setType(self::TYPE_REDIRECT);
            $payEntity->setUrl($result['url']);
            
        }
        return $payEntity;
    }
    
    
    
}