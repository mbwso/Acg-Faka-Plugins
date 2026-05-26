<?php
declare (strict_types=1);

namespace App\Pay\WeChat\Service;


use App\Pay\WeChat\Entity;
use App\Pay\WeChat\Util\Arr;
use App\Pay\WeChat\Util\Decimal;
use App\Util\Http;
use App\Util\Str;
use GuzzleHttp\Exception\GuzzleException;
use Kernel\Exception\JSONException;

class Request
{

    /**
     * @return static
     */
    public static function make(): static
    {
        return new self();
    }

    /**
     * 微信支付接口域名
     * @var string
     */
    private string $baseUrl = "https://api.mch.weixin.qq.com";


    /**
     * @param Entity\Request $request
     * @param string $createIp
     * @return array
     * @throws GuzzleException
     * @throws JSONException
     */
    public function trade(Entity\Request $request, string $createIp): array
    {
        $options = [
            'appid' => $request->appId,
            'attach' => 'pay',
            'body' => $request->body,
            'mch_id' => $request->mchId,
            'nonce_str' => Str::generateRandStr(16),
            'notify_url' => $request->notifyUrl,
            'out_trade_no' => $request->tradeNo,
            'spbill_create_ip' => $createIp,
            'total_fee' => (new Decimal($request->amount, 2))->mul(100)->getAmount(0),
            'trade_type' => $request->type
        ];

        //jsapi支付
        $request->openid && $options['openid'] = $request->openid;

        $options = array_merge($options, $request->options);
        $options['sign'] = $this->generateSign($options, $request->apiKey);

        $xml = $this->arrToXml($options);

        $response = Http::make()->post("{$this->baseUrl}/pay/unifiedorder", [
            'headers' => [
                'Content-Type' => 'application/xml',
            ],
            'body' => $xml
        ]);

        $contents = $response->getBody()->getContents();

        $result = Arr::xmlToArray($contents);

        if (empty($result)) {
            throw new JSONException("请求微信没有获取到数据");
        }

        if ($result['return_code'] != "SUCCESS") {
            throw new JSONException((string)$result['return_msg']);
        }
        if ($result['result_code'] != "SUCCESS") {
            throw new JSONException((string)$result['err_code']);
        }

        return $result;
    }

    /**
     * @param mixed $str
     * @param string $local
     * @return bool
     */
    public static function safetyEquals(mixed $str, string $local): bool
    {
        if (!is_string($str) || $str === '') {
            return false;
        }

        return hash_equals($local, $str);
    }


    /**
     * @param Entity\Request $request
     * @param array $data
     * @return bool
     */
    public function verification(Entity\Request $request, array $data): bool
    {

        if (!isset($data['return_code']) || $data['return_code'] != "SUCCESS") {
            return false;
        }

        if (!isset($data['result_code']) || $data['result_code'] != "SUCCESS") {
            return false;
        }

        //签名验证
        if (!self::safetyEquals($data['sign'], $this->generateSign($data, $request->apiKey))) {
            return false;
        }

        return true;
    }

    /**
     * @param array $arr
     * @return string
     */
    private function arrToXml(array $arr): string
    {
        $xml = "<xml>";
        foreach ($arr as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

    /**
     * 获取签名
     */
    public function generateSign(array $params, string $key): string
    {
        unset($params['sign']);
        ksort($params, SORT_STRING);
        $unSignParaString = $this->httpBuildQuery($params);
        return strtoupper(md5($unSignParaString . "&key=" . $key));
    }

    /**
     * @param array $params
     * @return string
     */
    private function httpBuildQuery(array $params): string
    {
        $buff = "";
        ksort($params);
        foreach ($params as $k => $v) {
            if (null != $v && "null" != $v) {
                $buff .= $k . "=" . $v . "&";
            }
        }
        $s = '';
        if (strlen($buff) > 0) {
            $s = substr($buff, 0, strlen($buff) - 1);
        }
        return $s;
    }
}