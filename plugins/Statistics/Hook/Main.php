<?php

declare(strict_types=1);

namespace App\Plugin\Statistics\Hook;

use App\Controller\Base\View\UserPlugin;
use Kernel\Annotation\Hook;
use Kernel\Annotation\Plugin;
use Kernel\Exception\JSONException;

class Main extends UserPlugin
{
    #[Plugin(state: Plugin::START)]
    public function start()
    {
        $config = getPluginConfig('Statistics');
        if (empty($config['html'])) {
            throw new JSONException("宝,启动前先检查配置了没哦！");
        }
    }

    #[Hook(point: \App\Consts\Hook::USER_VIEW_INDEX_FOOTER)]
    public function footer()
    {
        $config = getPluginConfig('Statistics');
        if (!empty($config['html'])) {
            echo $config['html'];
        }
    }
}