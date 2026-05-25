<?php
declare (strict_types=1);

namespace App\Plugin\HostRedirect\Hook;

use App\Util\Plugin;
use Kernel\Annotation\Hook;

class Main
{

    #[Hook(point: \App\Consts\Hook::KERNEL_INIT)]
    public function redirect(): void
    {
        $config = Plugin::getConfig("HostRedirect");

        if ((int)$config['host'] == 1) {
            $_SERVER["HTTP_HOST"] = $_SERVER["HTTP_ACG_HOST"];
        }

        if ((int)$config['https'] == 1) {
            $_SERVER["HTTPS"] = "on";
            $_SERVER['REQUEST_SCHEME'] = "https";
        }
    }

}