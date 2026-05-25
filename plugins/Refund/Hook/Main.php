<?php
declare (strict_types=1);

namespace App\Plugin\Refund\Hook;

use App\Controller\Base\View\ManagePlugin;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;
use Kernel\Annotation\Hook;
use Kernel\Exception\ViewException;

class Main extends ManagePlugin
{


    #[\Kernel\Annotation\Plugin(state: \Kernel\Annotation\Plugin::START)]
    public function init(): void
    {
        $cardShowTips = Manager::schema()->hasColumn("order", "refund_status");
        if (!$cardShowTips) {
            Manager::schema()->table("order", function (Blueprint $blueprint) {
                $blueprint->tinyInteger("refund_status", false, true)->nullable(false)->default(0);
            });
        }
    }

    /**
     * @throws ViewException
     */
    #[Hook(point: \App\Consts\Hook::ADMIN_VIEW_ORDER_TABLE)]
    public function aide()
    {
        echo $this->render(null, "Aide.hook");
    }
}