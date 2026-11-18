#!/usr/bin/env node
/**
 * check-static-demo — проверка статической демо-копии для Vercel.
 *
 * Статика собирается `tools/export-static.php` с живого October. Гейт отвечает
 * на вопросы, которые видит посетитель: страницы на месте, внутренние ссылки
 * не ведут в пустоту, локальный адрес не утёк, демо-плашка и отключение форм
 * есть на каждой странице, canonical и sitemap смотрят на боевой домен.
 *
 *   node tools/check-static-demo.mjs [--origin=https://octobercms.vercel.app]
 *
 * Требует собранный `demo-static/`.
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const demo = path.join(root, 'demo-static');
const originArg = process.argv.find((arg) => arg.startsWith('--origin='));
const origin = (originArg ? originArg.slice('--origin='.length) : 'https://octobercms.vercel.app').replace(/\/$/, '');

const failures = [];
const notes = [];

const walk = (dir) => {
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  const files = [];

  for (const entry of entries) {
    const full = path.join(dir, entry.name);

    if (entry.isDirectory()) {
      files.push(...walk(full));
    } else if (entry.isFile()) {
      files.push(full);
    }
  }

  return files;
};

if (!fs.existsSync(demo) || !fs.statSync(demo).isDirectory()) {
  console.error('FAIL: нет каталога demo-static — сначала php tools/export-static.php');
  process.exit(1);
}

const files = walk(demo);
const htmlFiles = files.filter((file) => file.endsWith('.html'));

if (htmlFiles.length < 110) {
  failures.push(`страниц ${htmlFiles.length}, ожидалось не меньше 110`);
}

const relative = (file) => path.relative(demo, file).split(path.sep).join('/');

const resolveTarget = (pathname) => {
  const clean = decodeURIComponent(pathname.split('#')[0].split('?')[0]);

  if (clean === '' || clean === '/') {
    return path.join(demo, 'index.html');
  }

  const base = path.join(demo, clean.replace(/^\//, ''));

  if (path.extname(clean)) {
    return base;
  }

  return path.join(base, 'index.html');
};

const isExternal = (url) => /^(?:[a-z][a-z0-9+.-]*:|\/\/)/i.test(url);

let links = 0;
let assets = 0;
let cssLinks = 0;

const systemPages = new Set(['offline.html', 'themes/elv/assets/pwa/offline.html']);

for (const file of htmlFiles) {
  const html = fs.readFileSync(file, 'utf8');
  const name = relative(file);
  const system = systemPages.has(name);

  if (!system && !html.includes('id="elv-demo-bar"')) {
    failures.push(`${name}: нет плашки демо-версии`);
  }

  if (!system && !html.includes('ELV_STATIC_DEMO')) {
    failures.push(`${name}: нет скрипта отключения форм`);
  }

  if (/127\.0\.0\.1|localhost:8080/i.test(html)) {
    failures.push(`${name}: остался локальный адрес`);
  }

  if (/data-elv-css="/i.test(html)) {
    failures.push(`${name}: инлайн CSS не вынесен в файл`);
  }

  const canonical = /<link rel="canonical" href="([^"]+)"/i.exec(html);

  if (!system && !canonical) {
    failures.push(`${name}: нет canonical`);
  } else if (canonical && !canonical[1].startsWith(origin + '/')) {
    failures.push(`${name}: canonical ${canonical[1]} не на ${origin}`);
  }

  for (const match of html.matchAll(/\b(?:href|src|poster)="([^"]+)"/gi)) {
    const url = match[1].trim();

    if (url === '' || url.startsWith('#') || isExternal(url) || url.startsWith('data:')) {
      continue;
    }

    if (url.startsWith('/themes/') || /\.(?:css|js|svg|png|jpe?g|webp|avif|ico|woff2?)$/i.test(url)) {
      assets++;
    } else {
      links++;
    }

    if (url.startsWith('/themes/elv/assets/css/static-')) {
      cssLinks++;
    }

    const target = resolveTarget(url);

    if (!fs.existsSync(target)) {
      failures.push(`${name}: ссылка ${url} не имеет файла (${relative(target)})`);
    }
  }
}

if (cssLinks === 0) {
  failures.push('нет ссылок на вынесенный CSS — вёрстка будет без стилей');
}

for (const name of ['sitemap.xml', 'robots.txt', 'rss.xml', 'blog/rss.xml', 'manifest.webmanifest', 'sw.js', 'offline.html', '404.html']) {
  if (!fs.existsSync(path.join(demo, name))) {
    failures.push(`нет ${name}`);
  }
}

for (const file of files.filter((item) => /\.(?:xml|txt|json|js)$/.test(item))) {
  const body = fs.readFileSync(file, 'utf8');

  if (/127\.0\.0\.1|localhost:8080/i.test(body)) {
    failures.push(`${relative(file)}: остался локальный адрес`);
  }
}

const sitemap = fs.readFileSync(path.join(demo, 'sitemap.xml'), 'utf8');
const locs = [...sitemap.matchAll(/<loc>([^<]+)<\/loc>/g)].map((match) => match[1]);

if (locs.length < 100) {
  failures.push(`sitemap: адресов ${locs.length}, ожидалось не меньше 100`);
}

for (const loc of locs) {
  if (!loc.startsWith(origin + '/')) {
    failures.push(`sitemap: ${loc} не на ${origin}`);
    break;
  }
}

const robots = fs.readFileSync(path.join(demo, 'robots.txt'), 'utf8');

if (!robots.includes(origin + '/sitemap.xml')) {
  failures.push('robots.txt: нет Sitemap на боевой домен');
}

const bytes = files.reduce((sum, file) => sum + fs.statSync(file).size, 0);
notes.push(`страниц: ${htmlFiles.length}, файлов: ${files.length}, объём: ${(bytes / 1048576).toFixed(1)} МБ`);
notes.push(`внутренних ссылок: ${links}, ассетов: ${assets}, ссылок на вынесенный CSS: ${cssLinks}`);

console.log(notes.join('\n'));

if (failures.length) {
  console.error('FAIL: ' + failures.length + ' проблем(ы)');
  for (const failure of failures.slice(0, 40)) {
    console.error('  - ' + failure);
  }
  if (failures.length > 40) {
    console.error('  ... ещё ' + (failures.length - 40));
  }
  process.exit(1);
}

console.log('OK: статическая демо-копия целая, форм и локальных адресов нет');
