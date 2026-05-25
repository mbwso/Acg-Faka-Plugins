<?php

declare(strict_types=1);

namespace App\Plugin\GoTop\Hook;

use App\Controller\Base\View\UserPlugin;
use Kernel\Annotation\Hook;

class Main extends UserPlugin
{

    #[Hook(point: \App\Consts\Hook::USER_VIEW_INDEX_HEADER)]
    public function header()
    {
        echo '<link rel="stylesheet" type="text/css" href="' . Plugin('GoTop', 'View/Css/main.css') . '" />';
    }

    #[Hook(point: \App\Consts\Hook::USER_VIEW_INDEX_BODY)]
    public function body()
    {
        $rightWidth = getPluginConfig('GoTop')['rightWidth'];
        if ($rightWidth) {
            echo '<div class="back-to-top faa-float animated cd-is-visible" style="top: -900px;right:' . $rightWidth . 'px;"></div>';
        } else {
            echo '<div class="back-to-top faa-float animated cd-is-visible" style="top: -900px;right:100px;"></div>';
        }
    }

    #[Hook(point: \App\Consts\Hook::USER_VIEW_INDEX_FOOTER)]
    public function footer()
    {
        echo '<script type="text/javascript" src="' . Plugin('GoTop', 'View/Js/main.js') . '"></script>';
    }
}