<?php
declare(strict_types=1);

namespace App\Pay\Bank\Impl;
/**
 *
 */
class Xml
{

    /**
     * @param string $str
     * @return array
     */
    public static function toArray(string $str): array
    {
        $obj = simplexml_load_string($str, "SimpleXMLElement", LIBXML_NOCDATA);
        return (array)json_decode(json_encode($obj), true);
    }
}