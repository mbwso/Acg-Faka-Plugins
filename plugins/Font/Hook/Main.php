<?php
declare (strict_types=1);

namespace App\Plugin\Font\Hook;

use App\Controller\Base\View\UserPlugin;
use App\Util\Opcache;
use App\Util\Plugin;
use Kernel\Annotation\Hook;
use Kernel\Exception\ViewException as ViewExceptionAlias;

class Main extends UserPlugin
{

    /**
     * @return void
     */
    private function style(): void
    {
        $cfg = Plugin::getConfig("Font");
        echo "<style>
    @font-face {
        font-family: 'custom_font';
        font-style: normal;
        font-display: swap;
        src: url('{$cfg['font']}')
    }
    body, div, html, a, span, b, button, input, h1, h2, h3, h4, h5, h6 {
        font-family: 'custom_font' !important;
    }
</style>";
    }


    #[Hook(point: \App\Consts\Hook::USER_VIEW_INDEX_HEADER)]
    public function index(): void
    {
        $this->style();
    }


    #[Hook(point: \App\Consts\Hook::ADMIN_VIEW_HEADER)]
    public function admin(): void
    {
        $this->style();
    }

    #[Hook(point: \App\Consts\Hook::USER_VIEW_HEADER)]
    public function user(): void
    {
        $this->style();
    }
}