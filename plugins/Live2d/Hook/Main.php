<?php
declare(strict_types=1);

namespace App\Plugin\Live2d\Hook;

use App\Controller\Base\View\UserPlugin;
use App\Util\Plugin;
use Kernel\Annotation\Hook;


/**
 *
 */
class Main extends UserPlugin
{

    /**
     * @throws \Kernel\Exception\ViewException
     */
    #[Hook(point: \App\Consts\Hook::USER_VIEW_INDEX_BODY)]
    public function indexRender(): void
    {
        $config = Plugin::getConfig("Live2d");
        echo $this->render("live2d", "Live2d.html", ["cfg" => $config]);
    }
}