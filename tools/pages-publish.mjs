#!/usr/bin/env node
/**
 * pages-publish — публикует статическую демо-копию на GitHub Pages.
 *
 * Берёт `demo-static/` (сборка `tools/export-static.php`), копирует в `.pages/`,
 * меняет боевой домен на адрес Pages, кладёт `.nojekyll` и — с флагом
 * `--publish` — создаёт репозиторий `<owner>.github.io`, пушит туда содержимое
 * и включает Pages.
 *
 *   node tools/pages-publish.mjs
 *   $env:GITHUB_TOKEN = "<токен>"
 *   node tools/pages-publish.mjs --publish
 *
 * Зачем отдельный репозиторий: страницы ссылаются на корень (`/services/`,
 * `/themes/…`, `/sw.js`), поэтому сайт проекта (`…github.io/OctoberCMS/`) их
 * сломал бы. Репозиторий `<owner>.github.io` отдаётся из корня — переписывать
 * ссылки не нужно, работает и service worker.
 *
 * Токен нужен только для GitHub API (создать репозиторий, включить Pages);
 * пуш идёт через обычный git-credential.
 */

import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const source = path.join(root, 'demo-static');
const out = path.join(root, '.pages');

const owner = process.env.PAGES_OWNER || 'elips0675-web';
const repo = process.env.PAGES_REPO || `${owner}.github.io`;
const origin = `https://${repo}`;
const production = 'https://octobercms.vercel.app';
const publish = process.argv.includes('--publish');

if (!fs.existsSync(path.join(source, 'index.html'))) {
  console.error('нет demo-static/index.html: сначала php tools/export-static.php');
  process.exit(2);
}

fs.rmSync(out, { recursive: true, force: true });
fs.cpSync(source, out, { recursive: true });

const textExt = new Set(['.html', '.xml', '.json', '.webmanifest', '.txt', '.js', '.css']);
let rewritten = 0;
let touched = 0;

function walk(dir, acc = []) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) walk(full, acc);
    else acc.push(full);
  }
  return acc;
}

const files = walk(out);
for (const file of files) {
  if (!textExt.has(path.extname(file).toLowerCase())) continue;
  const body = fs.readFileSync(file, 'utf8');
  if (!body.includes(production)) continue;
  const next = body.split(production).join(origin);
  rewritten += body.split(production).length - 1;
  fs.writeFileSync(file, next, 'utf8');
  touched += 1;
}
fs.writeFileSync(path.join(out, '.nojekyll'), '', 'utf8');

const left = [];
for (const file of files) {
  if (!textExt.has(path.extname(file).toLowerCase())) continue;
  const body = fs.readFileSync(file, 'utf8');
  if (body.includes(production)) left.push(path.relative(out, file));
}
const stat = fs.statSync(path.join(out, 'index.html'));
const size = files.reduce((sum, file) => sum + fs.statSync(file).size, 0);

console.log(`копия: ${files.length} файлов, ${(size / 1048576).toFixed(1)} МБ, index.html ${stat.size} байт`);
console.log(`домен: ${production} → ${origin} (${rewritten} замен в ${touched} файлах)`);
if (left.length) {
  console.error('✖ остались ссылки на боевой домен: ' + left.slice(0, 5).join(', '));
  process.exit(1);
}
console.log('✔ ссылок на боевой домен не осталось, .nojekyll на месте');

if (!publish) {
  console.log(`готово к публикации: node tools/pages-publish.mjs --publish (репозиторий ${owner}/${repo})`);
  process.exit(0);
}

const token = process.env.GITHUB_TOKEN;
if (!token) {
  console.error('нет GITHUB_TOKEN: он нужен для GitHub API (создать репозиторий и включить Pages)');
  process.exit(2);
}

const api = 'https://api.github.com';
async function call(method, url, body) {
  const res = await fetch(api + url, {
    method,
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: 'application/vnd.github+json',
      'User-Agent': 'octobercms-pages-publish',
      'Content-Type': 'application/json',
    },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const text = await res.text();
  let json = null;
  try {
    json = text ? JSON.parse(text) : null;
  } catch {
    json = null;
  }
  return { status: res.status, ok: res.ok, json, text };
}

const probe = await call('GET', `/repos/${owner}/${repo}`);
if (probe.status === 404) {
  const created = await call('POST', '/user/repos', {
    name: repo,
    private: false,
    description: 'Публичное демо шаблона ELV для October CMS (статическая сборка сайта)',
    homepage: origin,
    has_issues: false,
    has_wiki: false,
    has_projects: false,
  });
  if (!created.ok) {
    console.error(`✖ создать репозиторий: HTTP ${created.status} — ${created.json?.message || created.text.slice(0, 160)}`);
    console.error('  токену нужен scope «repo» (классический PAT) либо право Administration: write');
    process.exit(1);
  }
  console.log(`✔ репозиторий создан: ${owner}/${repo}`);
} else if (probe.ok) {
  console.log(`… репозиторий уже есть: ${owner}/${repo}`);
} else {
  console.error(`✖ проверка репозитория: HTTP ${probe.status}`);
  process.exit(1);
}

const git = (args) => execFileSync('git', ['-C', out, ...args], { stdio: 'pipe' }).toString().trim();

if (!fs.existsSync(path.join(out, '.git'))) {
  git(['init', '-b', 'main']);
  git(['remote', 'add', 'origin', `https://github.com/${owner}/${repo}.git`]);
} else {
  try {
    git(['remote', 'get-url', 'origin']);
  } catch {
    git(['remote', 'add', 'origin', `https://github.com/${owner}/${repo}.git`]);
  }
}
git(['add', '-A']);
const identity = ['-c', `user.name=${owner}`, '-c', `user.email=${owner}@users.noreply.github.com`];
try {
  git([...identity, 'commit', '-m', `pages: демо ${new Date().toISOString().slice(0, 10)}`]);
} catch (error) {
  const rest = String(error.stderr || '').trim();
  if (!rest.includes('nothing to commit')) {
    console.error('✖ коммит не создан: ' + rest.split('\n').slice(0, 3).join(' | '));
    process.exit(1);
  }
  console.log('… новых изменений нет, пушу как есть');
}
try {
  git(['rev-parse', '--verify', 'HEAD']);
} catch {
  console.error('✖ в .pages нет ни одного коммита');
  process.exit(1);
}
git(['push', '-f', 'origin', 'main']);
console.log('✔ содержимое отправлено в main');

const pages = await call('GET', `/repos/${owner}/${repo}/pages`);
const source_ = { source: { branch: 'main', path: '/' } };
if (pages.status === 404) {
  const enabled = await call('POST', `/repos/${owner}/${repo}/pages`, source_);
  if (enabled.ok) console.log('✔ GitHub Pages включён');
  else if (enabled.status === 409) console.log('… Pages уже включён');
  else {
    console.error(`✖ включить Pages: HTTP ${enabled.status} — ${enabled.json?.message || ''}`);
    process.exit(1);
  }
} else {
  const updated = await call('PUT', `/repos/${owner}/${repo}/pages`, source_);
  console.log(updated.ok ? '✔ Pages обновлён (ветка main)' : `… Pages уже настроен (HTTP ${updated.status})`);
}

let last = '';
for (let attempt = 1; attempt <= 30; attempt += 1) {
  await new Promise((resolve) => setTimeout(resolve, 10000));
  const res = await fetch(`${origin}/services/`).catch(() => null);
  const code = res ? res.status : 0;
  last = `попытка ${attempt}: ${origin}/services/ → ${code}`;
  console.log(last);
  if (code === 200) break;
}

if (!last.endsWith('200')) {
  console.error('✖ страница не открылась за 5 минут: проверьте Actions → pages build и настройки Pages');
  process.exit(1);
}
console.log(`✔ демо открывается: ${origin}/`);
