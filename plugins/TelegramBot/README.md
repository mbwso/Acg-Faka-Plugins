# TelegramBot —— Acg-Faka 智能购物机器人

把整个商城 **1:1** 复刻到 Telegram，并附带 **Topic 模式双向客服** + **分销返利推送** + **Bot↔网页账号互通**。

> **🚀 装好填好就能用，零常驻进程**（Webhook 模式默认）。
> 仅需 **Bot Token + HTTPS 域名** 两个东西，30 秒上手。

---

## ✨ 功能一览

- 🛍 **全场景复刻商城**：分类 → 商品 → 多规格 SKU → 账号类商品自助选号 → 收货联系方式 → 下单
- 💳 **聚合支付直连卡网**：不重复造支付，直接调主程序 `OrderService::trade()` —— 你在后台启用了多少支付通道，Bot 里就有多少支付按钮（USDT、微信、支付宝……一视同仁）
- 🔗 **账号双向打通**：Bot 内 `绑定/注册`，回到网页端「一键登录」无密码进入；订单、余额、推广关系两端实时同步
- 💬 **双向客户服务**（参考 [MiHaKun/Telegram-interactive-bot](https://github.com/MiHaKun/Telegram-interactive-bot)）：
  - 客户私聊 Bot → 自动镜像到管理群对应 Topic
  - 客服在 Topic 里回复 → Bot 同步发给客户
  - 用 `copyMessage` 转发，客户看不到「转自某人」字样，体验自然
  - 群内 `/ban` `/unban` `/clear` `/info` 一键管理
- 🤝 **社群裂变**：复用主程序 `user.pid` 推广关系；订单付款后自动 `Bill::create($from, divide_amount, ADD)` 入账（主程序原生逻辑），Bot 端再发一条"返利到账"通知
- 🔔 **支付成功推送**：下单 → 付款 → 自动卡密发货推送到 Bot；客服群同步收到订单通知
- 🌐 **多语言/重载/解绑** 等一站式按钮

---

## 🚀 30 秒上手（Webhook 模式 · 零常驻进程）

1. 在 [@BotFather](https://t.me/BotFather) 用 `/newbot` 创建 Bot，复制 Token
2. 后台「插件管理」启用 TelegramBot，配置里填：
   - **Bot Token** = 上一步复制的
   - **Webhook 域名** = 你的站点 HTTPS 根，如 `https://shop.example.com`（留空则取主程序「网站设置 → 站点 URL」）
3. 保存配置 → 点击「启用」
4. 插件自动调 Telegram `setWebhook` 完成注册 → @你的Bot 发 `/start` 即可使用

> **就这样。** 不用启动任何进程，不用 supervisord，不用 cron。
> Telegram 主动把每条消息 POST 到 `https://你的站点/plugin/TelegramBot/webhook/recv`，PHP-FPM 接住即可。
>
> 启用后访问后台「📱 TG Bot」控制台可看到完整 Webhook 注册状态。
> 详细使用说明、双向客服群配置、常见错误诊断 → 后台 **📖 Wiki** 页：`/plugin/TelegramBot/admin/wiki`

---

## 🧱 依赖

主程序已自带：`guzzlehttp/guzzle ≥7.4`、`illuminate/database ≥7.30.6`、`firebase/php-jwt ≥6.11`、PHP ≥ 8.0。

插件本身 **不需要 composer install**，零额外依赖。

---

## 📦 安装

### 方式 A：通过应用商店一键安装（推荐）

后台 → 应用商店 → 搜索 "Telegram" → 安装 → 启用。

### 方式 B：手动安装

```bash
cd /path/to/Acg-Faka-Local/app/Plugin
git clone --depth 1 https://github.com/NoDoorAction/Acg-Faka-Plugins.git /tmp/repo
cp -r /tmp/repo/plugins/TelegramBot ./TelegramBot
# 然后在主程序后台 → 插件管理 → 启用
```

启用时插件会执行 `install.sql`，自动建 5 张数据表（`plugin_telegrambot_user` / `_msgmap` / `_sso` / `_order` / `_state`）。

---

## ⚙️ 配置项

后台 → 插件管理 → TelegramBot → 配置：

| 字段 | 必填 | 说明 |
| --- | --- | --- |
| **Bot Token** | ✅ | 在 [@BotFather](https://t.me/BotFather) 创建 Bot 后获得 |
| **运行模式** | 默认 webhook | `webhook`(推荐) / `polling`(无 HTTPS 时用) |
| **Webhook 域名** | webhook 模式建议填 | 形如 `https://shop.example.com`，留空则用「站点 URL」 |
| **客服管理群 ID** | ⚠️ 用客服时必填 | 形如 `-1001234567890`，需开启 Topics 的 supergroup |
| **管理员 Telegram ID** | ⚠️ 用客服管理命令时必填 | 多个用英文逗号分隔 |
| 启用双向客服 | 否 | 默认开 |
| 启用推广分销返利推送 | 否 | 默认开 |
| 支付成功自动推送 | 否 | 默认开 |
| 管理群同步新订单 | 否 | 默认开 |
| 支持的支付通道（pay_ids） | 否 | 留空=全部启用商品支付的通道 |
| Bot 欢迎语 | 否 | 支持 `{name}` 占位符 |
| 客服首次接入提示 | 否 | 自定义 |
| 消息限频（秒） | 否 | 默认 2 |
| 禁用 SSL 校验 | 否 | 某些环境需要 |
| Cron Token | 仅 polling+HTTP cron 模式 | 见下方备用方式 |

---

## 🔁 备用：Long Polling 模式（无 HTTPS 时用）

如果你的站点确实无法上 HTTPS（罕见），把「运行模式」切到 `polling`，然后任选一种：

### supervisord（推荐 7x24）

```ini
[program:acgshop_telegrambot]
directory=/path/to/Acg-Faka-Local
command=php app/Plugin/TelegramBot/bot.php
autostart=true
autorestart=true
```

### 命令行直接跑

```bash
nohup php app/Plugin/TelegramBot/bot.php > runtime/tgbot.log 2>&1 &
```

### HTTP cron 定时拉取

1. 配置「Cron Token = 随机字符串」
2. 加 cron：
   ```cron
   * * * * * curl -s "https://你的域名/plugin/TelegramBot/cli/run?token=你的cron_token" > /dev/null
   ```

---

## 🎯 用户流程演示

### 游客主菜单

```
🛍 开始购物
🧾 我的订单
🫂 绑定账号
👤 注册账号
👩‍🚀 人工客服
🌐 Language
🫧 重载菜单
```

### 已绑定主菜单

```
🛍 开始购物
🧾 我的订单
🌐 一键登录网页端  ←  生成 10 分钟有效的 SSO 链接
🤝 推广分销         ←  查看返利统计 + 专属推广链接
👩‍🚀 人工客服
🚪 解绑账号
🫧 重载菜单
```

### 商品详情页

```
👜 账号带SKU带预选测试

☑ 自动发货  ☑ 已售 0  ☑ 库存 510

⬇ 选择宝贝类型 ⬇
● 普通账号 - ¥90
○ 高级账号 - ¥120

⬇ 自助选号 ⬇
点击开始选号,不选择则随机发货

⬇ 收货人信息 ⬇
* 联系方式:user@example.com 📝
* 购买数量:1 📝

⬇ 付款 ¥90.00 ⬇
» TRC20-USDT(免挂版) «
» 支付宝
» 微信支付

🔙返回上一级  ✖️取消
✅ 立即下单
```

---

## 🛠 管理员指令（在管理群对应 Topic 内执行）

| 命令 | 说明 |
| --- | --- |
| `/ban` | 封禁当前 Topic 对应的用户，禁止其发送消息 |
| `/unban` | 解禁 |
| `/clear` | 删除当前 Topic 所有消息映射 + 删除该 Topic |
| `/info` | 查看当前 Topic 对应用户的 TG ID / 绑定状态等 |

需要发起者 TG ID 在「管理员 Telegram ID」白名单内才生效。

---

## 🗄 数据表

| 表 | 用途 |
| --- | --- |
| `plugin_telegrambot_user` | TG user_id ↔ 商城 user_id 映射 + 状态机 + 客服 topic_id |
| `plugin_telegrambot_msgmap` | 用户消息 id ↔ 群消息 id 双向映射（用于 reply 链） |
| `plugin_telegrambot_sso` | 一次性 web 登录 token |
| `plugin_telegrambot_order` | TG 下单的订单 ↔ tg_user 映射（用于付款后推送） |
| `plugin_telegrambot_state` | 全局 KV 存储（webhook secret / polling offset 等） |

---

## 🪝 使用的 Hook 点位

- `USER_API_ORDER_PAY_AFTER` — 订单付款成功 → 推送发货 + 返利提醒
- `ADMIN_VIEW_NAV` — 后台导航栏加一个「📱 TG Bot」入口
- `Plugin::START` — 启用时校验 Bot Token + 自动注册 Webhook
- `Plugin::STOP` — 停用时自动 deleteWebhook
- `Plugin::SAVE_CONFIG` — 保存配置后自动重新 setWebhook

---

## 🆘 常见问题

更多 → 后台访问「📖 Wiki」页面：`/plugin/TelegramBot/admin/wiki`

**Q: 启用时报「Telegram Webhook 仅支持 HTTPS」？**
A: 你填的 Webhook 域名是 `http://`。给域名上 HTTPS 证书，或临时切到 polling 模式。

**Q: 启用时报「setWebhook 拒绝」？**
A: 检查域名拼写、HTTPS 证书是否有效、Telegram 能否从公网访问到 `/plugin/TelegramBot/webhook/recv`。可用 `curl https://你的域名/plugin/TelegramBot/webhook/info` 自检。

**Q: 双向客服 Topic 没创建？**
A: 群必须是 supergroup 且开 Topics，Bot 必须是管理员并有「管理 Topic」权限。

**Q: 下单后没有支付按钮？**
A: 主程序后台「支付管理」里至少要启用一个 `commodity=1` 的支付通道。本插件不重复造支付，所有 USDT/微信/支付宝/其他通道都走主程序卡网原生。

**Q: 商品 SKU 名字超长导致 callback_data 超 64 字节？**
A: Telegram 限制 callback_data ≤ 64 字节。请把 SKU/race 名称改短（4-5 个中文字符以内）。

---

## 🙏 致谢

- 双向客服转发机制参考自 [MiHaKun/Telegram-interactive-bot](https://github.com/MiHaKun/Telegram-interactive-bot)
- 主程序 [Acg-Faka-Local](https://github.com/NoDoorAction/Acg-Faka-Local)

---

## 📄 License

MIT
