# Acg-Faka-Plugins

[Acg-Faka-Local](https://github.com/NoDoorAction/Acg-Faka-Local) 的 GitHub 插件市场。

## 这是什么

主站 Acg-Faka-Local 自 **3.5.1** 起，移除了对异次元应用商店的全部依赖。插件列表、安装、升级、提交完全走 GitHub：

- 后台 → "应用商店" 直接读取本仓库根目录的 [`plugins.json`](./plugins.json)
- 用户点"安装"时，后端从仓库对应子目录拉文件、解压到 `app/Plugin/{key}/`、跑 `install.sql`、触发 `INSTALL` 钩子
- 想公开发布插件 / 修 bug / 改文案 = **在本仓库提 PR**

> 📘 写插件 / 支付 / 主题前请务必先读：[**插件编写规范 PLUGIN_SPEC.md**](./PLUGIN_SPEC.md) ——
> 同时面向人和 AI，覆盖通用插件 / 支付插件 / 主题模板**全部**字段、目录、Hook 点位、生命周期、可直接复制的模板。

## 目录约定

```
plugins.json            ← 索引文件（自动生成 / 手维护）
plugins/                ← type=0 通用插件
  GoTop/
    Config/Info.php     ← 必需，定义 NAME / AUTHOR / VERSION / DESCRIPTION
    Config/Config.php   ← 可选，运行时配置（含 STATUS 等）
    Hook/               ← 钩子代码（如有）
    View/               ← 模板文件（如有）
    install.sql         ← 可选，安装时执行
    update.sql          ← 可选，升级时执行
pays/                   ← type=1 支付插件（结构同上，落到 app/Pay/）
themes/                 ← type=2 站点模板（落到 app/View/User/Theme/）
```

## 提交一个新插件（PR 流程）

1. fork 本仓库
2. 按目录约定把你的插件放到 `plugins/<你的插件Key>/`
3. 在仓库根跑 `node scripts/gen-index.js` 重新生成 `plugins.json`（也可手动编辑该 JSON 添加你的条目）
4. 提交 PR，说明插件用途与测试方式
5. 我们审核合并后，所有用户的 "应用商店" 页面立即可以看到并安装

## plugins.json 字段说明

| 字段 | 类型 | 必填 | 说明 |
|---|---|---|---|
| `key` | string | 是 | 插件唯一标识；安装时作为目录名，必须只含字母、数字、下划线 |
| `type` | int | 是 | 0=通用插件，1=支付插件，2=站点模板 |
| `name` | string | 是 | 显示名 |
| `version` | string | 是 | 语义化版本号，与 `Config/Info.php` 的 `VERSION` 字段保持一致 |
| `author` | string | 否 | 作者 |
| `description` | string | 否 | 简介 |
| `web_site` | string | 否 | 作者主页 / 文档链接 |
| `icon` | string | 否 | 仓库内图标相对路径（如 `plugins/GoTop/icon.png`） |
| `path` | string | 是 | 仓库内插件目录路径，安装时按此抓取 |
| `tags` | string[] | 否 | 标签数组，用于前端筛选 |
| `min_app_version` | string | 否 | 兼容的最低 Acg-Faka-Local 版本 |
| `homepage` | string | 否 | 插件介绍页（默认指向 GitHub 仓库目录） |
| `download_url` | string\|null | 否 | 若提供，前端会直接下整包 zip（适合大插件 / 带 vendor 的） |

## 安全声明

⚠ **本仓库的插件代码会被用户直接安装并运行在他们的服务器上。**

每一笔 PR 都经过人工审核，但**不能 100% 保证插件没问题**。你应当：

- PR 提交时附上插件源码的简要说明（做什么、用到哪些权限）
- 不要打包带后门 / 数据上报 / 远程加载的代码
- 数据库变更必须放在 `install.sql` / `update.sql`，不允许在运行时硬改 schema

## License

[MIT](./LICENSE)
