<?php

namespace App\Pay\Jihe\Impl;

use App\Util\Http;
use GuzzleHttp\Exception\GuzzleException;

class Common
{
    public string $url;

    public string $appid;

    public string $secret_key;

    public false|\OpenSSLAsymmetricKey $publicKey;

    public false|\OpenSSLAsymmetricKey $privateKey;

    /**
     * @param string $url
     * @param string $appid
     * @param string $secretKey
     * @param string $publicKey
     * @param string $privateKey
     */
    public function __construct(string $url, string $appid, string $secretKey, string $publicKey, string $privateKey)
    {
        $this->url = $url;
        $this->appid = $appid;
        $this->secret_key = $secretKey;

        //私钥太长，格式不对。使用此方法格式化私钥
        $private_key = str_replace(array("\r\n", "\r", "\n"), "", $privateKey);
        $private_key = "-----BEGIN PRIVATE KEY-----" . PHP_EOL . wordwrap($private_key, 64, PHP_EOL, true) . PHP_EOL . "-----END PRIVATE KEY-----";
        //这个函数可用来判断私钥是否是可用的，可用返回资源id Resource id
        $this->privateKey = openssl_pkey_get_private($private_key);

        //公钥太长，格式不对。使用此方法格式化公钥
        $public_key = str_replace(array("\r\n", "\r", "\n"), "", $publicKey);
        $public_key = "-----BEGIN PUBLIC KEY-----" . PHP_EOL . wordwrap($public_key, 64, PHP_EOL, true) . PHP_EOL . "-----END PUBLIC KEY-----";
        //这个函数可用来判断公钥是否是可用的
        $this->publicKey = openssl_pkey_get_public($public_key);

    }


    /**
     * @param string $data
     * @return string
     */
    public function signByPrivateKey(string $data): string
    {
        openssl_sign($data, $signature, $this->privateKey);
        return base64_encode($signature); //加密后的内容通常含有特殊字符，需要编码转换下，在网络间通过url传输时要注意base64编码是否是url安全的
    }

    /**
     * 需要签名的参数排序
     *1.需要根据参数名的首字母,按从 a 到 z 的顺序进行排序.若首字母相同,则根据第二个字母进行排序,以此类推
     *2.排序完成后,再把所有参数以”&”字符作为分隔符进行连接
     * @param array $result
     * @return string
     */
    public function ksortToString(array $result): string
    {
        ksort($result);
        $signStr = "";
        foreach ($result as $key => $val) {
            if ($val) $signStr .= $key . '=' . $val . '&';
        }
        $signStr = trim($signStr, '&');
        return $signStr;
    }


    /**
     * @param array $parJson
     * @return array
     * @throws GuzzleException
     */
    public function getRequest(string $tradeNo, array $parJson): array
    {
        $myParams = array();
        //公共请求参数
        $myParams['requestNo'] = $tradeNo;
        $myParams['reqTime'] = date('Y-m-d H:i:s');
        $myParams['appid'] = $this->appid;
        $myParams['channel'] = 'ys';
        $myParams['version'] = '2.0';
        $myParams['serveNo'] = 'actionPay';
        //业务请求参数
        ksort($parJson);
        $parNewJson = [];
        foreach ($parJson as $key => $val) {
            if ($val) $parNewJson[$key] = $val;
        }
        $myParams['parJson'] = json_encode($parNewJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);//构造字符串
        $signStr = $this->ksortToString($myParams);
        $myParams['sign'] = $this->signByPrivateKey($signStr . '&secret=' . $this->secret_key);


        $response = Http::make()->post($this->url . '/saas/v2/trade', [
            'form_params' => $myParams
        ]);
        $contents = $response->getBody()->getContents();
        return json_decode($contents, true);
    }


}