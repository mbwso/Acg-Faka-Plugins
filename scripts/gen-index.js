#!/usr/bin/env node
// 扫描 plugins/ 目录下每个插件的 Config/Info.php，生成 plugins.json
const fs = require('fs');
const path = require('path');

const ROOT = process.argv[2] || 'D:/APPs/Acg-Faka-Plugins';
const PLUGINS_DIR = path.join(ROOT, 'plugins');

function extractField(content, key) {
  // 匹配 Plugin::KEY => 'value' 或 "KEY" => "value"
  const patterns = [
    new RegExp(`Plugin::${key}\\s*=>\\s*['"\`]([^'"\`]*)['"\`]`, 'i'),
    new RegExp(`["']${key}["']\\s*=>\\s*['"\`]([^'"\`]*)['"\`]`, 'i'),
  ];
  for (const re of patterns) {
    const m = content.match(re);
    if (m) return m[1];
  }
  return '';
}

const items = [];
const dirs = fs.readdirSync(PLUGINS_DIR).filter(d => {
  const full = path.join(PLUGINS_DIR, d);
  return fs.statSync(full).isDirectory();
});

for (const dir of dirs) {
  const info = path.join(PLUGINS_DIR, dir, 'Config', 'Info.php');
  if (!fs.existsSync(info)) {
    console.warn(`SKIP ${dir}: Config/Info.php 不存在`);
    continue;
  }
  const content = fs.readFileSync(info, 'utf8');
  const name = extractField(content, 'NAME');
  const author = extractField(content, 'AUTHOR');
  const version = extractField(content, 'VERSION');
  const description = extractField(content, 'DESCRIPTION');
  const webSite = extractField(content, 'WEB_SITE');

  // 看看插件目录里有没有 icon
  const iconCandidates = ['icon.png', 'icon.jpg', 'icon.webp', 'Config/icon.png'];
  let icon = '';
  for (const c of iconCandidates) {
    if (fs.existsSync(path.join(PLUGINS_DIR, dir, c))) {
      icon = `plugins/${dir}/${c}`;
      break;
    }
  }

  items.push({
    key: dir,
    type: 0,
    name: name || dir,
    version: version || '1.0.0',
    author: author || '',
    description: description || '',
    web_site: webSite === '#' ? '' : webSite,
    icon: icon,
    path: `plugins/${dir}`,
    tags: [],
    min_app_version: '3.4.9',
    homepage: `https://github.com/NoDoorAction/Acg-Faka-Plugins/tree/main/plugins/${dir}`,
    download_url: null
  });
}

items.sort((a, b) => a.key.localeCompare(b.key));

const out = {
  schema_version: 1,
  updated_at: new Date().toISOString().slice(0, 10),
  notice: '本仓库为 Acg-Faka-Local 的 GitHub 插件市场，前端会读取本 JSON 渲染插件列表，安装时根据 path 字段从仓库子目录拉取文件。',
  items: items
};

const target = path.join(ROOT, 'plugins.json');
fs.writeFileSync(target, JSON.stringify(out, null, 2), 'utf8');
console.log(`written ${items.length} plugins to ${target}`);
