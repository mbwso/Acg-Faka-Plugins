<?php
declare(strict_types=1);

namespace App\Plugin\Lumepanel\Service;

use App\Util\Plugin;

class LumepanelService
{
    public const PLUGIN = "Lumepanel";
    private const CACHE_DB = "cache";
    private const CACHE_KEY = "services";
    private const CACHE_META_KEY = "services_meta";
    private const ORDER_DB = "orders";
    private const ORDER_STATUS_CACHE_KEY = "order_status";
    private const ORDER_STATUS_META_KEY = "order_status_meta";

    public static function getConfig(): array
    {
        return Plugin::getConfig(self::PLUGIN);
    }

    public static function getCachedServices(): array
    {
        $list = Plugin::getCache(self::PLUGIN, self::CACHE_DB, self::CACHE_KEY);
        if (!is_array($list)) {
            return [];
        }
        return $list;
    }

    public static function getCachedMeta(): array
    {
        $meta = Plugin::getCache(self::PLUGIN, self::CACHE_DB, self::CACHE_META_KEY);
        if (!is_array($meta)) {
            return [];
        }
        return $meta;
    }

    public static function refreshCache(array $config): array
    {
        $result = self::fetchServices($config);
        if ($result["error"] !== "") {
            return $result;
        }

        $count = count($result["services"]);
        $meta = [
            "updated_at" => date("Y-m-d H:i:s"),
            "count" => $count
        ];

        Plugin::setCache(self::PLUGIN, self::CACHE_DB, self::CACHE_KEY, $result["services"]);
        Plugin::setCache(self::PLUGIN, self::CACHE_DB, self::CACHE_META_KEY, $meta);

        return [
            "services" => $result["services"],
            "meta" => $meta,
            "error" => ""
        ];
    }

    public static function ensureWiki(array $config): void
    {
        $wikiDir = BASE_PATH . "/app/Plugin/Lumepanel/Wiki";
        if (!is_dir($wikiDir)) {
            mkdir($wikiDir, 0777, true);
        }

        $token = trim((string)($config["cache_refresh_token"] ?? ""));
        $refreshUrl = "/plugin/lumepanel/api/refreshCache?token=" . urlencode($token);

        $statusRefreshUrl = "/plugin/lumepanel/api/refreshOrderStatus?token=" . urlencode($token);

        $readme = <<<MD
# Lumepanel 插件文档

## 插件说明

本插件支持所有使用 Lumepanel 系统的货源进行一键对接，主要用于将上游商品能力快速接入本站，实现商品列表展示、下单以及订单状态同步。

## 插件使用说明

插件采用缓存优先模式，管理员需要定期刷新缓存，保证前台数据最新。

### 1) 刷新商品列表缓存

请在浏览器中访问以下链接：

`{$refreshUrl}`

返回 `code = 200` 表示刷新成功。

### 2) 刷新订单状态缓存

请在浏览器中访问以下链接：

`{$statusRefreshUrl}`

返回 `code = 200` 表示刷新成功。

### 3) 配置宝塔定时任务（推荐）

请在宝塔面板中新增定时任务，使用“访问 URL”方式定时调用上述两个链接，建议每 5-15 分钟执行一次。

建议至少配置两个任务：

- 任务 A：定时访问“刷新商品列表缓存”链接。
- 任务 B：定时访问“刷新订单状态缓存”链接。

## 联系插件作者

- 微信：`alangwei345`
- Telegram：`etsowcom`
MD;

        $sidebar = <<<MD
* [插件说明](README.md#插件说明)
* [插件使用说明](README.md#插件使用说明)
* [联系插件作者](README.md#联系插件作者)
MD;

        $readmePath = $wikiDir . "/README.md";
        $sidebarPath = $wikiDir . "/Sidebar.md";

        if (!is_file($readmePath) || file_get_contents($readmePath) !== $readme) {
            file_put_contents($readmePath, $readme);
        }

        if (!is_file($sidebarPath) || file_get_contents($sidebarPath) !== $sidebar) {
            file_put_contents($sidebarPath, $sidebar);
        }
    }

    public static function addOrder(array $config, array $payload): array
    {
        $apiKey = trim((string)($config["api_key"] ?? ""));
        $apiUrl = self::normalizeApiUrl((string)($config["api_url"] ?? ""));

        if ($apiKey === "") {
            return ["error" => "请先在插件设置中填写 Lumepanel APIKEY。"];
        }

        if ($apiUrl === "") {
            return ["error" => "请先在插件设置中填写 API 地址。"];
        }

        $request = array_merge([
            "key" => $apiKey,
            "action" => "add"
        ], $payload);

        $res = self::request($apiUrl, $request);
        if (isset($res["error"])) {
            return ["error" => "Lumepanel 返回错误：" . (string)$res["error"]];
        }

        if (!isset($res["order"]) || $res["order"] === "") {
            return ["error" => "Lumepanel 下单失败，未返回订单号。"];
        }

        return ["order" => (string)$res["order"]];
    }

    public static function saveOrderRecord(array $record): void
    {
        $key = (string)($record["order_no"] ?? "");
        if ($key === "") {
            $key = "order_" . date("YmdHis") . "_" . mt_rand(1000, 9999);
        }
        Plugin::setCache(self::PLUGIN, self::ORDER_DB, $key, $record);
    }

    public static function getUserOrderRecords(int $userId): array
    {
        $list = Plugin::getCaches(self::PLUGIN, self::ORDER_DB);
        $statusCache = self::getOrderStatusCache();
        $statusCache = self::normalizeStatusCacheKeys($statusCache);
        error_log("[Lumepanel] getUserOrderRecords - order count: " . count($list) . ", status cache count: " . count($statusCache));
        
        $result = [];
        foreach ($list as $item) {
            if (!is_array($item)) {
                continue;
            }
            if ((int)($item["user_id"] ?? 0) !== $userId) {
                continue;
            }
            $orderNo = self::sanitizeOrderNo((string)($item["order_no"] ?? ""));
            error_log("[Lumepanel] Processing order: " . $orderNo . ", status in cache: " . (isset($statusCache[$orderNo]) ? "true" : "false"));
            
            if ($orderNo !== "" && isset($statusCache[$orderNo])) {
                $item["charge"] = $statusCache[$orderNo]["charge"] ?? "";
                $item["start_count"] = $statusCache[$orderNo]["start_count"] ?? "";
                $item["status"] = $statusCache[$orderNo]["status"] ?? $item["status"];
                $item["remains"] = $statusCache[$orderNo]["remains"] ?? "";
                $item["currency"] = $statusCache[$orderNo]["currency"] ?? "";
                error_log("[Lumepanel] Order " . $orderNo . " status updated to: " . $item["status"]);
            }
            $result[] = $item;
        }
        usort($result, static function (array $a, array $b): int {
            return strcmp((string)($b["create_time"] ?? ""), (string)($a["create_time"] ?? ""));
        });
        return $result;
    }

    public static function getOrderStatusCache(): array
    {
        $cache = Plugin::getCache(self::PLUGIN, self::CACHE_DB, self::ORDER_STATUS_CACHE_KEY);
        if (!is_array($cache)) {
            error_log("[Lumepanel] Order status cache is not an array, returning empty");
            return [];
        }
        error_log("[Lumepanel] Order status cache loaded, count: " . count($cache));
        return $cache;
    }

    public static function getOrderStatusMeta(): array
    {
        $meta = Plugin::getCache(self::PLUGIN, self::CACHE_DB, self::ORDER_STATUS_META_KEY);
        if (!is_array($meta)) {
            return [];
        }
        return $meta;
    }

    public static function refreshOrderStatusCache(array $config): array
    {
        $apiKey = trim((string)($config["api_key"] ?? ""));
        $apiUrl = self::normalizeApiUrl((string)($config["api_url"] ?? ""));

        if ($apiKey === "") {
            return ["error" => "请先在插件设置中填写 Lumepanel APIKEY。"];
        }

        if ($apiUrl === "") {
            return ["error" => "请先在插件设置中填写 API 地址。"];
        }

        $orderNos = self::getAllOrderNos();
        if (empty($orderNos)) {
            $meta = [
                "updated_at" => date("Y-m-d H:i:s"),
                "count" => 0
            ];
            Plugin::setCache(self::PLUGIN, self::CACHE_DB, self::ORDER_STATUS_CACHE_KEY, []);
            Plugin::setCache(self::PLUGIN, self::CACHE_DB, self::ORDER_STATUS_META_KEY, $meta);
            return [
                "status" => [],
                "meta" => $meta,
                "error" => ""
            ];
        }

        $chunkSize = 50;
        $allStatus = [];
        foreach (array_chunk($orderNos, $chunkSize) as $chunk) {
            $ordersStr = implode(",", $chunk);
            $res = self::request($apiUrl, [
                "key" => $apiKey,
                "action" => "status",
                "orders" => $ordersStr
            ]);
            error_log("[Lumepanel] API response for orders: " . $ordersStr . ", is_array: " . (is_array($res) ? "true" : "false") . ", response: " . json_encode($res));
            if (is_array($res)) {
                $normalized = self::normalizeOrderStatusResponse($res, $chunk);
                $allStatus = array_merge($allStatus, $normalized);
            }
        }
        $allStatus = self::normalizeStatusCacheKeys($allStatus);
        error_log("[Lumepanel] All status merged, count: " . count($allStatus) . ", keys: " . implode(",", array_keys($allStatus)));

        $count = count($allStatus);
        $meta = [
            "updated_at" => date("Y-m-d H:i:s"),
            "count" => $count
        ];

        Plugin::setCache(self::PLUGIN, self::CACHE_DB, self::ORDER_STATUS_CACHE_KEY, $allStatus);
        Plugin::setCache(self::PLUGIN, self::CACHE_DB, self::ORDER_STATUS_META_KEY, $meta);
        self::syncOrderRecordsStatus($allStatus);
        
        error_log("[Lumepanel] Order status cache refreshed, count: " . count($allStatus));
        if (!empty($allStatus)) {
            $firstKey = array_keys($allStatus)[0];
            error_log("[Lumepanel] First order in cache: " . $firstKey . ", data: " . json_encode($allStatus[$firstKey]));
        }

        return [
            "status" => $allStatus,
            "meta" => $meta,
            "error" => ""
        ];
    }

    private static function normalizeOrderStatusResponse(array $response, array $requestedOrderNos): array
    {
        // Some providers return {"status":"...","charge":"..."} for single order requests.
        if (
            isset($response["status"])
            && !isset($response["error"])
            && !isset($response["order"])
            && count($requestedOrderNos) === 1
        ) {
            return [
                (string)$requestedOrderNos[0] => $response
            ];
        }
        return $response;
    }

    private static function normalizeStatusCacheKeys(array $statusCache): array
    {
        $normalized = [];
        foreach ($statusCache as $key => $value) {
            if (!is_array($value)) {
                continue;
            }
            $orderNo = self::sanitizeOrderNo((string)$key);
            if ($orderNo === "" && isset($value["order"])) {
                $orderNo = self::sanitizeOrderNo((string)$value["order"]);
            }
            if ($orderNo === "") {
                continue;
            }
            $normalized[$orderNo] = $value;
        }
        return $normalized;
    }

    private static function sanitizeOrderNo(string $orderNo): string
    {
        return trim($orderNo);
    }

    private static function syncOrderRecordsStatus(array $statusCache): void
    {
        if ($statusCache === []) {
            return;
        }

        $orders = Plugin::getCaches(self::PLUGIN, self::ORDER_DB);
        foreach ($orders as $key => $order) {
            if (!is_array($order)) {
                continue;
            }

            $orderNo = self::sanitizeOrderNo((string)($order["order_no"] ?? ""));
            if ($orderNo === "" || !isset($statusCache[$orderNo]) || !is_array($statusCache[$orderNo])) {
                continue;
            }

            $status = $statusCache[$orderNo];
            $order["charge"] = $status["charge"] ?? ($order["charge"] ?? "");
            $order["start_count"] = $status["start_count"] ?? ($order["start_count"] ?? "");
            $order["status"] = $status["status"] ?? ($order["status"] ?? "");
            $order["remains"] = $status["remains"] ?? ($order["remains"] ?? "");
            $order["currency"] = $status["currency"] ?? ($order["currency"] ?? "");

            Plugin::setCache(self::PLUGIN, self::ORDER_DB, (string)$key, $order);
        }
    }

    private static function getAllOrderNos(): array
    {
        $list = Plugin::getCaches(self::PLUGIN, self::ORDER_DB);
        $orderNos = [];
        foreach ($list as $item) {
            if (!is_array($item)) {
                continue;
            }
            $orderNo = self::sanitizeOrderNo((string)($item["order_no"] ?? ""));
            if ($orderNo !== "") {
                $orderNos[] = $orderNo;
            }
        }
        return $orderNos;
    }

    public static function createRefill(array $config, string $orderNo): array
    {
        $apiKey = trim((string)($config["api_key"] ?? ""));
        $apiUrl = self::normalizeApiUrl((string)($config["api_url"] ?? ""));

        if ($apiKey === "") {
            return ["error" => "请先在插件设置中填写 Lumepanel APIKEY。"];
        }

        if ($apiUrl === "") {
            return ["error" => "请先在插件设置中填写 API 地址。"];
        }

        $res = self::request($apiUrl, [
            "key" => $apiKey,
            "action" => "refill",
            "order" => $orderNo
        ]);

        if (!isset($res["refill"]) || $res["refill"] !== 1) {
            $errorMsg = isset($res["error"]) ? (string)$res["error"] : "补单请求失败";
            return ["error" => "Lumepanel 返回错误：" . $errorMsg];
        }

        return ["refill" => 1];
    }

    public static function createCancel(array $config, string $orderNo): array
    {
        $apiKey = trim((string)($config["api_key"] ?? ""));
        $apiUrl = self::normalizeApiUrl((string)($config["api_url"] ?? ""));

        if ($apiKey === "") {
            return ["error" => "请先在插件设置中填写 Lumepanel APIKEY。"];
        }

        if ($apiUrl === "") {
            return ["error" => "请先在插件设置中填写 API 地址。"];
        }

        $res = self::request($apiUrl, [
            "key" => $apiKey,
            "action" => "cancel",
            "orders" => $orderNo
        ]);

        if (!is_array($res) || empty($res)) {
            return ["error" => "取消请求失败"];
        }

        foreach ($res as $item) {
            if (is_array($item) && isset($item["order"]) && $item["order"] === $orderNo) {
                if (isset($item["cancel"]["error"])) {
                    return ["error" => "Lumepanel 返回错误：" . (string)$item["cancel"]["error"]];
                }
                if (isset($item["cancel"]) && $item["cancel"] === 1) {
                    return ["cancel" => 1];
                }
            }
        }

        return ["error" => "取消请求未返回预期结果"];
    }

    private static function fetchServices(array $config): array
    {
        $apiKey = trim((string)($config["api_key"] ?? ""));
        $apiUrl = self::normalizeApiUrl((string)($config["api_url"] ?? ""));

        if ($apiKey === "") {
            return ["services" => [], "error" => "请先在插件设置中填写 Lumepanel APIKEY。"];
        }

        if ($apiUrl === "") {
            return ["services" => [], "error" => "请先在插件设置中填写 API 地址。"];
        }

        $decoded = self::request($apiUrl, [
            "key" => $apiKey,
            "action" => "services"
        ]);

        if ($decoded === []) {
            return ["services" => [], "error" => "Lumepanel 接口请求失败，请检查 API 地址、网络或 APIKEY。"];
        }

        if (isset($decoded["error"])) {
            return ["services" => [], "error" => "Lumepanel 返回错误：" . (string)$decoded["error"]];
        }

        if (!self::isListArray($decoded)) {
            return ["services" => [], "error" => "Lumepanel 服务列表为空或无权限访问。"];
        }

        return ["services" => $decoded, "error" => ""];
    }

    private static function request(string $apiUrl, array $payload): array
    {
        $body = http_build_query($payload);
        $context = stream_context_create([
            "http" => [
                "method" => "POST",
                "header" => "Content-Type: application/x-www-form-urlencoded\r\n",
                "content" => $body,
                "timeout" => 20
            ]
        ]);

        $raw = @file_get_contents($apiUrl, false, $context);
        if ($raw === false) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function normalizeApiUrl(string $apiUrl): string
    {
        $apiUrl = trim($apiUrl);
        if ($apiUrl === "") {
            return "";
        }

        $apiUrl = rtrim($apiUrl, "/");
        if (str_ends_with($apiUrl, "/api/v3")) {
            return $apiUrl;
        }
        if (str_ends_with($apiUrl, "/api")) {
            return $apiUrl . "/v3";
        }
        if (str_ends_with($apiUrl, "/api.html")) {
            return substr($apiUrl, 0, -9) . "/api/v3";
        }

        return $apiUrl . "/api/v3";
    }

    private static function isListArray(array $data): bool
    {
        if ($data === []) {
            return true;
        }
        return array_keys($data) === range(0, count($data) - 1);
    }
}
