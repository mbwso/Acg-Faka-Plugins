<?php
declare (strict_types=1);

namespace App\Plugin\GiteeStar\Hook;

use App\Util\Http;
use App\Util\Plugin;
use GuzzleHttp\Exception\GuzzleException;
use Kernel\Annotation\Hook;
use Kernel\Exception\JSONException;

class Main
{

    /**
     * @throws GuzzleException
     * @throws JSONException
     */
    #[Hook(point: \App\Consts\Hook::USER_API_ORDER_TRADE_BEGIN)]
    public function trade(): void
    {
        $cfg = Plugin::getConfig("GiteeStar");
        $gitee = (string)$_POST['gitee'];

        if (!isset($_POST['gitee'])) {
            return;
        }

        if (!$gitee) {
            throw new JSONException("请输入正确的Github账号（不是邮箱）");
        }

        $cache = Plugin::getCache("GiteeStar", "Db", $gitee);

        if ($cache) {
            throw new JSONException("你已经领取过了，无法再领取了。");
        }

        $response = Http::make([
            "headers" => [
                "Host" => "github.com",
                "Connection" => "keep-alive",
                "User-Agent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.4844.84 Safari/537.36"
            ]
        ])->get("https://gitee.com/" . trim((string)$cfg['project'], "/") . "/stargazers");


        try {
            $contents = $response->getBody()->getContents();
        } catch (\Error|\Exception $e) {
            Plugin::log("GiteeStar", "Gitee请求异常：" . $e->getMessage());
            throw new JSONException("Gitee请求异常");
        }

        preg_match_all('#<a target="_blank" class="js-popover-card disable" href=".*?">(.*?)</a>#', $contents, $list);
        $users = (array)$list[1];

        if (!in_array($gitee, $users)) {
            throw new JSONException("没有检测到您为我的项目点赞，请先到Gitee项目点赞后，再来领取。");
        }

        //记录缓存
        Plugin::setCache("GiteeStar", "Db", $gitee, true);
    }
}