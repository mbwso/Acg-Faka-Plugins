<?php

declare(strict_types=1);

namespace App\Plugin\MouseEffects\Hook;

use App\Controller\Base\View\UserPlugin;
use Kernel\Annotation\Hook;

class Main extends UserPlugin
{
    #[Hook(point: \App\Consts\Hook::USER_VIEW_INDEX_BODY)]
    public function body()
    {
        $text = getPluginConfig('MouseEffects')['text'];
        if (empty($text)) {
            $text = ["富强", "民主", "文明", "和谐", "自由", "平等", "公正", "法治", "爱国", "敬业", "诚信", "友善"];
        } else {
            $text = explode(',', $text);
        }
        $text = json_encode($text);
        echo $this->render('', "Effect.hook", [
            "text" => $text
        ]);
    }
}