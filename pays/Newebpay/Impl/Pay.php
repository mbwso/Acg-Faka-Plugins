<?php
declare(strict_types=1);

namespace App\Pay\Newebpay\Impl;

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
     */
    public function trade(): PayEntity
    {
        $post_data = [
            'MerchantID' => $this->config['merchantId'],
            'RespondType' => 'JSON',
            'TimeStamp' => time(),
            'Version' => '1.4',
            'MerchantOrderNo' => $this->tradeNo,
            'Amt' => (int)$this->amount,
            'ItemDesc' => $this->tradeNo,
            'Email' => "",
            'LoginType' => '0',
            'NotifyURL' => $this->callbackUrl, //幕後
            'ReturnURL' => $this->returnUrl, //幕前(線上)
            'ClientBackURL' => $this->returnUrl //取消交易
        ];

        $hash_key = $this->config['aes_key'];
        $hash_iv = $this->config['aes_iv'];

        $aes = Signature::createAesEncrypt($post_data, $hash_key, $hash_iv);
        $sha256 = Signature::aesSha256Str($aes, $hash_key, $hash_iv);

        $trans_data = array(
            'MerchantID' => $this->config['merchantId'],
            'TradeInfo' => $aes,
            'TradeSha' => $sha256,
            'Version' => '1.4',
            'CartVersion' => "ACG-SHOP:" . config("app")['version']
        );

        $payEntity = new PayEntity();
        $payEntity->setUrl($this->config['url'] . '/MPG/mpg_gateway');
        $payEntity->setOption($trans_data);
        $payEntity->setType(self::TYPE_SUBMIT);
        return $payEntity;
    }
}