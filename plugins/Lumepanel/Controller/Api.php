<?php
declare(strict_types=1);

namespace App\Plugin\Lumepanel\Controller;

use App\Controller\Base\API\UserPlugin;
use App\Interceptor\UserVisitor;
use App\Interceptor\Waf;
use App\Model\Bill;
use App\Model\User;
use App\Plugin\Lumepanel\Service\LumepanelService;
use Illuminate\Database\Capsule\Manager as DB;
use Kernel\Annotation\Interceptor;
use Kernel\Exception\JSONException;

#[Interceptor([Waf::class, UserVisitor::class])]
class Api extends UserPlugin
{
    public function refreshCache(): array
    {
        $config = LumepanelService::getConfig();
        $token = trim((string)($_GET["token"] ?? ""));
        $allowToken = trim((string)($config["cache_refresh_token"] ?? ""));

        if ($allowToken === "") {
            return $this->json(403, "缓存刷新口令未配置，请先在插件配置中设置。");
        }

        if ($token === "" || !hash_equals($allowToken, $token)) {
            return $this->json(403, "无效的缓存刷新口令。");
        }

        try {
            $result = LumepanelService::refreshCache($config);
        } catch (JSONException $e) {
            return $this->json(500, $e->getMessage());
        }
        if ($result["error"] !== "") {
            return $this->json(500, $result["error"]);
        }

        LumepanelService::ensureWiki($config);
        return $this->json(200, "缓存刷新成功", [
            "updated_at" => $result["meta"]["updated_at"] ?? "",
            "count" => $result["meta"]["count"] ?? 0
        ]);
    }

    public function refreshOrderStatus(): array
    {
        $config = LumepanelService::getConfig();
        $token = trim((string)($_GET["token"] ?? ""));
        $allowToken = trim((string)($config["cache_refresh_token"] ?? ""));

        if ($allowToken === "") {
            return $this->json(403, "缓存刷新口令未配置，请先在插件配置中设置。");
        }

        if ($token === "" || !hash_equals($allowToken, $token)) {
            return $this->json(403, "无效的缓存刷新口令。");
        }

        try {
            $result = LumepanelService::refreshOrderStatusCache($config);
        } catch (JSONException $e) {
            return $this->json(500, $e->getMessage());
        }
        if ($result["error"] !== "") {
            return $this->json(500, $result["error"]);
        }

        LumepanelService::ensureWiki($config);
        
        $debugInfo = [];
        if (!empty($result["status"])) {
            $firstOrder = array_keys($result["status"])[0];
            $debugInfo["first_order"] = $firstOrder;
            $debugInfo["first_order_data"] = $result["status"][$firstOrder];
        }
        
        return $this->json(200, "订单状态缓存刷新成功", [
            "updated_at" => $result["meta"]["updated_at"] ?? "",
            "count" => $result["meta"]["count"] ?? 0,
            "debug" => $debugInfo
        ]);
    }

    public function testCache(): array
    {
        $config = LumepanelService::getConfig();
        $token = trim((string)($_GET["token"] ?? ""));
        $allowToken = trim((string)($config["cache_refresh_token"] ?? ""));

        if ($token !== $allowToken) {
            return $this->json(403, "无效的口令");
        }

        $userId = (int)($_GET["user_id"] ?? 0);
        if ($userId <= 0) {
            return $this->json(422, "请提供 user_id 参数");
        }

        $statusCache = LumepanelService::getOrderStatusCache();
        $orderRecords = LumepanelService::getUserOrderRecords($userId);

        $debug = [];
        foreach ($orderRecords as $record) {
            $orderNo = (string)($record["order_no"] ?? "");
            $debug[] = [
                "order_no" => $orderNo,
                "order_no_length" => strlen($orderNo),
                "in_status_cache" => isset($statusCache[$orderNo]),
                "current_status" => $record["status"] ?? "N/A",
                "cached_status" => $statusCache[$orderNo]["status"] ?? "N/A"
            ];
        }

        return $this->json(200, "缓存测试", [
            "user_id" => $userId,
            "status_cache_keys" => array_keys($statusCache),
            "order_count" => count($orderRecords),
            "debug" => $debug
        ]);
    }

    public function orderAction(): array
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(401, "请先登录");
        }

        $config = LumepanelService::getConfig();
        $action = trim((string)$this->request->post("action"));
        $orderNo = trim((string)$this->request->post("order"));

        if (!in_array($action, ["refill", "cancel"], true)) {
            return $this->json(422, "无效的操作类型");
        }

        if ($orderNo === "") {
            return $this->json(422, "缺少订单号");
        }

        $orderRecords = LumepanelService::getUserOrderRecords((int)$user->id);
        $orderExists = false;
        foreach ($orderRecords as $record) {
            if ((string)($record["order_no"] ?? "") === $orderNo) {
                $orderExists = true;
                break;
            }
        }

        if (!$orderExists) {
            return $this->json(403, "您无权操作此订单");
        }

        if ($action === "refill") {
            $result = LumepanelService::createRefill($config, $orderNo);
            if (isset($result["error"])) {
                return $this->json(500, (string)$result["error"]);
            }
            return $this->json(200, "补单申请已提交，管理员将在后台处理");
        }

        if ($action === "cancel") {
            $result = LumepanelService::createCancel($config, $orderNo);
            if (isset($result["error"])) {
                return $this->json(500, (string)$result["error"]);
            }
            return $this->json(200, "取消申请已提交，管理员将在后台处理");
        }

        return $this->json(422, "未知操作");
    }

    public function addOrder(): array
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(401, "请先登录后再购买");
        }

        $config = LumepanelService::getConfig();
        $serviceId = trim((string)$this->request->post("service"));
        $link = trim((string)$this->request->post("link"));
        $quantity = (int)$this->request->post("quantity");
        $comments = trim((string)$this->request->post("comments"));
        $runs = (int)$this->request->post("runs");
        $interval = (int)$this->request->post("interval");
        $username = trim((string)$this->request->post("username"));

        if ($serviceId === "") {
            return $this->json(422, "缺少服务ID");
        }
        if ($link === "") {
            return $this->json(422, "请填写链接");
        }

        $services = LumepanelService::getCachedServices();
        $service = null;
        foreach ($services as $item) {
            if ((string)($item["service"] ?? "") === $serviceId) {
                $service = $item;
                break;
            }
        }
        if (!$service) {
            return $this->json(404, "服务不存在或缓存已过期，请刷新缓存后重试");
        }

        $type = strtolower((string)($service["type"] ?? ""));
        $isCommentType = str_contains($type, "comment");
        $isSubscriptionType = str_contains($type, "subscription");

        if ($isCommentType) {
            if ($comments === "") {
                return $this->json(422, "评论类型服务需要填写评论内容");
            }
            if ($quantity <= 0) {
                $quantity = count(array_filter(array_map("trim", preg_split("/\r\n|\r|\n/", $comments))));
            }
        }

        if ($quantity <= 0) {
            return $this->json(422, "请填写正确的数量");
        }
        $min = (int)($service["min"] ?? 1);
        $max = (int)($service["max"] ?? PHP_INT_MAX);
        if ($quantity < $min || $quantity > $max) {
            return $this->json(422, "下单数量不符合要求，必须在 {$min} ~ {$max} 之间");
        }

        $payload = [
            "service" => $serviceId,
            "link" => $link,
            "quantity" => $quantity
        ];

        if ($comments !== "") {
            $payload["comments"] = $comments;
        }
        if ($username !== "") {
            $payload["username"] = $username;
        }
        if ($isSubscriptionType) {
            if ($runs <= 0 || $interval <= 0) {
                return $this->json(422, "订阅类型服务需要 runs 和 interval");
            }
            $payload["runs"] = $runs;
            $payload["interval"] = $interval;
        } else {
            if ($runs > 0) {
                $payload["runs"] = $runs;
            }
            if ($interval > 0) {
                $payload["interval"] = $interval;
            }
        }

        $rawRate = (float)($service["rate"] ?? 0);
        $premiumRate = max(0, (float)($config["premium_rate"] ?? 0));
        $adjustedRate = $rawRate * (1 + $premiumRate / 100);
        $totalAmount = ($quantity / 1000) * $adjustedRate;
        if ($totalAmount <= 0) {
            return $this->json(422, "金额计算异常，请检查服务单价");
        }
        $payment = $this->reserveBalance((int)$user->id, $totalAmount);
        if ($payment["ok"] !== true) {
            return $this->json(422, (string)$payment["msg"]);
        }

        $result = LumepanelService::addOrder($config, $payload);
        if (isset($result["error"])) {
            $refund = $this->refundBalance((int)$user->id, $totalAmount, "Lumepanel下单失败退款");
            if ($refund["ok"] !== true) {
                return $this->json(500, "上游下单失败且退款异常，请立即联系管理员处理。错误：" . (string)$result["error"]);
            }
            return $this->json(500, (string)$result["error"]);
        }

        try {
            LumepanelService::saveOrderRecord([
                "order_no" => (string)($result["order"] ?? ""),
                "user_id" => (int)$user->id,
                "username" => (string)$user->username,
                "service_id" => (string)$serviceId,
                "service_name" => (string)($service["name"] ?? ""),
                "link" => $link,
                "quantity" => $quantity,
                "rate" => number_format($adjustedRate, 4, ".", ""),
                "amount" => number_format($totalAmount, 4, ".", ""),
                "status" => "CREATED",
                "create_time" => date("Y-m-d H:i:s")
            ]);
        } catch (\Throwable $e) {
            // 订单已经创建成功，记录失败不应影响主流程
        }

        return $this->json(200, "下单成功", [
            "order" => $result["order"] ?? "",
            "rate" => number_format($adjustedRate, 4, ".", ""),
            "amount" => number_format($totalAmount, 4, ".", "")
        ]);
    }

    private function reserveBalance(int $userId, float $amount): array
    {
        try {
            DB::transaction(function () use ($userId, $amount) {
                /** @var User|null $lockedUser */
                $lockedUser = User::query()->where("id", $userId)->lockForUpdate()->first();
                if (!$lockedUser) {
                    throw new JSONException("用户不存在");
                }
                if ((float)$lockedUser->balance < $amount) {
                    throw new JSONException("余额不足，当前余额：" . number_format((float)$lockedUser->balance, 4, ".", ""));
                }
                Bill::create($lockedUser, $amount, Bill::TYPE_SUB, "Lumepanel下单预扣款");
            });
            return ["ok" => true];
        } catch (JSONException $e) {
            return ["ok" => false, "msg" => $e->getMessage()];
        } catch (\Throwable $e) {
            return ["ok" => false, "msg" => "扣款失败，请稍后重试"];
        }
    }

    private function refundBalance(int $userId, float $amount, string $reason): array
    {
        try {
            DB::transaction(function () use ($userId, $amount, $reason) {
                /** @var User|null $lockedUser */
                $lockedUser = User::query()->where("id", $userId)->lockForUpdate()->first();
                if (!$lockedUser) {
                    throw new JSONException("用户不存在");
                }
                Bill::create($lockedUser, $amount, Bill::TYPE_ADD, $reason);
            });
            return ["ok" => true];
        } catch (\Throwable $e) {
            return ["ok" => false];
        }
    }
}
