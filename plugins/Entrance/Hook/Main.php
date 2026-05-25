<?php
declare(strict_types=1);

namespace App\Plugin\Entrance\Hook;


use App\Controller\Base\View\ManagePlugin;
use App\Util\Client;
use App\Util\Plugin;
use App\Util\Str;
use Kernel\Annotation\Hook;
use Kernel\Util\Session;


class Main extends ManagePlugin
{

    /**
     * 安全认证状态
     */
    const ENTRANCE_SESSION = "entrance_status";


    #[Hook(point: \App\Consts\Hook::KERNEL_INIT)]
    public function entrance(): void
    {
        $config = Plugin::getConfig("Entrance");
        if (!isset($config['location'])) {
            return;
        }
        if (trim(trim((string)$config['location']), "/") == "") {
            return;
        }
        $entrance = strtolower(trim(trim($config['location']), "/"));
        $location = explode("/", trim((string)$_GET['s'], "/"));
        $route = strtolower($location[0]);
        $ip = Client::getAddress();

        if ($config['white'] == 1 && $route == "admin") {
            if (!str_contains((string)$config['whitelist'], $ip)) {
                echo $this->render("您的IP被阻挡", "error.html", [
                    "ip" => '<span style="color:red;">' . $ip . '</span>',
                    "ua" => (string)$_SERVER['HTTP_USER_AGENT'],
                    "server_ip" => mt_rand(20, 245) . "." . mt_rand(20, 245) . "." . mt_rand(20, 245) . "." . mt_rand(20, 245),
                    "id" => Str::generateRandStr(16)
                ]);
                exit;
            }
        }

        if ($route == $entrance) {
            //安全通过认证
            Session::set(self::ENTRANCE_SESSION, true);
            Client::redirect("/admin", "安全认证成功，请稍后..", 1);
            return;
        }

        if ($route == "admin") {
            if (!Session::has(self::ENTRANCE_SESSION) || Session::get(self::ENTRANCE_SESSION) !== true) {
                echo $this->render("没有使用正确入口访问后台", "error.html", [
                    "ip" => '<span style="color:red;">' . $ip . '</span>',
                    "ua" => (string)$_SERVER['HTTP_USER_AGENT'],
                    "server_ip" => mt_rand(20, 245) . "." . mt_rand(20, 245) . "." . mt_rand(20, 245) . "." . mt_rand(20, 245),
                    "id" => Str::generateRandStr(16)
                ]);
                exit;
            }
        }
    }
}