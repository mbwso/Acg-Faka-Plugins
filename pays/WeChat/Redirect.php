<?php
declare (strict_types=1);

use App\Pay\WeChat\Entity\Request;
use App\Util\Aes;
use App\Util\Client;
use App\Util\Http;
use App\Util\Str;

require('../../../vendor/autoload.php');

const BASE_PATH = __DIR__ . "/../../../";

if (!isset($_GET['code'])) {
    exit('code error');
}

$code = $_GET['code'];

$config = require ("Config/Config.php") ?: [];

$response = Http::make()->get("https://api.weixin.qq.com/sns/oauth2/access_token?appid={$config['app_id']}&secret={$config['app_secret']}&code={$code}&grant_type=authorization_code");

$json = json_decode($response->getBody()->getContents() ?: "", true) ?: [];

if (!isset($json['openid'], $_GET['state'])) {
    exit('openid error');
}

$openid = $json['openid'];
$tradeNo = $_GET['state'];

$cache = "Cache/{$tradeNo}";

if (!file_exists($cache)) {
    exit('order error');
}
$encryptKey = substr(md5($config['key']), 0, 16);

/**
 * @var Request $request
 */
$request = unserialize(Aes::decrypt(file_get_contents($cache), $encryptKey, $encryptKey));
$request->setType("JSAPI");

try {
    $request->setOpenid($openid);
    $trade = \App\Pay\WeChat\Service\Request::make()->trade($request, Client::getAddress());

    $arr = [
        "appId" => $request->appId,
        "timeStamp" => (string)time(),
        "nonceStr" => Str::generateRandStr(16),
        "package" => "prepay_id=" . $trade['prepay_id'],
        "signType" => 'MD5'
    ];
    $arr['paySign'] = \App\Pay\WeChat\Service\Request::make()->generateSign($arr, $request->apiKey);

    $jsApiParameters = json_encode($arr);
} catch (Throwable $e) {
    exit($e->getMessage());
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>微信支付收银台</title>
    <meta charset="utf-8"/>
    <meta name="viewport" content="initial-scale=1.0, width=device-width, user-scalable=no"/>
    <style>
        * {
            padding: 0;
            margin: 0;
        }

        body {
            font: 12px "微软雅黑", Arial;
            background: #efeff4;
            min-width: 320px;
            max-width: 640px;
            color: #000;
        }

        a {
            text-decoration: none;
            color: #666666;
        }

        a, img {
            border: none;
        }

        img {
            vertical-align: middle;
        }

        ul, li {
            list-style: none;
        }

        em, i {
            font-style: normal;
        }

        .fr {
            float: right
        }

        .all_w {
            width: 91.3%;
            margin: 0 auto;
        }

        .header {
            background: #393a3e;
            color: #f5f7f6;
            height: auto;
            overflow: hidden;
        }

        .gofh {
            float: left;
            height: 45px;
            display: -webkit-box;
            -webkit-box-orient: horizontal;
            -webkit-box-pack: center;
            -webkit-box-align: center;
        }

        .gofh a {
            padding-right: 10px;
            border-right: 1px solid #2e2f33;
        }

        .gofh a img {
            width: 40%;
        }

        .ttwenz {
            float: left;
            height: 45px;
        }

        .ttwenz h4 {
            font-size: 16px;
            font-weight: 400;
            margin-top: 2px;
        }

        .ttwenz h5 {
            font-size: 12px;
            font-weight: 400;
            color: #6c7071;
        }

        .wenx_xx {
            text-align: center;
            font-size: 16px;
            padding: 18px 0;
        }

        .wenx_xx .wxzf_price {
            font-size: 36px;
            font-weight: bolder;
        }

        .wenx_xx .wxzf_trade_no {
            font-size: 18px;
            font-weight: bolder;
        }

        .skf_xinf {
            height: 43px;
            border-top: 1px solid #ddd;
            border-bottom: 1px solid #ddd;
            line-height: 43px;
            background: #FFF;
            font-size: 12px;
            overflow: hidden;
        }

        .skf_xinf .bt {
            color: #767676;
            float: left;
            font-size: 14px;
        }

        .ljzf_but {
            border-radius: 10px;
            height: 45px;
            line-height: 45px;
            background: #07c060;
            display: block;
            text-align: center;
            font-size: 16px;
            margin-top: 14px;
            color: #fff;
        }

        .qsrzfmm_bt a {
            display: block;
            width: 10%;
            padding: 10px 0;
            text-align: center;
        }

        .qsrzfmm_bt img.tx {
            width: 10%;
            padding: 10px 0;
        }

        .qsrzfmm_bt span {
            padding: 15px 5px;
        }

        .zfmmxx_shop .mz {
            font-size: 14px;
            float: left;
            width: 100%;
        }

        .zfmmxx_shop .wxzf_price {
            font-size: 24px;
            float: left;
            width: 100%;
        }

        .blank_yh img {
            height: 40px;
        }

        .mm_box li {
            border-right: 1px solid #efefef;
            height: 40px;
            float: left;
            width: 16.3%;
            background: #FFF;
        }

        .mm_box li:last-child {
            border-right: none;
        }

        .nub_ggg li {
            width: 33.3333%;
            border-bottom: 1px solid #dadada;
            float: left;
            text-align: center;
            font-size: 22px;
        }

        .nub_ggg li a {
            display: block;
            color: #000;
            height: 50px;
            line-height: 50px;
            overflow: hidden;
        }

        .nub_ggg li a:active {
            background: #e0e0e0;
        }

        .nub_ggg li a.zj_x {
            border-left: 1px solid #dadada;
            border-right: 1px solid #dadada;
        }

        .nub_ggg li span {
            display: block;
            color: #e0e0e0;
            background: #e0e0e0;
            height: 50px;
            line-height: 50px;
            overflow: hidden;
        }

        .nub_ggg li span.del img {
            width: 30%;
        }

        .zfcg_box img {
            width: 10%;
        }

        .spxx_shop td {
            color: #7b7b7b;
            font-size: 14px;
            padding: 10px 0;
        }

        .wzxfcgde_tb img {
            width: 20.6%;
        }
    </style>
    <script type="text/javascript">
        const _is_wx = localStorage.getItem("_is_wx");
        localStorage.removeItem("_is_wx");

        function jsApiCall() {
            WeixinJSBridge.invoke(
                'getBrandWCPayRequest',
                <?php echo $jsApiParameters;?>,
                function (res) {
                    if (res.err_msg == 'get_brand_wcpay_request:ok') {
                        window.location.href = (_is_wx == 1) ? "<?php echo $request->returnUrl;?>" : "/app/Pay/WeChat/Success.php";
                    } else {
                        //支付失败
                        //window.location.href = "/app/Pay/WeChat/Error.php";
                    }
                }
            );
        }

        function callpay() {
            if (typeof WeixinJSBridge == "undefined") {
                if (document.addEventListener) {
                    document.addEventListener('WeixinJSBridgeReady', jsApiCall, false);
                } else if (document.attachEvent) {
                    document.attachEvent('WeixinJSBridgeReady', jsApiCall);
                    document.attachEvent('onWeixinJSBridgeReady', jsApiCall);
                }
            } else {
                jsApiCall();
            }
        }

        callpay();
    </script>
</head>
<body>
<div class="wenx_xx">
    <div class="wxzf_trade_no"><?php echo $request->tradeNo ?></div>
    <div class="wxzf_price">￥<?php echo $request->amount; ?></div>
</div>
<div class="skf_xinf">
    <div class="all_w"><span class="bt">收款方</span> <span class="fr" style="font-size: 14px;"><?php echo $config['payee'] ?? "收款方已隐藏";?></span></div>
</div>
<a href="javascript:callpay();" class="ljzf_but all_w">立即支付</a>
</body>
</html>