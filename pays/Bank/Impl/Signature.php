<?php
declare(strict_types=1);

namespace App\Pay\Bank\Impl;

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
     * @param array $data
     * @param string $appKey
     * @return string
     */
    public static function generateSignature(array $data, string $appKey): string
    {
        $signPars = "";
        ksort($data);
        foreach ($data as $k => $v) {
            if ("" != $v && "sign" != $k) {
                $signPars .= $k . "=" . $v . "&";
            }
        }
        $signPars .= "key=" . $appKey;
        $sign = strtoupper(md5($signPars));
        return $sign;
    }

    /**
     * @inheritDoc
     */
    public function verification(array $data, array $config): bool
    {
        $data = Xml::toArray((string)file_get_contents("php://input"));
        $generateSignature = self::generateSignature($data, $config['key']);
        if (!self::safetyEquals($data['sign'], $generateSignature)) {
            return false;
        }
        $data['total_fee'] = (float)$data['total_fee'] / 100;
        Context::set(\App\Consts\Pay::DAFA, $data);
        return true;
    }

}