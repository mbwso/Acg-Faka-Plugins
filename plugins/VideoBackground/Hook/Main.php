<?php
declare(strict_types=1);

namespace App\Plugin\VideoBackground\Hook;

use App\Controller\Base\View\UserPlugin;
use App\Util\Plugin;
use Kernel\Annotation\Hook;

class Main extends UserPlugin
{

    /**
     * @throws \Kernel\Exception\ViewException
     */
    #[Hook(point: \App\Consts\Hook::USER_VIEW_INDEX_BODY)]
    public function body(): void
    {
        echo $this->render("body", "Body.html", ["cfg" => Plugin::getConfig("VideoBackground")]);
    }
}