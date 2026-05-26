<?php
declare(strict_types=1);

namespace App\Pay\YsmWxPay\Impl;

use Kernel\Exception\JSONException;

/**
 * Class Signature
 * @package App\Pay\Kvmpay\Impl
 */
class Signature implements \App\Pay\Signature
{

    /**
     * 生成签名
     * @param array $data
     * @param string $secret
     * @return string
     */
    public static function HashSign(array $data, $secret)
    {
        if (isset($data['sign'])) {
            unset($data['sign']);
        }
        ksort($data);
        reset($data);
        $str = '';
        foreach ($data as $key => $row) {
            if ($key == 'hash' || is_null($row) || $row === '') {
                continue;
            }
            if ($str) {
                $str .= '&';
            }
            $str .= "$key=$row";
        }
        return hash('sha256', $str . $secret, false);

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
     * @inheritDoc
     */
    public function verification(array $data, array $config): bool
    {
        $appid = $config['appid'];//商户号
        $secret = $config['secret'];//商户密钥
        if (!isset($data['appid']) || $data['appid'] != $appid) {
            return false;
        }
        $sign = self::HashSign($data, $secret);
        if (!self::safetyEquals($data['sign'], $sign)) {
            return false;
        }
        return true;
    }

    //post提交
    public static function HttpPost($url, $data)
    {
        $header = array(
            'Content-Type:' . 'application/json; charset=UTF-8',
            'Accept:application/json',
            'User-Agent:*/*',
            'Authorization: WECHATPAY2-SHA256-RSA2048 '
        );
        $curl = curl_init(); // 启动一个CURL会话
        curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2); // 从证书中检查SSL加密算法是否存在
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); // 模拟用户使用的浏览器
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data); // Post提交的数据包
        curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        $result = curl_exec($curl); // 执行操作
        if (curl_errno($curl)) {
            echo 'Errno' . curl_error($curl);//捕抓异常
        }
        curl_close($curl); // 关闭CURL会话
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
            $clientkeywords = array(
                'nokia'
            , 'sony'
            , 'ericsson'
            , 'mot'
            , 'samsung'
            , 'htc'
            , 'sgh'
            , 'lg'
            , 'sharp'
            , 'sie-'
            , 'philips'
            , 'panasonic'
            , 'alcatel'
            , 'lenovo'
            , 'iphone'
            , 'ipod'
            , 'blackberry'
            , 'meizu'
            , 'android'
            , 'netfront'
            , 'symbian'
            , 'ucweb'
            , 'windowsce'
            , 'palm'
            , 'operamini'
            , 'operamobi'
            , 'openwave'
            , 'nexusone'
            , 'cldc'
            , 'midp'
            , 'wap'
            , 'mobile'
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