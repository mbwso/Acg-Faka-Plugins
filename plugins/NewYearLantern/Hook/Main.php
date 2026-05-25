<?php
declare(strict_types=1);

namespace App\Plugin\NewYearLantern\Hook;

use App\Controller\Base\View\UserPlugin;
use App\Util\Plugin;
use Kernel\Annotation\Hook;

class Main extends UserPlugin
{
    const Holiday = [
        ["元", "旦", "快", "乐"],
        ["新", "年", "快", "乐"],
    ];

    /**
     * @throws \Kernel\Exception\ViewException
     */
    #[Hook(point: \App\Consts\Hook::USER_VIEW_INDEX_BODY)]
    public function loadHtml()
    {

        $config = Plugin::getConfig("NewYearLantern");
        echo $this->render("灯笼", "Lantern.html", ['hol' => self::Holiday[(int)$config['holiday']]]);
    }
}