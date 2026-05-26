<?php
declare (strict_types=1);

namespace App\Pay\WeChat\Entity;


class Request
{
    public string $mchId;
    public string $appId;
    public string $appSecret;

    public string $apiKey;
    public ?string $body = null;
    public ?string $notifyUrl = null;
    public ?string $returnUrl = null;
    public ?string $tradeNo = null;
    public ?string $amount = null;

    public string $type = "NATIVE"; // NATIVE =扫码， MWEB = H5跳转


    public array $options = [];

    public ?string $openid = null;
    public ?string $webUrl = null;


    public function __construct(string $mchId, string $apiKey, string $appId, string $appSecret)
    {
        $this->mchId = $mchId;
        $this->appId = $appId;
        $this->apiKey = $apiKey;
        $this->appSecret = $appSecret;
    }

    /**
     * @param string|int|float $amount
     */
    public function setAmount(string|int|float $amount): void
    {
        $this->amount = (string)$amount;
    }

    /**
     * @param string|null $body
     */
    public function setBody(?string $body): void
    {
        $this->body = $body;
    }

    /**
     * @param string|null $notifyUrl
     */
    public function setNotifyUrl(?string $notifyUrl): void
    {
        $this->notifyUrl = $notifyUrl;
    }

    /**
     * @param string|null $returnUrl
     */
    public function setReturnUrl(?string $returnUrl): void
    {
        $this->returnUrl = $returnUrl;
    }

    /**
     * @param string|null $tradeNo
     */
    public function setTradeNo(?string $tradeNo): void
    {
        $this->tradeNo = $tradeNo;
    }

    /**
     * @param string $type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * @param array $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    /**
     * @param string $openid
     */
    public function setOpenid(string $openid): void
    {
        $this->openid = $openid;
    }

    /**
     * @param string $webUrl
     * @return void
     */
    public function setWebUrl(string $webUrl): void
    {
        $this->webUrl = trim($webUrl, "/");
    }
}