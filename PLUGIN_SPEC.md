# Acg-Faka-Local 插件 / 支付通道 / 模板 编写规范

> 本文档同时面向**人**和**AI**（Claude / Cursor / Copilot 等）。AI 在按本规范生成代码时**必须严格遵循**这里给出的命名、目录、签名、字段名；遇到本规范未覆盖的情况，**应当向用户提问而不是凭训练数据猜测**——因为本项目从早期 Acg-Faka 分叉后改动较大，互联网上能搜到的"异次元发卡 / 彩虹发卡"插件教程**绝大部分已过时或不适用**。
>
> 适用主程序版本：**Acg-Faka-Local ≥ 3.5.1**（已脱离官方应用商店，纯 GitHub 仓库分发）。
> 主仓库：[`NoDoorAction/Acg-Faka-Local`](https://github.com/NoDoorAction/Acg-Faka-Local)
> 插件仓库（本仓库）：[`NoDoorAction/Acg-Faka-Plugins`](https://github.com/NoDoorAction/Acg-Faka-Plugins)

---

## 0. 阅读路线图

| 你是谁                         | 看哪几节                                         |
| ------------------------------ | ------------------------------------------------ |
| 第一次写插件的开发者           | §1 → §2 → §3 → §4 → §10                          |
| 要写一个新支付通道             | §1 → §2 → §6 → §10                               |
| 要写一个站点 / 会员中心模板    | §1 → §2 → §7 → §10                               |
| 给已有插件加功能 / 改 bug      | §3 / §4 / §5 + §11                               |
| AI 助手（生成代码前必读）      | §0 → §1 → §2 → §8 → §9 → §11                     |

---

## 1. 三类插件 & 总览

主程序认识**三类**且仅三类扩展，由 `plugins.json` 里的 `type` 字段区分：

| `type` | 中文名     | 仓库目录   | 安装后落到主程序                  | 入口配置文件                  |
| ------ | ---------- | ---------- | ---------------------------------- | ----------------------------- |
| `0`    | 通用插件   | `plugins/` | `app/Plugin/{Key}/`               | `Config/Info.php`             |
| `1`    | 支付插件   | `pays/`    | `app/Pay/{Key}/`                  | `Config/Info.php`             |
| `2`    | 站点模板   | `themes/`  | `app/View/User/Theme/{Key}/`       | `Config.php`（**根目录下**）  |

> 关键约束：
> - **`{Key}` 必须是大驼峰命名（首字母大写，仅字母/数字/下划线）**。`{Key}` 同时是：仓库目录名 / 安装目录名 / PHP 命名空间段 / `plugins.json` 的 `key` 字段。一处错处处错。
> - **不允许动态修改库表结构**。所有建表 / 改字段必须放在 `install.sql` / `update.sql`，详见 §5。
> - **不允许在运行时联网下载代码并执行**。代码必须随仓库提交，所有依赖打包进 `Vendor/`（推荐）或 `composer.json`。

---

## 2. 主程序提供给插件的运行时

下面这些是主程序内核保证可用的全局符号，**AI 必须按此调用，不要发明类名 / 方法名**。

### 2.1 常量类（必须用，不要硬编码字符串）

| 类                                 | 用途                                                                                 |
| ---------------------------------- | ------------------------------------------------------------------------------------ |
| `App\Consts\Plugin`                | 通用插件 `Info.php` / `Config.php` 里用到的字段 key：`NAME` / `AUTHOR` / `WEB_SITE` / `DESCRIPTION` / `VERSION` / `STATUS` |
| `App\Consts\Hook`                  | **所有可用 hook 挂载点常量**，完整列表见 §4.3                                         |
| `App\Consts\Pay`                   | 支付回调字段映射常量：`IS_SIGN` / `IS_STATUS` / `FIELD_STATUS_KEY` / `FIELD_STATUS_VALUE` / `FIELD_ORDER_KEY` / `FIELD_AMOUNT_KEY` / `FIELD_RESPONSE` |
| `App\Consts\Render`                | 模板渲染引擎：`ENGINE_SMARTY = 0` / `ENGINE_PHP = 1`                                  |
| `Kernel\Annotation\Hook`           | `#[Hook(point: ...)]` PHP 注解，用于声明 hook 监听方法                                |
| `Kernel\Annotation\Plugin`         | `#[Plugin(state: ...)]` PHP 注解，用于声明生命周期回调（`START` / `STOP` / `INSTALL` / `UNINSTALL` / `UPGRADE` / `SAVE_CONFIG`） |
| `Kernel\Annotation\Interceptor`    | 控制器拦截器，常用于强制登录：`#[Interceptor(ManageSession::class)]` 或 `UserSession::class` |

### 2.2 全局辅助函数（`kernel/Helper.php`）

| 函数                                    | 用途                                                                                                 |
| --------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| `getPluginConfig(string $name): array` | 读取插件运行时配置（即 `Config/Config.php` 当前内容）。**只读**，不要写。                              |
| `hook(int $point, mixed &...$args)`     | 主动触发一个 hook 点。**插件极少需要**，主要给主程序自己用。                                          |
| `getHookNum(int $point): int`           | 当前点位有几个插件监听。可用于"如有插件接管就跳过原逻辑"。                                            |
| `Plugin($key, $src, $debug=false)`      | 生成静态资源 URL：`/app/Plugin/{Key}/{src}?v={Version}`。在模板里给 CSS/JS 加版本号用。               |
| `PluginView($src, $debug=false)`        | 同上，但 `{Key}` 自动取自当前路由（仅在 `/plugin/...` 路由下有效）。                                  |
| `config(string $name): array`           | 读 `config/{name}.php`。常见 `config("app")['version']`。                                            |

### 2.3 控制器基类

| 基类                                          | 何时用                                                                       |
| --------------------------------------------- | ---------------------------------------------------------------------------- |
| `App\Controller\Base\View\ManagePlugin`       | 渲染**后台 HTML 页面**（需要登录的运营页）                                    |
| `App\Controller\Base\View\UserPlugin`         | 渲染**前台 HTML 页面**                                                       |
| `App\Controller\Base\API\ManagePlugin`        | 暴露**后台 JSON 接口**                                                       |
| `App\Controller\Base\API\UserPlugin`          | 暴露**前台 JSON 接口**                                                       |

`render()` 签名（View 系）：

```php
protected function render(?string $title, string $template, array $data = [], bool $controller = false): string
```

- `$title`：浏览器标题；可为 `null`。
- `$template`：相对插件 `View/` 目录的模板文件名。
- `$data`：模板变量。框架会自动注入 `$config`（站点配置）、`$user`（当前后台账号，仅 ManagePlugin）、`$manage_view_path`（后台公共片段目录）。
- `$controller`：**关键差异**。
  - `false`（默认）：用于 **hook 调用上下文**——例如在 `ADMIN_VIEW_MENU` hook 里 `echo $this->render(null, "Menu.html");`，模板路径取自当前 hook 所属插件。
  - `true`：用于 **HTTP 控制器上下文**——直接由路由 `/plugin/{key}/{controller}/{method}` 调起，模板路径取自当前控制器所属插件。

> AI 误区：写控制器返回页面时若忘了 `controller: true`，会去**别的插件**目录找模板，从而 404。**控制器里一律传 `controller: true`，hook 里一律不传。**

---

## 3. 通用插件（type = 0）

### 3.1 目录结构（**严格规定**）

```
plugins/{Key}/
├── Config/
│   ├── Info.php            # 必需，元数据
│   ├── Config.php          # 必需，运行时配置（即使是空数组也要有）
│   ├── Submit.php          # 可选，后台编辑此插件时弹出的表单字段
│   └── Submit.js           # 可选，与 Submit.php 二选一，复杂场景下用 JS 自定义
├── Hook/                   # 可选，每个 .php 一个 Hook 类
│   └── *.php
├── Controller/             # 可选，每个 .php 一个 Controller 类
│   └── *.php
├── View/                   # 可选，模板文件（Smarty 语法）
│   └── *.html
├── Assets/                 # 可选，静态资源（CSS/JS/图片）
│   └── ...
├── icon.png                # 可选，应用商店里显示的图标（也可放 Assets/icon.png）
├── install.sql             # 可选，安装时执行的 SQL（详见 §5）
└── update.sql              # 可选，升级时执行的 SQL
```

> **`Config/Config.php` 必须存在**，哪怕只是 `return [];`，否则主程序的 hook 重建逻辑会跳过你的插件。

### 3.2 `Config/Info.php`

**唯一格式**（不要照搬"异次元"教程的 ArrayAccess 风格）：

```php
<?php
declare(strict_types=1);

use App\Consts\Plugin;

return [
    Plugin::NAME        => '示例插件',
    Plugin::AUTHOR      => 'YourName',
    Plugin::WEB_SITE    => 'https://example.com',   // 无主页填 '#'
    Plugin::DESCRIPTION => '一句话说清这个插件做什么。',
    Plugin::VERSION     => '1.0.0',                 // 语义化版本，与 plugins.json 同步
];
```

### 3.3 `Config/Config.php`

运行时配置文件。主程序在用户**首次启用插件**时把 `STATUS => 1` 写进来；之后用户每次在后台保存表单，新值都 merge 进这里。

```php
<?php
declare(strict_types=1);

return [
    // 空数组完全合法
];
```

> 不要在仓库里预填用户自己的数据（密钥、token 等）。AI 生成时也**不要**写 placeholder API key——留空字符串或干脆不写这个键。

### 3.4 `Config/Submit.php`（后台配置表单）

返回**表单字段数组**，每个字段一个关联数组：

```php
<?php
declare(strict_types=1);

return [
    [
        "title"       => "API 地址",         // 显示在字段上方的标签
        "name"        => "api_url",          // 写入 Config.php 的 key
        "type"        => "input",            // 见下表
        "placeholder" => "https://...",      // 占位符
        // "default"   => "",                // 可选默认值
    ],
    [
        "title" => "通知模式",
        "name"  => "mode",
        "type"  => "radio",
        "dict"  => [
            ["id" => "sync",  "name" => "同步"],
            ["id" => "async", "name" => "异步"],
        ],
        "default" => "sync",
    ],
];
```

#### 3.4.1 支持的 `type` 全集（来自 `assets/common/js/component/form.js`）

| `type`        | 渲染为                          | 额外字段                                                                  |
| ------------- | -------------------------------- | ------------------------------------------------------------------------- |
| `input`       | `<input type="text">`           | `placeholder` / `default`                                                 |
| `password`    | `<input type="password">`       | 同上                                                                      |
| `number`      | `<input type="number">`         | 同上                                                                      |
| `date`        | 文本框（前端不强制日期控件）     | 同上                                                                      |
| `textarea`    | `<textarea>`                    | `placeholder` / `default`                                                 |
| `editor`      | 富文本编辑器                     | `uploadUrl`（不填走默认 `/admin/api/upload/send`）                        |
| `radio`       | 单选组                           | **必填** `dict: [{id, name}, ...]`；`default` 为 `id` 值                  |
| `checkbox`    | 多选组                           | 同 `radio`；`default` 为 id 数组                                          |
| `select`      | 下拉框                           | 同 `radio`                                                                |
| `switch`      | 开关                             | `default` 为 `"1"` 或 `"0"`                                               |
| `html`        | 任意 HTML 块（仅展示，不收数据） | `default` 为 HTML 字符串                                                  |
| `image`       | 图片上传                         | `uploadUrl` / `photoAlbumUrl`（不填走默认）                                |
| `file`        | 文件上传                         | `uploadUrl`                                                                |
| `treeCheckbox`/`treeSelect` | 树形多选 / 单选     | `dict` 同上但允许 `children`                                              |
| `widget`      | 业务级"商品控件"，**通用插件别用**，会跟商品控件冲突 | —                                                          |

#### 3.4.2 `Submit.js`（高级）

返回**字符串**（注意是字符串，整个文件 `return "..."`），内容是 JS 代码片段，**会被 `eval` 执行**，产物必须是一个数组：

```js
[
    {
        name: "页签1",
        form: [ /* 同 Submit.php 的字段对象 */ ]
    },
    {
        name: "页签2",
        form: [ /* ... */ ]
    }
]
```

> 仅当你需要**多页签**或**字段间联动**（`change` 回调）时再用。普通场景 `Submit.php` 就够了。

### 3.5 `Hook/*.php`（业务挂钩）

```php
<?php
declare(strict_types=1);

namespace App\Plugin\{Key}\Hook;       // 命名空间必须严格对应目录

use App\Controller\Base\View\UserPlugin;  // 或 ManagePlugin / API\UserPlugin / API\ManagePlugin
use Kernel\Annotation\Hook;
use Kernel\Annotation\Plugin;

class Main extends UserPlugin
{
    #[Plugin(state: Plugin::START)]
    public function onStart(): void
    {
        // 用户点"启用"时执行；抛 JSONException 可阻断启用
        $config = getPluginConfig('{Key}');
        if (empty($config['api_url'])) {
            throw new \Kernel\Exception\JSONException("启用前请先填写 API 地址");
        }
    }

    #[Plugin(state: Plugin::STOP)]
    public function onStop(): void { /* 停用时清理 */ }

    #[Plugin(state: Plugin::INSTALL)]
    public function onInstall(): void { /* 仓库拉取完成、SQL 执行后 */ }

    #[Plugin(state: Plugin::UPGRADE)]
    public function onUpgrade(): void { /* update.sql 执行后 */ }

    #[Plugin(state: Plugin::UNINSTALL)]
    public function onUninstall(): void { /* 卸载前最后一刻 */ }

    #[Plugin(state: Plugin::SAVE_CONFIG)]
    public function onSaveConfig(): void { /* 后台保存表单后 */ }

    #[Hook(point: \App\Consts\Hook::USER_API_ORDER_PAY_AFTER)]
    public function onPaid($commodity, $order, $pay): void
    {
        // 客户付款成功后，做点什么……
    }
}
```

**硬性约束**：

1. **类必须 `extends` §2.3 的某个基类**（即便不用 `render()`，也要 `extends UserPlugin`），否则 DI 注入会失败。
2. `#[Hook(point: ...)]` 的 `point` **必须**来自 `App\Consts\Hook::*` 常量，不要写裸数字。
3. **同一个挂载点可以挂多次**，但**同一个类里不要重名方法**。
4. 方法可以是 `void`，也可以 `return string`（会被拼到 hook 输出）或 `return array`（聚合）。
5. 不需要 `__construct`，框架使用反射实例化并注入依赖。

### 3.6 `Controller/*.php`（独立路由）

路由形如 `/plugin/{key}/{controller}/{method}`，**`{key}` 大小写不敏感但建议小写**，`{controller}` 是类名小写，`{method}` 是方法名。

```php
<?php
declare(strict_types=1);

namespace App\Plugin\{Key}\Controller;

use App\Controller\Base\View\ManagePlugin;
use App\Interceptor\ManageSession;
use Kernel\Annotation\Interceptor;

#[Interceptor(ManageSession::class)]   // 强制要求已登录后台
class Demo extends ManagePlugin
{
    /**
     * 访问 /plugin/{key}/demo/test 返回页面
     * @throws \Kernel\Exception\ViewException
     */
    public function test(): string
    {
        return $this->render(
            title:      '示例页面',
            template:   'Demo.html',
            data:       ['msg' => 'hello'],
            controller: true,            // ← 控制器里必传 true
        );
    }
}
```

API 控制器（返回 JSON）：

```php
namespace App\Plugin\{Key}\Controller;

use App\Controller\Base\API\UserPlugin;

class Api extends UserPlugin
{
    public function ping(): array
    {
        return ['code' => 200, 'msg' => 'ok', 'data' => []];
    }
}
```

返回 `array` 会自动以 JSON 输出（具体响应包装由 `Base` 系处理，保持习惯就好）。

### 3.7 `View/*.html`（模板）

使用 **Smarty** 语法，但定界符是 `#{` 和 `}`（不是 Smarty 默认的 `{` `}`）。常见片段：

```html
#{include file=$manage_view_path|cat:"/Header.html"}
<div>...</div>
#{if $config.something == 1}
  <span>启用</span>
#{/if}
#{foreach from=$items item=row}
  <li>{$row.name}</li>
#{/foreach}
#{include file=$manage_view_path|cat:"Footer.html"}
```

加载本插件自己的静态资源：

```html
<link rel="stylesheet" href="#{$_plugin_asset_url|default:""}">
<!-- 推荐用 helper 函数 -->
<link rel="stylesheet" href="{Plugin('{Key}', 'Assets/style.css')}">
<script src="{Plugin('{Key}', 'Assets/main.js')}"></script>
```

> AI 误区：**不要**用 `{$var}`（Smarty 默认风格）；**必须**用 `#{...}` 形式，否则模板渲染会跳过你的标签。变量插值仍是 `{$var}`，但流程控制需要 `#{if}` / `#{foreach}`。

---

## 4. Hook 系统详解

### 4.1 工作机制（AI 必读）

1. 插件目录下所有 `Hook/*.php` 在**用户点击"启用"时**被反射扫描。
2. 框架查找方法上的 `#[Hook(point: X)]` 注解，把 `(pluginName, namespace, method)` 三元组写入 **加密** 的 hook 缓存 `runtime/plugin/hook`。
3. 每次请求加载时，主程序从该缓存读出某个 `point` 对应的回调列表，按注册顺序依次 `call_user_func_array`。
4. **重要**：编辑了 Hook 文件后，**必须重启该插件**（后台停用 → 启用）才会被重新扫描。`runtime/plugin/plugin.cache` 与 `runtime/plugin/hook` 也会随启用 / 停用清空。
5. 如果缓存解密失败（如换了服务器），主程序会自动 rebuild。

### 4.2 Hook 方法的传参规则

调用时传参通过引用，可修改。例如 `USER_API_INDEX_COMMODITY_LIST` 会传 `array &$data`，hook 直接对 `$data` 改值即可影响前台列表。返回值的语义：

- `return string`：拼接到输出（适合 View 类挂载点）。
- `return array`：聚合到结果数组（多个 hook 时一起返回）。
- `return $stockEntity`（仅 `SERVICE_SHOP_GET_ITEM_STOCK`）：直接接管库存查询。
- 其它返回值：忽略。

### 4.3 全部 Hook 挂载点（来自 `app/Consts/Hook.php`）

> AI 选择 hook 点时，**禁止**用本表以外的数字，**禁止**自己造点位。下表列出的是当前主程序定义的**全部**，缺什么去给主仓库提 issue，不要绕过。

#### 后台 View 类（输出 HTML 片段）

| 常量                                     | 值        | 挂载位置                                              |
| ---------------------------------------- | --------- | ----------------------------------------------------- |
| `ADMIN_VIEW_HEADER`                      | `0x2`     | 后台 `<head>` 里（放 css link）                       |
| `ADMIN_VIEW_FOOTER`                      | `0x1`     | 后台 `</body>` 前（放 js）                            |
| `ADMIN_VIEW_BODY`                        | `0x10201` | 后台 `<body>` 全局                                    |
| `ADMIN_VIEW_MENU`                        | `0x3`     | 后台左侧菜单                                          |
| `ADMIN_VIEW_NAV`                         | `0x4`     | 后台顶部 NAV（应用商店按钮旁）                        |
| `ADMIN_VIEW_USER_HEADER`                 | `0x10002` | 后台"会员管理"页 header                              |
| `ADMIN_VIEW_USER_FOOTER`                 | `0x9`     | 后台"会员管理"页底部                                 |
| `ADMIN_VIEW_USER_TOOLBAR`                | `0x10`    | 后台"会员管理"页按钮区                               |
| `ADMIN_VIEW_USER_TABLE`                  | `0x8`     | 后台"会员管理"表格列                                 |
| `ADMIN_VIEW_COMMODITY_TABLE`             | `0x5`     | 后台"商品管理"表格列                                 |
| `ADMIN_VIEW_COMMODITY_FOOTER`            | `0x6`     | 后台"商品管理"页底部                                 |
| `ADMIN_VIEW_COMMODITY_TOOLBAR`           | `0x7`     | 后台"商品管理"按钮区                                 |
| `ADMIN_VIEW_COMMODITY_POST`              | `0x45`    | 后台"商品添加"表单字段                               |
| `ADMIN_VIEW_CATEGORY_TOOLBAR`            | `0x701`   | 后台"商品分类"按钮区                                 |
| `ADMIN_VIEW_CATEGORY_TABLE`              | `0x702`   | 后台"商品分类"表格列                                 |
| `ADMIN_VIEW_CATEGORY_POST`               | `0x703`   | 后台"商品分类"表单                                   |
| `ADMIN_VIEW_ORDER_TABLE`                 | `0x11`    | 后台"订单管理"表格列                                 |
| `ADMIN_VIEW_ORDER_FOOTER`                | `0x12`    | 后台"订单管理"底部                                   |
| `ADMIN_VIEW_ORDER_TOOLBAR`               | `0x13`    | 后台"订单管理"按钮区                                 |
| `ADMIN_VIEW_CONFIG_TOOLBAR`              | `0x14`    | 后台"网站设置"工具栏（**返回二维数组**：`[[title,url],...]`） |

#### 后台 API 类（业务事件）

| 常量                                     | 值        | 传参                                                  |
| ---------------------------------------- | --------- | ----------------------------------------------------- |
| `ADMIN_API_PLUGIN_SAVE_CONFIG`           | `0x15`    | `string $pluginName, array $postMap`，可改 `$postMap` |

#### 前台 View 类（输出 HTML 片段）

| 常量                            | 值        | 挂载位置                                          |
| ------------------------------- | --------- | ------------------------------------------------- |
| `USER_VIEW_INDEX_HEADER`        | `0x10001` | 前台首页 `<head>`                                 |
| `USER_VIEW_INDEX_BODY`          | `0x10003` | 前台首页 body                                     |
| `USER_VIEW_INDEX_FOOTER`        | `0x10004` | 前台首页 footer                                   |
| `USER_VIEW_HEADER`              | `0x128`   | 各前台模板 Common header                          |
| `USER_VIEW_BODY`                | `0x129`   | 各前台模板 Common body                            |
| `USER_VIEW_FOOTER`              | `0x130`   | 各前台模板 Common footer                          |
| `USER_GLOBAL_VIEW_HEADER`       | `0x228`   | 用户端**全局** header（含会员中心）              |
| `USER_GLOBAL_VIEW_BODY`         | `0x229`   | 用户端**全局** body                              |
| `USER_GLOBAL_VIEW_FOOTER`       | `0x230`   | 用户端**全局** footer                            |
| `USER_VIEW_AUTH_LOGIN_BUTTON`   | `0x41`    | 登录页第三方按钮                                  |
| `USER_VIEW_AUTH_REGISTER_BUTTON`| `0x42`    | 注册页第三方按钮                                  |
| `USER_VIEW_SECURITY_NAV`        | `0x43`    | 会员安全中心导航                                  |
| `USER_VIEW_PERSONAL_FORM`       | `0x44`    | 会员个人资料表单                                  |
| `USER_VIEW_COMMODITY_POST`      | `0x46`    | 前台商品下单表单                                  |
| `USER_VIEW_MENU`                | `0x57`    | Cartoon 主题左侧菜单                              |
| `USER_VIEW_HEADER_NAV`          | `0x88`    | Cartoon 主题顶部 NAV（返回数组）                  |
| `USER_VIEW_QUERY_TRADE_NO`      | `0x89`    | 查单页订单号后缀小尾巴                            |

#### 前台 API 类（业务事件）

| 常量                                       | 值          | 传参                                                              |
| ------------------------------------------ | ----------- | ----------------------------------------------------------------- |
| `USER_API_ORDER_TRADE_BEGIN`               | `0x16`      | `array $_POST`，可在下单前拦截                                    |
| `USER_API_ORDER_TRADE_AFTER`               | `0x17`      | `Commodity $commodity, Order $order, Pay $pay`，下单成功后        |
| `USER_API_ORDER_TRADE_PAY_BEGIN`           | `0x171`     | 同上，下单后发起支付前                                            |
| `USER_API_ORDER_PAY_AFTER`                 | `0x18`      | 同上，客户**已付款成功**后（最常用于推送 / 履约）                  |
| `USER_API_RECHARGE_AFTER`                  | `0x18191`   | `UserRecharge $recharge, Pay $pay`                                |
| `USER_API_AUTH_REGISTER_BEGIN`             | `0x19`      | 注册前拦截                                                        |
| `USER_API_AUTH_REGISTER_AFTER`             | `0x20`      | `User $user`                                                      |
| `USER_API_AUTH_LOGIN_BEGIN`                | `0x21`      | 登录前拦截                                                        |
| `USER_API_AUTH_LOGIN_AFTER`                | `0x22`      | `User $user`                                                      |
| `USER_API_INDEX_CATEGORY_LIST`             | `0x49`      | `array &$data`（分类列表，指针，可改）                            |
| `USER_API_INDEX_COMMODITY_LIST`            | `0x50`      | `array &$data`                                                    |
| `USER_API_INDEX_COMMODITY_DETAIL_INFO`     | `0x51`      | `array &$data`                                                    |
| `USER_API_INDEX_TRADE_CALC_AMOUNT`         | `0x52`      | `array &$result`（订单金额计算结果，可改）                        |
| `USER_API_INDEX_PAY_LIST`                  | `0x53`      | `array &$list`                                                    |
| `USER_API_INDEX_QUERY_LIST`                | `0x54`      | `array &$list`                                                    |
| `USER_API_INDEX_QUERY_SECRET`              | `0x55`      | `Order $order`                                                    |
| `USER_API_PURCHASE_RECORD_LIST`            | `0x56`      | `array &$list`                                                    |

#### 内核 / 系统级

| 常量                            | 值        | 说明                                                          |
| ------------------------------- | --------- | ------------------------------------------------------------- |
| `KERNEL_INIT`                   | `0x30`    | 核心初始化完成                                                |
| `CONTROLLER_CALL_BEFORE`        | `0x31`    | 控制器调用前（传：控制器名、方法）                            |
| `CONTROLLER_CALL_AFTER`         | `0x32`    | 控制器调用后（传：控制器名、方法、返回值）                    |
| `RENDER_VIEW`                   | `0x33`    | 视图渲染（传 raw 指针）                                       |
| `HTTP_ROUTE_RESPONSE`           | `0x47`    | HTTP 响应返还客户前（拿到完整返回数据）                       |
| `WAF_INTERCEPT`                 | `0x289`   | 防火墙拦截（`string $message`）                              |
| `SERVICE_SMTP_SEND_BEFORE`      | `0x3000`  | 发邮件前 `(array $config, string $email, string $title, string $content)` |
| `SERVICE_SMTP_SEND_SUCCESS`     | `0x3001`  | 发邮件成功                                                    |
| `SERVICE_SMTP_SEND_ERROR`       | `0x3002`  | 发邮件失败                                                    |
| `SERVICE_SHOP_GET_ITEM_STOCK`   | `0x8000`  | `(Commodity $commodity, ?string $race, ?array $sku)` 返回 `Kernel\Plugin\Entity\Stock` 接管库存 |
| `HACK_ROUTE_TABLE_COLUMNS`      | `0x2005`  | 通用表格列扩展                                                |
| `HACK_ROUTE_TABLE_SEARCH`       | `0x2006`  | 通用表格搜索扩展                                              |
| `HACK_SUBMIT_FORM`              | `0x9038`  | 通用提交表单扩展                                              |
| `HACK_SUBMIT_TAB`               | `0x9039`  | 通用提交页签扩展                                              |

---

## 5. 数据库迁移：`install.sql` / `update.sql`

### 5.1 文件位置

放在**插件根目录**（不是 `Config/` 下）：

```
plugins/{Key}/install.sql
plugins/{Key}/update.sql
```

### 5.2 写法

- **必须使用** `__PREFIX__` 作为表前缀占位符，框架运行时替换为站点配置里的真实 `table_prefix`（通常是 `acg_`）。
- 一条语句一行结尾 `;`，能被 `Rah\Danpu\Import` 解析即可。
- 文件**编码 UTF-8 无 BOM**。

```sql
-- install.sql 示例
CREATE TABLE IF NOT EXISTS `__PREFIX__plugin_mylog` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` INT UNSIGNED NOT NULL,
    `payload` TEXT NULL,
    `create_time` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

```sql
-- update.sql 示例（从 1.0.0 升到 1.1.0 时加字段）
ALTER TABLE `__PREFIX__plugin_mylog` ADD COLUMN `status` TINYINT NOT NULL DEFAULT 0;
```

### 5.3 严禁事项

- **不要在 `Hook` 里用 `Schema::create()` / `DB::statement('ALTER ...')` 之类的运行时建表**。这是审核 PR 时直接拒收的硬指标。
- 表名必须以 `__PREFIX__plugin_` 开头，避免和主表冲突。

---

## 6. 支付插件（type = 1）

### 6.1 目录结构

```
pays/{Key}/
├── Config/
│   ├── Info.php          # 必需，元数据 + 回调字段映射
│   ├── Config.php        # 必需，运行时配置（可为空数组）
│   └── Submit.php        # 必需，后台填密钥的表单
├── Impl/
│   ├── Pay.php           # 必需，下单实现
│   └── Signature.php     # 必需，回调签名验证
├── View/                 # 可选，本地渲染收银台时用
├── Assets/               # 可选
├── Vendor/               # 可选，第三方 SDK；如有 Vendor/autoload.php 会自动 require
└── composer.json         # 可选
```

> 支付插件**不能**使用 hook、生命周期注解（`#[Plugin(state)]`）、控制器路由，**也不需要** `Config/Submit.js`。它只是被主程序按接口约定调用。

### 6.2 `Config/Info.php`（注意：字段 key 全小写）

```php
<?php
declare(strict_types=1);

return [
    'version'     => '1.0.0',
    'name'        => '示例支付',
    'author'      => 'YourName',
    'website'     => 'https://example.com',
    'description' => '一句话说明这个通道。',

    // 通道下面的"子类型"，会让后台运营在添加支付方式时选 code
    // key 是 code（整数），value 是显示名
    'options' => [
        1 => '扫码支付',
        2 => 'H5 支付',
    ],

    // 回调验证规则（**字段顺序无所谓，但 key 必须用常量**）
    'callback' => [
        \App\Consts\Pay::IS_SIGN          => true,            // 是否启用签名验证
        \App\Consts\Pay::IS_STATUS        => true,            // 是否启用状态值验证
        \App\Consts\Pay::FIELD_STATUS_KEY   => 'trade_status',  // 回调里"状态"字段名
        \App\Consts\Pay::FIELD_STATUS_VALUE => 'TRADE_SUCCESS', // "状态"等于该值表示成功
        \App\Consts\Pay::FIELD_ORDER_KEY    => 'out_trade_no',  // 回调里"商户订单号"字段名
        \App\Consts\Pay::FIELD_AMOUNT_KEY   => 'total_amount',  // 回调里"金额"字段名
        \App\Consts\Pay::FIELD_RESPONSE     => 'success',       // 通道要求回应给它的字符串
    ],
];
```

> 注意：`callback` 数组的 key 是 `App\Consts\Pay::*` 常量（其值是 `0x1` / `0x2` 这样的整数）。**不要写裸字符串** `'IS_SIGN' => true`，那是错的。

### 6.3 `Config/Submit.php`

字段格式同 §3.4，但场景固定为：要求运营填写商户号、密钥、对接地址等。例：

```php
<?php
declare(strict_types=1);

return [
    ["title" => "商户号", "name" => "mch_id",   "type" => "input",    "placeholder" => "商户号"],
    ["title" => "密钥",   "name" => "key",      "type" => "textarea", "placeholder" => "RSA 私钥 / API Key"],
    ["title" => "网关",   "name" => "url",      "type" => "input",    "placeholder" => "https://api.xxx.com"],
];
```

### 6.4 `Impl/Pay.php`（**必须**严格按此签名）

```php
<?php
declare(strict_types=1);

namespace App\Pay\{Key}\Impl;

use App\Entity\PayEntity;
use App\Pay\Base;
use Kernel\Exception\JSONException;

class Pay extends Base implements \App\Pay\Pay
{
    /**
     * 主程序下单时调用此方法。
     * 通过继承自 Base 的字段拿到一切上下文：
     *   $this->amount       float  订单金额（含支付手续费）
     *   $this->tradeNo      string 商户订单号（主程序生成）
     *   $this->config       array  当前通道在后台填的配置（Config/Submit.php 收集到的）
     *   $this->callbackUrl  string 主程序生成的回调 URL（绝对地址）
     *   $this->returnUrl    string 同步跳转 URL
     *   $this->clientIp     string 客户 IP
     *   $this->code         string 当前选择的子通道 code（对应 Info.php['options'] 的 key）
     *   $this->handle       string 当前通道 handle，等同于 {Key}
     *
     * @throws JSONException 任何下单错误用这个抛
     */
    public function trade(): PayEntity
    {
        // 1. 校验配置
        if (empty($this->config['mch_id']) || empty($this->config['key'])) {
            throw new JSONException("请先在后台配置支付通道");
        }

        // 2. 组装请求体
        $param = [
            'mch_id'       => $this->config['mch_id'],
            'out_trade_no' => $this->tradeNo,
            'total_amount' => $this->amount,
            'notify_url'   => $this->callbackUrl,
            'return_url'   => $this->returnUrl,
            'client_ip'    => $this->clientIp,
            'channel'      => $this->code,
        ];

        // 3. 签名 + 调用渠道接口
        $param['sign'] = Signature::sign($param, $this->config['key']);
        $resp = $this->http()->post(
            rtrim($this->config['url'], '/') . '/api/pay',
            ['form_params' => $param]
        );
        $json = json_decode((string)$resp->getBody(), true);

        if (!is_array($json) || ($json['code'] ?? -1) !== 0) {
            $this->log('下单失败：' . ($json['msg'] ?? '未知'));
            throw new JSONException($json['msg'] ?? '下单失败');
        }

        // 4. 用 PayEntity 告诉框架"前端怎么进入支付"
        $entity = new PayEntity();
        $entity->setUrl($json['pay_url']);
        $entity->setType(\App\Pay\Pay::TYPE_REDIRECT);  // 见下表
        // $entity->setOption(['key' => 'value']);       // 可选附加参数
        return $entity;
    }
}
```

#### 6.4.1 `PayEntity::setType` 取值

| 常量                       | 值  | 含义                                                                                       |
| -------------------------- | --- | ------------------------------------------------------------------------------------------ |
| `Pay::TYPE_REDIRECT`       | `2` | 前端直接跳转 `setUrl()` 的 URL（通道托管收银台）                                            |
| `Pay::TYPE_LOCAL_RENDER`   | `3` | 主程序在 `/user/pay/order.{tradeNo}.1` 本地渲染收银台（典型场景：二维码图片，URL 是图片地址） |
| `Pay::TYPE_SUBMIT`         | `4` | 主程序在 `/user/pay/order.{tradeNo}.2` 用 form POST 提交到 `setUrl()`，参数取 `setOption()` |

### 6.5 `Impl/Signature.php`（**必须**实现接口）

```php
<?php
declare(strict_types=1);

namespace App\Pay\{Key}\Impl;

class Signature implements \App\Pay\Signature
{
    /**
     * 主程序在收到通道异步回调时调用此方法验证签名。
     *
     * @param array $data   通道 POST 过来的全部字段（已去掉路由参数）
     * @param array $config 当前通道配置（即 Config/Submit.php 收集到的）
     * @return bool         true=签名通过；false=拒绝该回调
     */
    public function verification(array $data, array $config): bool
    {
        $sign = $data['sign'] ?? '';
        unset($data['sign'], $data['sign_type']);
        return $sign === self::sign($data, $config['key']);
    }

    public static function sign(array $data, string $key): string
    {
        unset($data['sign'], $data['sign_type']);
        ksort($data);
        $str = '';
        foreach ($data as $k => $v) {
            if (is_array($v) || $v === '') continue;
            $str .= "{$k}={$v}&";
        }
        return md5(trim($str, '&') . $key);
    }
}
```

> **回调字段映射的余下校验**（`IS_STATUS` / `FIELD_ORDER_KEY` / `FIELD_AMOUNT_KEY` / `FIELD_RESPONSE`）由主程序按 `Info.php['callback']` 配置自动完成。**插件不要重复校验**，也不要直接读 `$_POST`。
>
> 如果你的渠道签名验证过程中需要把 `$data` 改写（比如 base64 解码、合并多层字段），可以通过 `Kernel\Util\Context::set(\App\Consts\Pay::DAFA, $newData)` 把改写后的数据塞回上下文，主程序后续校验和回调结果取的就是它。

### 6.6 `View/`（仅 TYPE_LOCAL_RENDER 用）

如果选择本地渲染收银台，框架会找 `app/Pay/{Key}/View/Pay.html` 之类的模板，由你提供。模板里通常显示 `$payEntity->getUrl()` 给出的二维码。具体看 `pays/Alipay` / `pays/Codepay` / `pays/VmqPay` 仓库里现成例子。

### 6.7 `Vendor/`（第三方 SDK）

主程序在调用支付时会 `require BASE_PATH . '/app/Pay/{Key}/Vendor/autoload.php'`（**如果存在**）。**推荐**把 composer 依赖 `composer install --no-dev -o` 到本目录里，然后把整个 `Vendor/` 提交进仓库，避免用户没法跑 composer 时插件挂掉。

---

## 7. 模板（站点主题）（type = 2）

### 7.1 目录结构

```
themes/{Key}/
├── Config.php           # 必需，根目录
├── Assets/              # 静态资源
├── Index.php            # ENGINE_PHP 渲染时的首页文件（若用 PHP 引擎）
├── Common/              # 公共片段
├── Dashboard/           # 会员中心 - 个人主页
├── User/                # 会员中心 - 各子页
├── Agent/               # 推广代理子页
└── ...                  # 视主题需要
```

### 7.2 `Config.php`（**接口形式，不是 return array**）

> 这是和通用插件 / 支付插件最大的区别。模板的元数据**写在 PHP `interface` 的 `const` 里**。

```php
<?php
declare(strict_types=1);

namespace App\View\User\Theme\{Key};   // 命名空间必须与目录对齐

use App\Consts\Render;

interface Config
{
    /** 元数据 */
    const INFO = [
        "NAME"        => "示例主题",
        "AUTHOR"      => "YourName",
        "VERSION"     => "1.0.0",
        "WEB_SITE"    => "#",
        "DESCRIPTION" => "一句话介绍这个主题。",
        "RENDER"      => Render::ENGINE_SMARTY,   // 或 Render::ENGINE_PHP
    ];

    /** 可选：后台站点设置里展示的主题自定义表单（字段格式同 §3.4） */
    const SUBMIT = [
        [
            "title" => "色彩模式",
            "name"  => "theme_mode",
            "type"  => "radio",
            "dict"  => [
                ["id" => "auto",  "name" => "跟随系统"],
                ["id" => "light", "name" => "固定白天"],
                ["id" => "dark",  "name" => "固定黑夜"],
            ],
            "default" => "auto",
        ],
    ];

    /** 必需：模板文件重定向表。框架按这张表去找对应页面 */
    const THEME = [
        // ----- 用户前台（购物页） -----
        "INDEX"           => "Index.html",          // 首页 / 列表
        "ITEM"            => "Index.html",          // 商品详情（可与 INDEX 同文件）
        "QUERY"           => "Query.html",          // 查单页

        // ----- 会员中心 -----
        "DASHBOARD"       => "Dashboard/Index.html",
        "RECHARGE"        => "User/Recharge.html",
        "BILL"            => "User/Bill.html",
        "BUSINESS"        => "User/Business.html",
        "CATEGORY"        => "User/Category.html",
        "COMMODITY"       => "User/Commodity.html",
        "CARD"            => "User/Card.html",
        "COUPON"          => "User/Coupon.html",
        "CASH"            => "User/Cash.html",
        "CASH_RECORD"     => "User/CashRecord.html",
        "PERSONAL"        => "User/Personal.html",
        "EMAIL"           => "User/Email.html",
        "PHONE"           => "User/Phone.html",
        "PASSWORD"        => "User/Password.html",
        "ORDER"           => "User/Order.html",
        "PURCHASE_RECORD" => "User/PurchaseRecord.html",

        // ----- 推广代理 -----
        "AGENT_MEMBER"    => "Agent/Member.html",
    ];
}
```

> **没用到的 key 直接删掉**——框架会回退到默认模板。不要写 `"PHONE" => null` 这样的占位。

### 7.3 两种渲染引擎对比

| 引擎                          | 文件后缀     | 语法                                                                  |
| ----------------------------- | ------------ | --------------------------------------------------------------------- |
| `Render::ENGINE_SMARTY` (0)  | `.html`      | Smarty，定界符 `#{...}`，变量 `{$var}`；和通用插件 View 完全一致      |
| `Render::ENGINE_PHP`    (1)  | `.php`       | 原生 PHP；`<?= $var ?>` / `<?php if(...): ?>`                          |

> 一个主题只能选一个引擎；引擎在 `INFO['RENDER']` 里定。

### 7.4 模板可用变量

模板渲染时框架自动注入 `$config`（站点配置）、`$user`（已登录会员对象，未登录为空）、各业务列表变量等。具体取决于框架调用的入口。**首次写主题时强烈建议**先 fork 已有主题（`Magic` / `MountFuji`），看现成的 `.html` / `.php` 里都用了什么变量，再开始改造。

---

## 8. `plugins.json` 索引

### 8.1 生成方式

仓库根的 `plugins.json` 是**所有用户的"应用商店"数据来源**。两种生成方式（任选其一）：

```bash
# 推荐：扫描全部三个目录自动生成
node scripts/gen-index.js
```

或者**手动编辑** `plugins.json`，把你新插件的条目按字典序插进 `items` 数组。

### 8.2 字段全集

```json
{
  "key": "MyPlugin",
  "type": 0,
  "name": "我的插件",
  "version": "1.0.0",
  "author": "YourName",
  "description": "一句话介绍。",
  "web_site": "https://example.com",
  "icon": "plugins/MyPlugin/icon.png",
  "path": "plugins/MyPlugin",
  "tags": ["通知", "API"],
  "min_app_version": "3.4.9",
  "homepage": "https://github.com/NoDoorAction/Acg-Faka-Plugins/tree/main/plugins/MyPlugin",
  "download_url": null
}
```

| 字段              | 类型              | 必填 | 说明                                                                            |
| ----------------- | ----------------- | ---- | ------------------------------------------------------------------------------- |
| `key`             | `string`          | ✅   | 与目录名、`{Key}` 完全一致；仅 `[A-Za-z0-9_]`                                  |
| `type`            | `0` / `1` / `2`   | ✅   | 0=通用、1=支付、2=主题                                                          |
| `name`            | `string`          | ✅   | 显示名（与 `Info.php` 同步）                                                    |
| `version`         | `string`          | ✅   | 语义化版本（与 `Info.php` 同步）                                                |
| `author`          | `string`          | 否   | 作者                                                                            |
| `description`     | `string`          | 否   | 简介                                                                            |
| `web_site`        | `string`          | 否   | 作者主页 / 文档；`#` 视同空                                                     |
| `icon`            | `string`          | 否   | 仓库内图标相对路径（`{path}/icon.png` 等）                                       |
| `path`            | `string`          | ✅   | 仓库内目录路径，主程序按此抓取                                                  |
| `tags`            | `string[]`        | 否   | 标签数组，用于前端筛选                                                          |
| `min_app_version` | `string`          | 否   | 兼容的最低主程序版本                                                            |
| `homepage`        | `string`          | 否   | 介绍页（默认指向本仓库目录）                                                    |
| `download_url`    | `string \| null` | 否   | 给出后前端直接下整包 zip；适合超大插件 / 带 `Vendor/` 的                          |

### 8.3 一致性硬约束

| 必须三处同步      | 体现                                                                                     |
| ----------------- | ---------------------------------------------------------------------------------------- |
| **版本号**        | `plugins.json.items[].version` ≡ `Config/Info.php` 的 `VERSION` ≡ Theme `Config.php` 的 `INFO['VERSION']` |
| **名称 `key`**    | `plugins.json.items[].key` ≡ 仓库目录名 ≡ PHP 命名空间段                                  |
| **`type` 与 `path`** | `type=0 ↔ path=plugins/*` / `type=1 ↔ path=pays/*` / `type=2 ↔ path=themes/*`            |

> 跑 `node scripts/gen-index.js` 会自动维护以上一致性。手改之后**强烈建议**再跑一次脚本进行对账。

---

## 9. 安装 / 升级 / 卸载 流程（行为说明）

下面是主程序遇到这三种动作时的**精确顺序**——AI 写代码或回答用户问题时**以此为准**。

### 9.1 安装（`installPlugin`）

1. 校验目标目录不存在（避免重装）。
2. 从 `plugins.json` 找到 `key` + `type` 对应条目，按 `path` 通过 GitHub API 拉取**整个子目录**到本地：
   - `type=0` → `app/Plugin/{Key}/`
   - `type=1` → `app/Pay/{Key}/`
   - `type=2` → `app/View/User/Theme/{Key}/`
3. 校验入口配置文件存在（`Config/Info.php` 或 `Config.php`）；不在则回滚。
4. 如有 `install.sql`，把 `__PREFIX__` 替换为真实前缀后导入数据库。
5. 仅 `type=0`：触发 `#[Plugin(state: Plugin::INSTALL)]`。
6. 写下 `.faka-installed.json` 商店追踪标记。

### 9.2 启用（前台"启动"按钮）

1. 把 `Config/Config.php` 的 `STATUS` 改为 `1`。
2. 重新扫描 `Hook/*.php`，加密写入 `runtime/plugin/hook` 缓存。
3. 触发 `#[Plugin(state: Plugin::START)]`。
4. 清理 `runtime/plugin/plugin.cache`。

### 9.3 停用

1. `STATUS` 改为 `0`。
2. 触发 `#[Plugin(state: Plugin::STOP)]`。
3. 从 hook 缓存里删除该插件的注册项。

### 9.4 升级（`updatePlugin`）

1. 校验目录存在（必须先装过）。
2. 重新拉取仓库内对应目录，覆盖本地。**注意**：用户的 `Config/Config.php` 会被仓库版本覆盖——所以你的仓库版 `Config.php` **必须是空数组**，不要预填字段。
3. 如有 `update.sql`，执行。
4. `type=0`：触发 `#[Plugin(state: Plugin::UPGRADE)]`。
5. `type=2`：清空模板渲染缓存。

### 9.5 卸载

1. 删除对应目录（含全部用户数据 / 配置）。
2. **不触发** `Plugin::UNINSTALL`（该状态目前主要由插件内部主动调用 `runHookState` 使用；如需清表请在 `update.sql` 中显式 DROP，或主程序后续版本再补）。

---

## 10. 从零开始：三类模板（**直接复制即可跑通**）

### 10.1 通用插件模板（Hello World）

```
plugins/HelloWorld/
├── Config/
│   ├── Info.php
│   ├── Config.php
│   └── Submit.php
└── Hook/
    └── Main.php
```

**`Config/Info.php`**

```php
<?php
declare(strict_types=1);
use App\Consts\Plugin;

return [
    Plugin::NAME        => 'Hello World',
    Plugin::AUTHOR      => 'YourName',
    Plugin::WEB_SITE    => '#',
    Plugin::DESCRIPTION => '在前台底部插一段问候语。',
    Plugin::VERSION     => '1.0.0',
];
```

**`Config/Config.php`**

```php
<?php
declare(strict_types=1);
return [];
```

**`Config/Submit.php`**

```php
<?php
declare(strict_types=1);
return [
    [
        "title"       => "问候语",
        "name"        => "greeting",
        "type"        => "input",
        "placeholder" => "Hello, world!",
    ],
];
```

**`Hook/Main.php`**

```php
<?php
declare(strict_types=1);

namespace App\Plugin\HelloWorld\Hook;

use App\Controller\Base\View\UserPlugin;
use Kernel\Annotation\Hook;
use Kernel\Annotation\Plugin;
use Kernel\Exception\JSONException;

class Main extends UserPlugin
{
    #[Plugin(state: Plugin::START)]
    public function onStart(): void
    {
        $cfg = getPluginConfig('HelloWorld');
        if (empty($cfg['greeting'])) {
            throw new JSONException("启用前请先填写问候语。");
        }
    }

    #[Hook(point: \App\Consts\Hook::USER_VIEW_INDEX_FOOTER)]
    public function footer(): void
    {
        $cfg = getPluginConfig('HelloWorld');
        echo '<div style="text-align:center;padding:10px;">'
             . htmlspecialchars((string)$cfg['greeting'], ENT_QUOTES, 'UTF-8')
             . '</div>';
    }
}
```

### 10.2 支付插件模板（一个最简通道）

```
pays/SamplePay/
├── Config/
│   ├── Info.php
│   ├── Config.php
│   └── Submit.php
└── Impl/
    ├── Pay.php
    └── Signature.php
```

**`Config/Info.php`**

```php
<?php
declare(strict_types=1);

return [
    'version'     => '1.0.0',
    'name'        => '示例支付',
    'author'      => 'YourName',
    'website'     => '#',
    'description' => '一个最简支付通道示例。',
    'options'     => [
        1 => '默认通道',
    ],
    'callback' => [
        \App\Consts\Pay::IS_SIGN            => true,
        \App\Consts\Pay::IS_STATUS          => true,
        \App\Consts\Pay::FIELD_STATUS_KEY   => 'status',
        \App\Consts\Pay::FIELD_STATUS_VALUE => 'SUCCESS',
        \App\Consts\Pay::FIELD_ORDER_KEY    => 'out_trade_no',
        \App\Consts\Pay::FIELD_AMOUNT_KEY   => 'amount',
        \App\Consts\Pay::FIELD_RESPONSE     => 'success',
    ],
];
```

**`Config/Config.php`**

```php
<?php
declare(strict_types=1);
return [];
```

**`Config/Submit.php`**

```php
<?php
declare(strict_types=1);
return [
    ["title" => "商户号", "name" => "mch_id", "type" => "input",    "placeholder" => "商户号"],
    ["title" => "密钥",   "name" => "key",    "type" => "textarea", "placeholder" => "API Key"],
    ["title" => "网关",   "name" => "url",    "type" => "input",    "placeholder" => "https://api.example.com"],
];
```

**`Impl/Pay.php`**

```php
<?php
declare(strict_types=1);

namespace App\Pay\SamplePay\Impl;

use App\Entity\PayEntity;
use App\Pay\Base;
use Kernel\Exception\JSONException;

class Pay extends Base implements \App\Pay\Pay
{
    public function trade(): PayEntity
    {
        if (empty($this->config['mch_id']) || empty($this->config['key']) || empty($this->config['url'])) {
            throw new JSONException("支付通道未配置完成");
        }

        $param = [
            'mch_id'       => $this->config['mch_id'],
            'out_trade_no' => $this->tradeNo,
            'amount'       => $this->amount,
            'notify_url'   => $this->callbackUrl,
            'return_url'   => $this->returnUrl,
            'channel'      => $this->code,
        ];
        $param['sign'] = Signature::sign($param, $this->config['key']);

        // 这里省略实际 HTTP 请求；假装通道返回了支付 URL
        $payUrl = rtrim($this->config['url'], '/') . '/pay?token=' . md5($this->tradeNo);

        $entity = new PayEntity();
        $entity->setUrl($payUrl);
        $entity->setType(\App\Pay\Pay::TYPE_REDIRECT);
        return $entity;
    }
}
```

**`Impl/Signature.php`**

```php
<?php
declare(strict_types=1);

namespace App\Pay\SamplePay\Impl;

class Signature implements \App\Pay\Signature
{
    public function verification(array $data, array $config): bool
    {
        $sign = (string)($data['sign'] ?? '');
        return $sign !== '' && hash_equals(self::sign($data, $config['key']), $sign);
    }

    public static function sign(array $data, string $key): string
    {
        unset($data['sign'], $data['sign_type']);
        ksort($data);
        $str = '';
        foreach ($data as $k => $v) {
            if (is_array($v) || $v === '' || $v === null) continue;
            $str .= "{$k}={$v}&";
        }
        return md5(trim($str, '&') . $key);
    }
}
```

### 10.3 主题模板（最简骨架）

```
themes/SampleTheme/
├── Config.php
└── Index.html
```

**`Config.php`**

```php
<?php
declare(strict_types=1);

namespace App\View\User\Theme\SampleTheme;

use App\Consts\Render;

interface Config
{
    const INFO = [
        "NAME"        => "示例主题",
        "AUTHOR"      => "YourName",
        "VERSION"     => "1.0.0",
        "WEB_SITE"    => "#",
        "DESCRIPTION" => "一个最简主题骨架。",
        "RENDER"      => Render::ENGINE_SMARTY,
    ];

    const THEME = [
        "INDEX" => "Index.html",
        "ITEM"  => "Index.html",
    ];
}
```

**`Index.html`**

```html
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title>{$config.shop_name|default:"My Shop"}</title>
</head>
<body>
    <h1>{$config.shop_name|default:"我的小店"}</h1>
    #{foreach from=$categories item=cat}
        <h2>{$cat.name}</h2>
        <ul>
        #{foreach from=$cat.commodity item=item}
            <li>{$item.name} - ¥{$item.amount}</li>
        #{/foreach}
        </ul>
    #{/foreach}
</body>
</html>
```

---

## 11. 给 AI 助手的硬性约束（**必读**）

> 本仓库的代码会被用户**直接安装到生产服务器并执行**。AI 在生成 / 修改插件代码时，遵守下面这些规则可以显著减少返工。

### 11.1 必须做

1. **所有常量引用 `App\Consts\*`**：hook 点位、Plugin 元信息字段、Pay 回调字段，**禁止**写裸数字 / 裸字符串。
2. **命名空间严格对齐目录**：`plugins/Foo/Hook/Bar.php` ⇄ `namespace App\Plugin\Foo\Hook;` 且类名 `Bar`。改一处必须改另一处。
3. **类继承基类**（§2.3 四选一）。
4. **`Config/Config.php` 永远只 `return []`**——把字段定义放 `Submit.php`，让框架自己 merge 用户值。
5. **修改库表只走 `install.sql` / `update.sql`**，并且表名前缀用 `__PREFIX__`，前缀紧跟 `plugin_`（如 `__PREFIX__plugin_yourname_xxx`）。
6. **支付插件的 `Info.php` 字段 key 全小写**（`name` / `version` / `author` / `website` / `description` / `options` / `callback`），通用插件的 `Info.php` 字段 key 用 `Plugin::*` **大写**常量。**这是两者最容易写错的地方**。
7. **HTTP 出网用 `$this->http()` 或 `App\Util\Http`**（Guzzle 封装），不要直接 `file_get_contents` 远程 URL 也不要 `curl_init`。
8. **用户输入做转义**：模板里输出非可信变量加 `|escape`（Smarty）或 `htmlspecialchars`（PHP）。

### 11.2 禁止做

1. **不要**写运行时建表 / 改字段 / 删字段。
2. **不要**用 `eval` / `assert($code)` / `create_function` / `system()` / `exec()` / `shell_exec()` / `passthru()`。
3. **不要**远程加载并执行代码（`require 'https://...'`、`include $remoteUrl` 之类）。
4. **不要**发"数据上报"请求（向插件作者自己的服务器回传站点信息），除非该功能是插件的核心目的且已在 `description` 中明示。
5. **不要**在 `Config/Config.php` 里预写用户密钥 / token / API Key 的真实值。
6. **不要**在 hook 里 `die()` / `exit()`。需要中断业务请 `throw new \Kernel\Exception\JSONException("...")`。
7. **不要**自定义新的 hook 点位编号。点位是主程序的"公开 API 表面"，要新增请去主仓库提 issue/PR。
8. **不要**绕过 `App\Consts\Pay::*` 回调字段配置，自己在 `Signature::verification()` 里改订单状态。

### 11.3 不确定时怎么办

- 不确定 hook 点位的传参签名 → **直接打开 `kernel/Util/Plugin.php`** 看 `hook()` 函数实际怎么调（它就是 hook 派发器），或在主程序代码里搜 `Hook::POINT_NAME` 找到调用位置。
- 不确定字段类型支持哪些值 → 看 `assets/common/js/component/form.js`。
- 不确定支付通道某个值 → 看现有的 `pays/Epay/` / `pays/Alipay/` / `pays/Codepay/` 真实实现。
- **以上都查不到 → 向用户提问。不要靠 LLM 训练数据里"异次元发卡"的旧知识生成代码——本项目和那个项目早就分叉了。**

---

## 12. 提 PR 流程

1. Fork 本仓库到自己的 GitHub。
2. 把你的插件 / 支付 / 主题按 §3 / §6 / §7 的目录约定放进对应子目录。
3. **本地跑一遍** `node scripts/gen-index.js` 更新 `plugins.json`。
4. 提交并发 PR，PR 描述里写：
   - 这是什么类型的插件（type=0/1/2）。
   - 解决了什么问题 / 适用什么场景。
   - 用到了哪些 hook 点 / 调用了哪些外部接口。
   - 是否有 `install.sql` / `update.sql`，做了什么 schema 改动。
   - 是否包含 `Vendor/` 第三方依赖。
5. 维护者审核合并后，**全网用户**的"应用商店"立刻能看到。

---

## 13. 版本兼容

| 主程序版本   | 本规范状态                                                              |
| ------------ | ----------------------------------------------------------------------- |
| `< 3.5.1`    | 应用商店仍走异次元官方，请按官方旧文档；本规范的"安装"流程**不适用**    |
| `≥ 3.5.1`    | 完全适用                                                                |
| `≥ 3.6.x` 预计 | hook 点位可能新增（不删除）、`Info.php` 字段保持向后兼容                 |

修订记录与本仓库 git 历史保持一致；如有 breaking change，会单独在仓库 README 顶部标注。
