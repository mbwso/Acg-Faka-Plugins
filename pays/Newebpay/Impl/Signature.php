<?php
declare(strict_types=1);

namespace App\Pay\Newebpay\Impl;

use JetBrains\PhpStorm\Pure;
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
     * @param string $string
     * @param int $blocksize
     * @return string
     */
    public static function addPadding(string $string, int $blocksize = 32): string
    {
        $len = strlen($string);
        $pad = $blocksize - ($len % $blocksize);
        $string .= str_repeat(chr($pad), $pad);
        return $string;
    }

    /**
     * @param string $string
     * @return bool|string
     */
    public static function strPadding(string $string): bool|string
    {
        $slast = ord(substr($string, -1));
        $slastc = chr($slast);
        if (preg_match("/$slastc{" . $slast . "}/", $string)) {
            $string = substr($string, 0, strlen($string) - $slast);
            return $string;
        } else {
            return false;
        }
    }

    /**
     * @param array $parameter
     * @param string $key
     * @param string $iv
     * @return string
     */
    public static function createAesEncrypt(array $parameter, string $key = "", string $iv = ""): string
    {
        $return_str = '';
        if (!empty($parameter)) {
            ksort($parameter);
            $return_str = http_build_query($parameter, '', '&');
        }
        return trim(bin2hex(openssl_encrypt(self::addPadding($return_str), 'aes-256-cbc', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv)));
    }

    private static function decryptJsoData(string $dec_str)
    {
        $dec_data = json_decode($dec_str, true);
        $dec_data['Result']['Status'] = $dec_data['Status'];
        $dec_data['Result']['Message'] = $dec_data['Message'];
        return $dec_data['Result']; //整理成跟String回傳相同格式
    }

    private static function decryptStrData(string $dec_str)
    {
        $dec_data = explode('&', $dec_str);
        foreach ($dec_data as $_ind => $value) {
            $trans_data = explode('=', $value);
            $return_data[$trans_data[0]] = $trans_data[1];
        }
        return $return_data;
    }


    public static function createAesDecrypt(string $parameter = "", string $key = "", string $iv = "")
    {
        $decrypt_data = self::strPadding(openssl_decrypt(hex2bin($parameter), 'AES-256-CBC', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv));
        if (!$decrypt_data) {
            return false;
        }
        if (json_decode($decrypt_data)) {
            $return_data = self::decryptJsoData($decrypt_data);
        } else {
            $return_data = self::decryptStrData($decrypt_data);
        }
        return $return_data;
    }


    /**
     * @param string $str
     * @param string $key
     * @param string $iv
     * @return string
     */
    public static function aesSha256Str(string $str, string $key = "", string $iv = ""): string
    {
        return strtoupper(hash("sha256", 'HashKey=' . $key . '&' . $str . '&HashIV=' . $iv));
    }

    /**
     * @param array $return_data
     * @param string $hash_key
     * @param string $hash_iv
     * @return bool
     */
    public static function chkShaIsVaildByReturnData(array $return_data, string $hash_key, string $hash_iv): bool
    {
        if (empty($return_data['TradeSha'])) return false;
        if (empty($return_data['TradeInfo'])) return false;
        if (empty($hash_key) || empty($hash_iv)) return false;

        $local_sha = self::aesSha256Str($return_data['TradeInfo'], $hash_key, $hash_iv);

        if (!self::safetyEquals($return_data['TradeSha'], $local_sha)) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function verification(array $data, array $config): bool
    {
        if (!$this->chkShaIsVaildByReturnData($data, $config['aes_key'], $config['aes_iv'])) {
            return false;
        }
        $decryptData = self::createAesDecrypt($data['TradeInfo'], $config['aes_key'], $config['aes_iv']);
        Context::set("FROM_PAY_DATA", $decryptData);
        return true;
    }
}