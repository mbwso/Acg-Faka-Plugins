<?php
declare(strict_types=1);

namespace App\Pay\LtzfAliPay\Impl;

use Kernel\Exception\JSONException;

/**
 * Class Signature
 * @package App\Pay\Kvmpay\Impl
 */
class Signature implements \App\Pay\Signature
{
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
     * 生成签名
     * @param array $data
     * @param string $key
     * @return string
     */
    public static function generateSignature(array $data, string $key): string
    {
        ksort($data);
        $sign = strtoupper(md5(urldecode(http_build_query($data)) . '&key=' . $key));
        return $sign;
    }

    /**
     * @inheritDoc
     */
    public function verification(array $data, array $config): bool
    {
        $mch_id = $config['mch_id'];//商户号
        $mch_key = $config['mch_key'];//商户密钥

        if (!isset($data['code']) || $data['code'] != 0) {
            return false;
        }

        if (!isset($data['mch_id']) || $data['mch_id'] != $mch_id) {
            return false;
        }

        $param = array('code' => $data['code'],
            'timestamp' => $data['timestamp'],
            'mch_id' => $data['mch_id'],
            'order_no' => $data['order_no'],
            'out_trade_no' => $data['out_trade_no'],
            'pay_no' => $data['pay_no'],
            'total_fee' => $data['total_fee'],
        );

        $sign = self::generateSignature($param, $mch_key);
        if (!self::safetyEquals($data['sign'], $sign)) {
            return false;
        }
        return true;
    }

    //post提交
    public static function httpPost($url, array $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type:application/x-www-form-urlencoded;charset=UTF-8'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $errno = curl_errno($ch);
        if ($errno) {
            throw new JSONException ('Curl Error ' . $errno . ': ' . curl_error($ch));
        }
        curl_close($ch);
        return json_decode($result, true);
    }

    //移动端判断
    public static function isMobile(): bool
    {
        // 如果有HTTP_X_WAP_PROFILE则一定是移动设备
        if (isset ($_SERVER['HTTP_X_WAP_PROFILE'])) {
            return true;
        }
        // 如果via信息含有wap则一定是移动设备
        if (isset ($_SERVER['HTTP_VIA'])) {
            // 找不到为flase,否则为true
            return stristr($_SERVER['HTTP_VIA'], "wap") ? true : false;
        }
        // 脑残法，判断手机发送的客户端标志,兼容性有待提高
        if (isset ($_SERVER['HTTP_USER_AGENT'])) {
            $clientkeywords = array('nokia',
                'sony',
                'ericsson',
                'mot',
                'samsung',
                'htc',
                'sgh',
                'lg',
                'sharp',
                'sie-',
                'philips',
                'panasonic',
                'alcatel',
                'lenovo',
                'iphone',
                'ipod',
                'blackberry',
                'meizu',
                'android',
                'netfront',
                'symbian',
                'ucweb',
                'windowsce',
                'palm',
                'operamini',
                'operamobi',
                'openwave',
                'nexusone',
                'cldc',
                'midp',
                'wap',
                'mobile'
            );
            // 从HTTP_USER_AGENT中查找手机浏览器的关键字
            if (preg_match("/(" . implode('|', $clientkeywords) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT']))) {
                return true;
            }
        }
        // 协议法，因为有可能不准确，放到最后判断
        if (isset ($_SERVER['HTTP_ACCEPT'])) {
            // 如果只支持wml并且不支持html那一定是移动设备
            // 如果支持wml和html但是wml在html之前则是移动设备
            if ((strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') !== false) && (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false || (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') < strpos($_SERVER['HTTP_ACCEPT'], 'text/html')))) {
                return true;
            }
        }
        return false;
    }
}