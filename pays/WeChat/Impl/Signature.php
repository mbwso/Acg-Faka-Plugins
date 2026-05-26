<?php
declare(strict_types=1);

namespace App\Pay\WeChat\Impl;


use App\Pay\WeChat\Entity\Request;
use App\Pay\WeChat\Service\Wechat;
use App\Pay\WeChat\Util\Arr;
use App\Pay\WeChat\Util\Decimal;
use Kernel\Util\Context;

/**
 *
 */
class Signature implements \App\Pay\Signature
{
    /**
     * @param array $data
     * @param array $config
     * @return bool
     */
    public function verification(array $data, array $config): bool
    {
        $data = Arr::xmlToArray((string)file_get_contents("php://input"));

        if (empty($data)) {
            return false;
        }

        $request = new Request($config['mch_id'], $config['key'], $config['app_id'], $config['app_secret']);
        $request->setAmount($data['total_fee']);

        if (!\App\Pay\WeChat\Service\Request::make()->verification($request, $data)) {
            return false;
        }

        $data['total_fee'] = (new Decimal($data['total_fee'], 2))->div(100)->getAmount();

        Context::set(\App\Consts\Pay::DAFA, $data);


        $tradeNo = $data['out_trade_no'];

        if (is_file(Wechat::CACHE_PATH . "/{$tradeNo}")) {
            @unlink(Wechat::CACHE_PATH . "/{$tradeNo}");
        }

        return true;
    }
}