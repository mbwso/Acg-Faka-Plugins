# TelegramBot —— Acg-Faka 智能购物机器人

把整个商城 **1:1** 复刻到 Telegram，并附带 **Topic 模式双向客服** + **分销返利推送** + **Bot↔网页账号互通**。

> 仅需在后台填一个 **Bot Token**，最简 30 秒上手。

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

## 🧱 依赖

主程序已自带的依赖：
- `guzzlehttp/guzzle` ≥ 7.4
- `illuminate/database` ≥ 7.30.6
- `firebase/php-jwt` ≥ 6.11
- PHP ≥ 8.0

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

## ⚙️ 配置

后台 → 插件管理 → TelegramBot → 配置：

| 字段 | 必填 | 说明 |
| --- | --- | --- |
| **Bot Token** | ✅ | 在 [@BotFather](https://t.me/BotFather) 创建 Bot 后获得 |
| **客服管理群 ID** | ⚠️ 用客服时必填 | 形如 `-1001234567890`。需开启 **Topics** 的 supergroup，Bot 设为管理员并授「管理 Topic」 |
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
| Cron Token | 仅 HTTP 模式 | 见下方启动方式 |

---

## 🚀 启动 Bot（任选其一）

### 🥇 方式一：CLI + supervisord（推荐 7x24 稳定）

`/etc/supervisor/conf.d/acgshop_telegrambot.conf`:

```ini
[program:acgshop_telegrambot]
directory=/path/to/Acg-Faka-Local
command=php app/Plugin/TelegramBot/bot.php
autostart=true
autorestart=true
startretries=10
stderr_logfile=/var/log/tgbot.err.log
stdout_logfile=/var/log/tgbot.out.log
user=www-data
```

```bash
supervisorctl reread && supervisorctl update && supervisorctl start acgshop_telegrambot
```

### 🥈 方式二：命令行直接跑（开发/小流量）

```bash
cd /path/to/Acg-Faka-Local
nohup php app/Plugin/TelegramBot/bot.php > runtime/tgbot.log 2>&1 &
```

### 🥉 方式三：HTTP cron 拉取（适合宝塔/虚拟主机不能开常驻进程）

1. 在配置里设 `cron_token = 随机字符串`（如 `openssl rand -hex 16`）
2. 添加定时任务，每分钟拉一次：
   ```cron
   * * * * * curl -s "https://你的域名/plugin/TelegramBot/cli/run?token=你的cron_token" > /dev/null
   ```

> Run 模式：每次调用最多跑 50 秒，期间 long-poll 取消息；适合 1 分钟级别的 cron。

---

## 📋 部署清单（30 秒上手版）

1. ✅ 在 [@BotFather](https://t.me/BotFather) 用 `/newbot` 命令创建 Bot，复制 Token
2. ✅ 后台「插件管理」启用 TelegramBot，填 Token
3. ✅ 启动 Bot（任选一种方式）
4. ✅ Telegram 里搜你的 Bot → `/start` —— 立即可用！

> 👇 如果你要双向客服 / 群通知：

5. 新建一个 supergroup，设置里开启「Topics」
6. 把你的 Bot 拉进群，设为管理员，授予「管理 Topic」权限
7. 用 [@getidsbot](https://t.me/getidsbot) 查到群 ID（形如 `-100...`），填进配置
8. 把自己的 TG ID 填到「管理员 Telegram ID」

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

### 商品详情页（自动跟随 SKU 切换价格）

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
| `/info` | 查看当前 Topic 对应用户的 TG ID / 绑定状态等元信息 |

---

## 🗄 数据表

| 表 | 用途 |
| --- | --- |
| `plugin_telegrambot_user` | TG user_id ↔ 商城 user_id 映射 + 状态机 + 客服 topic_id |
| `plugin_telegrambot_msgmap` | 用户消息 id ↔ 群消息 id 双向映射（用于 reply 链） |
| `plugin_telegrambot_sso` | 一次性 web 登录 token |
| `plugin_telegrambot_order` | TG 下单的订单 ↔ tg_user 映射（用于付款后推送） |
| `plugin_telegrambot_state` | 全局 KV 存储（update offset 等） |

所有表都按规范带 `__PREFIX__plugin_telegrambot_` 前缀。

---

## 🪝 使用的 Hook 点位

- `USER_API_ORDER_PAY_AFTER` — 订单付款成功 → 推送发货 + 返利提醒
- `ADMIN_VIEW_NAV` — 后台导航栏加一个「📱 TG Bot」入口
- `Plugin::START` — 启用时校验 Bot Token

---

## 🆘 常见问题

**Q: Bot 收不到消息？**
A: 检查 1) Bot 进程是否在运行（`ps aux | grep bot.php` 或 supervisor status）  2) 后台 → 控制台页面是否显示「✅ Bot 在线」 3) 防火墙/SSL 设置

**Q: 双向客服 Topic 没创建？**
A: 1) 群必须是 supergroup 且开启 Topics  2) Bot 必须是群管理员  3) Bot 必须拥有「管理 Topic」权限

**Q: 下单后没有支付按钮？**
A: 主程序后台「支付管理」里至少要启用一个 `commodity=1` 的支付通道。

**Q: 卡网 USDT 通道怎么接？**
A: 本插件不重复造支付。你在主程序后台正常添加 USDT 支付通道（如 ApiNotification 配合的 EpUsdt / Codepay 等支付插件），Bot 端会自动出现。

**Q: 商品 SKU 名字超长导致 callback_data 超 64 字节怎么办？**
A: Telegram 限制 callback_data ≤ 64 字节。本插件用 base64 编码 SKU 选择参数，中文 SKU 4-5 个字以内是安全的。如果你的 SKU 名字非常长，建议改短。

---

## 🙏 致谢

- 双向客服转发机制参考自 [MiHaKun/Telegram-interactive-bot](https://github.com/MiHaKun/Telegram-interactive-bot)
- 主程序 [Acg-Faka-Local](https://github.com/NoDoorAction/Acg-Faka-Local)

---

## 📄 License

MIT
