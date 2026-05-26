<?php
declare(strict_types=1);

namespace App\Pay\VmqPay\Impl;

use App\Util\Plugin;

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
        $str = "" . $data['payId'] . $data['type'] . $data['price'] . $data['reallyPrice'] . $key;
        return md5($str);
    }

    /**
     * @inheritDoc
     */
    public function verification(array $data, array $config): bool
    {
        $sign = $data['sign'];
        $generateSignature = $this->generateSignature($data, $config['key']);
        if (!self::safetyEquals($sign, $generateSignature)) {
            return false;
        }
        return true;
    }
}