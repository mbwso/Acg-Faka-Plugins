<?php
declare(strict_types=1);

namespace App\Plugin\Lumepanel\Hook;

use App\Consts\Hook;
use App\Util\Plugin;
use Kernel\Annotation\Hook as HookPoint;

class Index
{
    #[HookPoint(point: Hook::USER_VIEW_INDEX_FOOTER)]
    public function injectBoothCard(): string
    {
        $config = Plugin::getConfig("Lumepanel");
        $title = strip_tags((string)($config["booth_title"] ?? "海外社媒粉丝运营（Lumepanel）"));

        $titleJson = json_encode($title, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $script = <<<'HTML'
<script>
(() => {
    const title = __TITLE_JSON__;
    const href = "/plugin/lumepanel/index/index";
    const cover = "/favicon.ico";

    const ensureBooth = () => {
        const itemList = document.querySelector(".item-list");
        if (!itemList) {
            return;
        }

        if (itemList.querySelector(".lumepanel-booth")) {
            return;
        }

        const html = `
<a href="${href}" class="col-12 col-md-6 col-lg-3 mb-3 lumepanel-booth" data-id="lumepanel-booth">
  <div class="acg-card h-100">
    <div class="acg-thumb" style="background: url('${cover}') center/cover no-repeat;"></div>
    <div class="p-3">
      <div class="tags">
        <span class="badge-soft badge-soft-primary">Lumepanel</span>
        <span class="badge-soft badge-soft-success">海外社媒</span>
      </div>
      <p class="goods-title">${title}</p>
      <div class="stat-row mb-1">
        <div class="price"><span class="unit"></span>点击进入</div>
      </div>
      <div class="stat-bottom"><span>实时接口商品</span><span>展位商品</span></div>
    </div>
  </div>
</a>`;
        itemList.insertAdjacentHTML("afterbegin", html);
    };

    ensureBooth();

    const observer = new MutationObserver(() => {
        ensureBooth();
    });

    observer.observe(document.documentElement, {
        childList: true,
        subtree: true
    });
})();
</script>
HTML;
        return str_replace("__TITLE_JSON__", (string)$titleJson, $script);
    }
}
