<?php
declare(strict_types=1);

namespace App\Plugin\Lumepanel\Controller;

use App\Controller\Base\View\UserPlugin;
use App\Interceptor\UserVisitor;
use App\Interceptor\Waf;
use App\Plugin\Lumepanel\Service\LumepanelService;
use Kernel\Annotation\Interceptor;
use Kernel\Exception\JSONException;

#[Interceptor([Waf::class, UserVisitor::class])]
class Index extends UserPlugin
{
    public function index(): string
    {
        // Index/Header.html 依赖 cid，插件页默认给一个稳定值，避免上下文缺失。
        if (!isset($_GET["cid"])) {
            $_GET["cid"] = 0;
        }

        $config = LumepanelService::getConfig();
        LumepanelService::ensureWiki($config);
        $keywords = trim((string)($_GET["keywords"] ?? ""));
        $premiumRate = $this->getPremiumRate($config);

        $cacheError = "";
        try {
            $cachedServices = LumepanelService::getCachedServices();
            $cachedMeta = LumepanelService::getCachedMeta();
        } catch (JSONException $e) {
            $cachedServices = [];
            $cachedMeta = [];
            $cacheError = "缓存读取失败：" . $e->getMessage();
        }
        if ($cacheError === "" && $cachedServices === []) {
            $cacheError = "当前暂无缓存数据，请管理员先使用 Wiki 中的刷新链接更新缓存。";
        }

        $page = max(1, (int)($_GET["page"] ?? 1));
        $limit = (int)($_GET["limit"] ?? 20);
        $allowedLimits = [10, 20, 50, 100];
        if (!in_array($limit, $allowedLimits, true)) {
            $limit = 20;
        }

        $services = $this->sortServices($cachedServices);
        $services = $this->searchServices($services, $keywords);
        $total = count($services);
        $totalPages = max(1, (int)ceil($total / $limit));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $limit;
        $services = array_slice($services, $offset, $limit);
        $services = $this->formatServicesForView($services, $premiumRate);

        return $this->render(($config["booth_title"] ?? "海外社媒粉丝运营"), "Index.html", [
            "apiConfigured" => !empty($config["api_key"]),
            "services" => $services,
            "error" => $cacheError,
            "keywords" => $keywords,
            "page" => $page,
            "limit" => $limit,
            "total" => $total,
            "totalPages" => $totalPages,
            "cachedAt" => (string)($cachedMeta["updated_at"] ?? "")
        ], true);
    }

    public function orders(): string
    {
        $config = LumepanelService::getConfig();
        $keywords = trim((string)($_GET["keywords"] ?? ""));
        $user = $this->getUser();
        if (!$user) {
            return $this->render("订单管理", "Orders.html", [
                "needLogin" => true,
                "orders" => [],
                "cachedAt" => "",
                "keywords" => $keywords
            ], true);
        }

        $orders = [];
        try {
            $orders = LumepanelService::getUserOrderRecords((int)$user->id);
        } catch (\Throwable $e) {
            $orders = [];
        }
        $orders = $this->searchOrders($orders, $keywords);

        $statusMeta = LumepanelService::getOrderStatusMeta();

        return $this->render("订单管理", "Orders.html", [
            "needLogin" => false,
            "orders" => $orders,
            "cachedAt" => (string)($statusMeta["updated_at"] ?? ""),
            "keywords" => $keywords
        ], true);
    }

    private function searchOrders(array $orders, string $keywords): array
    {
        if ($keywords === "") {
            return $orders;
        }

        $needle = mb_strtolower($keywords, "UTF-8");
        $result = [];
        foreach ($orders as $order) {
            if (!is_array($order)) {
                continue;
            }
            $haystacks = [
                (string)($order["order_no"] ?? ""),
                (string)($order["service_id"] ?? ""),
                (string)($order["service_name"] ?? ""),
                (string)($order["link"] ?? ""),
                (string)($order["status"] ?? "")
            ];

            foreach ($haystacks as $text) {
                if ($text !== "" && str_contains(mb_strtolower($text, "UTF-8"), $needle)) {
                    $result[] = $order;
                    break;
                }
            }
        }
        return $result;
    }

    private function searchServices(array $services, string $keywords): array
    {
        if ($keywords === "") {
            return $services;
        }

        $needle = mb_strtolower($keywords, "UTF-8");
        $isIdSearch = ctype_digit($keywords);
        $exactMatches = [];
        $others = [];

        foreach ($services as $service) {
            $serviceId = (string)($service["service"] ?? "");
            $serviceName = (string)($service["name"] ?? "");

            $idHit = str_contains($serviceId, $keywords);
            $nameHit = str_contains(mb_strtolower($serviceName, "UTF-8"), $needle);
            if (!$idHit && !$nameHit) {
                continue;
            }

            if ($isIdSearch && $serviceId === $keywords) {
                $exactMatches[] = $service;
            } else {
                $others[] = $service;
            }
        }

        return array_merge($exactMatches, $others);
    }

    private function sortServices(array $services): array
    {
        usort($services, static function (array $a, array $b): int {
            $aInc = (float)($a["interaction_increment_per_hour"] ?? 0);
            $bInc = (float)($b["interaction_increment_per_hour"] ?? 0);
            return $bInc <=> $aInc;
        });
        return $services;
    }

    private function formatServicesForView(array $services, float $premiumRate): array
    {
        foreach ($services as &$service) {
            $service["interaction_increment_per_hour_display"] = $this->formatInteger($service["interaction_increment_per_hour"] ?? null);
            $service["start_speed_minutes_display"] = $this->formatNumber($service["start_speed_minutes"] ?? null);
            $service["completion_speed_minutes_display"] = $this->formatNumber($service["completion_speed_minutes"] ?? null);
            $rawRate = (float)($service["rate"] ?? 0);
            $adjustedRate = $rawRate * (1 + $premiumRate / 100);
            $service["rate_display"] = number_format($adjustedRate, 4, ".", "");
            $service["premium_rate"] = $premiumRate;
        }
        unset($service);
        return $services;
    }

    private function getPremiumRate(array $config): float
    {
        $rate = (float)($config["premium_rate"] ?? 0);
        if ($rate < 0) {
            $rate = 0;
        }
        return $rate;
    }

    private function formatNumber(mixed $value): string
    {
        if ($value === null || $value === "") {
            return "-";
        }

        $raw = trim((string)$value);
        if (!is_numeric($raw)) {
            return (string)$value;
        }

        $num = (float)$raw;
        $roundedInt = (int)round($num);
        if (abs($num - $roundedInt) < 0.000001) {
            return (string)$roundedInt;
        }
        return $raw;
    }

    private function formatInteger(mixed $value): string
    {
        if ($value === null || $value === "") {
            return "-";
        }
        if (!is_numeric((string)$value)) {
            return (string)$value;
        }
        return (string)((int)round((float)$value));
    }

}
