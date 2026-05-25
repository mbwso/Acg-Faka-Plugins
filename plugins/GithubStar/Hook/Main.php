<?php
declare (strict_types=1);

namespace App\Plugin\GithubStar\Hook;

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
        $cfg = Plugin::getConfig("GithubStar");
        $github = (string)$_POST['github'];

        if (!isset($_POST['github'])) {
            return;
        }

        if (!$github) {
            throw new JSONException("请输入正确的Github账号（不是邮箱）");
        }

        $response = Http::make([
            "headers" => [
                "Host" => "github.com",
                "Connection" => "keep-alive",
                "User-Agent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.4844.84 Safari/537.36"
            ]
        ])->get("https://github.com/" . trim((string)$cfg['project'], "/") . "/stargazers");


        try {
            $contents = $response->getBody()->getContents();
        } catch (\Error|\Exception $e) {
            Plugin::log("GithubStar", "Github请求异常：" . $e->getMessage());
            throw new JSONException("Github请求异常");
        }

        preg_match_all('#<a data-hovercard-type="user" data-hovercard-url=".*?" data-octo-click="hovercard-link-click" data-octo-dimensions="link_type:self" href=".*?">(.*?)</a>#', $contents, $list);
        $users = (array)$list[1]; //github点赞列表


        if (!in_array($github, $users)) {
            throw new JSONException("没有检测到您为我的项目点赞，请先到Github项目点赞后，再来领取。");
        }
    }
}