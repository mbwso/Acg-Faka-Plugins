<?php
declare(strict_types=1);

namespace App\Plugin\Lumepanel\Hook;

use App\Plugin\Lumepanel\Service\LumepanelService;

class Config
{
    #[\Kernel\Annotation\Plugin(state: \Kernel\Annotation\Plugin::SAVE_CONFIG)]
    public function onSaveConfig(string $pluginName): void
    {
        if ($pluginName !== "Lumepanel") {
            return;
        }
        LumepanelService::ensureWiki(LumepanelService::getConfig());
    }
}
