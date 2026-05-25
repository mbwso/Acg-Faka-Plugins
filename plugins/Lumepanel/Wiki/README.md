# Lumepanel 插件文档

## 插件说明

本插件支持所有使用 Lumepanel 系统的货源进行一键对接，主要用于将上游商品能力快速接入本站，实现商品列表展示、下单以及订单状态同步。

## 插件使用说明

插件采用缓存优先模式，管理员需要定期刷新缓存，保证前台数据最新。

### 1) 刷新商品列表缓存

请在浏览器中访问以下链接：

`/plugin/lumepanel/api/refreshCache?token=change_me_lumepanel_refresh_token`

返回 `code = 200` 表示刷新成功。

### 2) 刷新订单状态缓存

请在浏览器中访问以下链接：

`/plugin/lumepanel/api/refreshOrderStatus?token=change_me_lumepanel_refresh_token`

返回 `code = 200` 表示刷新成功。

### 3) 配置宝塔定时任务（推荐）

请在宝塔面板中新增定时任务，使用“访问 URL”方式定时调用上述两个链接，建议每 5-15 分钟执行一次。

建议至少配置两个任务：

- 任务 A：定时访问“刷新商品列表缓存”链接。
- 任务 B：定时访问“刷新订单状态缓存”链接。

## 联系插件作者

- 微信：`alangwei345`
- Telegram：`etsowcom`