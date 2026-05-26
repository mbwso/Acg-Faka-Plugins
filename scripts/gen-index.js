#!/usr/bin/env node
/**
 * 扫描 plugins/ pays/ themes/ 三个目录，生成统一的 plugins.json。
 *
 *   plugins/  → type=0   通用插件   入口: <plugin>/Config/Info.php   (return array)
 *   pays/     → type=1   支付插件   入口: <plugin>/Config/Info.php   (return array)
 *   themes/   → type=2   主题模板   入口: <plugin>/Config.php        (interface Config 内 const INFO = [...])
 *
 * 用法：
 *   node scripts/gen-index.js                 # ROOT 默认为本仓库根
 *   node scripts/gen-index.js D:/path/to/repo
 */
const fs = require('fs');
const path = require('path');

const ROOT = process.argv[2] || path.resolve(__dirname, '..');

const REPO_OWNER  = 'NoDoorAction';
const REPO_NAME   = 'Acg-Faka-Plugins';
const REPO_BRANCH = 'main';
const MIN_APP_VER = '3.4.9';

/**
 * 从 PHP 源码里抠出 KEY 对应的字符串值。覆盖三种常见写法：
 *   "KEY"      => "..."
 *   'KEY'      => '...'
 *   Plugin::KEY => "..."
 */
function extractField(content, key) {
    const patterns = [
        new RegExp(`Plugin::${key}\\s*=>\\s*['"\`]([^'"\`]*)['"\`]`, 'i'),
        new RegExp(`["']${key}["']\\s*=>\\s*['"\`]([^'"\`]*)['"\`]`, 'i'),
    ];
    for (const re of patterns) {
        const m = content.match(re);
        if (m) {
            const v = m[1].trim();
            // '#' 在原作模板里是"留空占位符"，作者/官网/描述里出现 # 直接当空处理
            return v === '#' ? '' : v;
        }
    }
    return '';
}

/** 在插件目录下寻找 icon 文件，返回相对仓库根的路径，找不到返回 '' */
function findIcon(absDir, repoRel) {
    const candidates = ['icon.png', 'icon.jpg', 'icon.jpeg', 'icon.webp', 'icon.svg', 'Config/icon.png', 'Assets/icon.png'];
    for (const c of candidates) {
        if (fs.existsSync(path.join(absDir, c))) {
            return `${repoRel}/${c}`;
        }
    }
    return '';
}

function listDirs(absDir) {
    if (!fs.existsSync(absDir)) return [];
    return fs.readdirSync(absDir).filter(d => {
        const full = path.join(absDir, d);
        return fs.statSync(full).isDirectory() && !d.startsWith('.');
    });
}

const items = [];

/* ---------- type=0  plugins/ ---------- */
for (const dir of listDirs(path.join(ROOT, 'plugins'))) {
    const info = path.join(ROOT, 'plugins', dir, 'Config', 'Info.php');
    if (!fs.existsSync(info)) {
        console.warn(`[plugins] SKIP ${dir}: Config/Info.php 不存在`);
        continue;
    }
    const content = fs.readFileSync(info, 'utf8');
    items.push({
        key: dir,
        type: 0,
        name: extractField(content, 'NAME') || dir,
        version: extractField(content, 'VERSION') || '1.0.0',
        author: extractField(content, 'AUTHOR') || '',
        description: extractField(content, 'DESCRIPTION') || '',
        web_site: (extractField(content, 'WEB_SITE') === '#') ? '' : extractField(content, 'WEB_SITE'),
        icon: findIcon(path.join(ROOT, 'plugins', dir), `plugins/${dir}`),
        path: `plugins/${dir}`,
        tags: [],
        min_app_version: MIN_APP_VER,
        homepage: `https://github.com/${REPO_OWNER}/${REPO_NAME}/tree/${REPO_BRANCH}/plugins/${dir}`,
        download_url: null,
    });
}

/* ---------- type=1  pays/ ---------- */
for (const dir of listDirs(path.join(ROOT, 'pays'))) {
    const info = path.join(ROOT, 'pays', dir, 'Config', 'Info.php');
    if (!fs.existsSync(info)) {
        console.warn(`[pays] SKIP ${dir}: Config/Info.php 不存在`);
        continue;
    }
    const content = fs.readFileSync(info, 'utf8');
    items.push({
        key: dir,
        type: 1,
        // 支付插件 Info.php 用小写 key，跟通用插件不一样
        name: extractField(content, 'name') || dir,
        version: extractField(content, 'version') || '1.0.0',
        author: extractField(content, 'author') || '',
        description: extractField(content, 'description') || '',
        web_site: extractField(content, 'website') || '',
        icon: findIcon(path.join(ROOT, 'pays', dir), `pays/${dir}`),
        path: `pays/${dir}`,
        tags: [],
        min_app_version: MIN_APP_VER,
        homepage: `https://github.com/${REPO_OWNER}/${REPO_NAME}/tree/${REPO_BRANCH}/pays/${dir}`,
        download_url: null,
    });
}

/* ---------- type=2  themes/ ---------- */
for (const dir of listDirs(path.join(ROOT, 'themes'))) {
    const config = path.join(ROOT, 'themes', dir, 'Config.php');
    if (!fs.existsSync(config)) {
        console.warn(`[themes] SKIP ${dir}: Config.php 不存在`);
        continue;
    }
    const content = fs.readFileSync(config, 'utf8');
    items.push({
        key: dir,
        type: 2,
        name: extractField(content, 'NAME') || dir,
        version: extractField(content, 'VERSION') || '1.0.0',
        author: extractField(content, 'AUTHOR') || '',
        description: extractField(content, 'DESCRIPTION') || '',
        web_site: (extractField(content, 'WEB_SITE') === '#') ? '' : extractField(content, 'WEB_SITE'),
        icon: findIcon(path.join(ROOT, 'themes', dir), `themes/${dir}`),
        path: `themes/${dir}`,
        tags: [],
        min_app_version: MIN_APP_VER,
        homepage: `https://github.com/${REPO_OWNER}/${REPO_NAME}/tree/${REPO_BRANCH}/themes/${dir}`,
        download_url: null,
    });
}

// 先按 type 再按 key 排序，方便人肉 diff
items.sort((a, b) => (a.type - b.type) || a.key.localeCompare(b.key));

const out = {
    schema_version: 1,
    updated_at: new Date().toISOString().slice(0, 10),
    notice: '本仓库为 Acg-Faka-Local 的 GitHub 插件市场，前端会读取本 JSON 渲染插件列表，安装时根据 path 字段从仓库子目录拉取文件。type: 0=通用插件 1=支付插件 2=主题模板。',
    items: items,
};

const target = path.join(ROOT, 'plugins.json');
fs.writeFileSync(target, JSON.stringify(out, null, 2), 'utf8');

const byType = {0: 0, 1: 0, 2: 0};
items.forEach(i => byType[i.type]++);
console.log(`written ${items.length} items to ${target}`);
console.log(`  type=0 通用插件: ${byType[0]}`);
console.log(`  type=1 支付插件: ${byType[1]}`);
console.log(`  type=2 主题模板: ${byType[2]}`);
