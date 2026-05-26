<?php
declare(strict_types=1);

namespace App\Pay\WeChat\Service;

use App\Entity\PayEntity;
use App\Pay\WeChat\Entity\Request;
use App\Util\Aes;
use Error;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Kernel\Exception\JSONException;

class Wechat
{

    public const CACHE_PATH = BASE_PATH . "/app/Pay/WeChat/Cache";

    /**
     * @return static
     */
    public static function make(): static
    {
        return new self();
    }


    /**
     * @param Request $request
     * @param string $clientIp
     * @return PayEntity
     * @throws JSONException
     */
    public function native(Request $request, string $clientIp): PayEntity
    {
        try {
            $request->setType("NATIVE");
            $trade = \App\Pay\WeChat\Service\Request::make()->trade($request, $clientIp);

            if (!isset($trade['code_url'])) {
                throw new JSONException("没有获取到code_url");
            }

            $pay = new PayEntity();
            $pay->setType(3);
            $pay->setUrl($trade['code_url']);
            $pay->setOption(['returnUrl' => $request->returnUrl]);
            return $pay;
        } catch (\Throwable $e) {
            throw new JSONException($e->getMessage());
        }
    }

    /**
     * @param Request $request
     * @param string $clientIp
     * @return PayEntity
     * @throws GuzzleException
     * @throws JSONException
     */
    public function h5(Request $request, string $clientIp): PayEntity
    {
        try {
            $request->setType("MWEB");

            $trade = \App\Pay\WeChat\Service\Request::make()->trade($request, $clientIp);

            if (!isset($trade['mweb_url'])) {
                throw new JSONException("没有获取到code_url");
            }

            $pay = new PayEntity();
            $pay->setType(2);
            $pay->setUrl($trade['mweb_url'] . '&redirect_url=' . urlencode($request->returnUrl));
            return $pay;
        } catch (Error|Exception $e) {
            throw new JSONException($e->getMessage());
        }
    }


    /**
     * @param Request $request
     * @return PayEntity
     */
    public function jsapi(Request $request): PayEntity
    {

        if (!is_dir(self::CACHE_PATH)) {
            mkdir(self::CACHE_PATH, 0755, true);;
        }

        $encryptKey = substr(md5($request->apiKey), 0, 16);

        file_put_contents(self::CACHE_PATH . "/{$request->tradeNo}", Aes::encrypt(serialize($request), $encryptKey, $encryptKey));

        $pay = new PayEntity();
        $pay->setType(3);
        $pay->setUrl("https://open.weixin.qq.com/connect/oauth2/authorize?appid={$request->appId}&redirect_uri={$request->webUrl}/app/Pay/WeChat/Redirect.php&response_type=code&scope=snsapi_base&state={$request->tradeNo}#wechat_redirect");
        $pay->setOption(['returnUrl' => $request->returnUrl]);
        return $pay;
    }
}