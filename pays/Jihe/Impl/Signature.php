<?php
declare(strict_types=1);

namespace App\Pay\Jihe\Impl;

use Kernel\Util\Context;

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

        $data['parJson'] = json_encode($data['parJson'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $sign = '';
        foreach ($data as $k => $v) {
            $sign .= $k . '=' . $v . '&';
        }
        $sign = trim($sign, '&');


        return md5($sign . $key);
    }

    /**
     * @param array $result
     * @return string
     */
    private static function ksortToString(array $result): string
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
     * @inheritDoc
     */
    public function verification(array $data, array $config): bool
    {
        parse_str(file_get_contents('php://input'), $data);
        Context::set(\App\Consts\Pay::DAFA, $data);
        $sign = $data['sign'];
        unset($data['sign']);
        $generateSignature = strtoupper(md5(self::ksortToString($data) . "&appid={$config['appid']}&secret={$config['secret_key']}"));
        if (!self::safetyEquals($sign, $generateSignature)) {
            return false;
        }
        return true;
    }
}