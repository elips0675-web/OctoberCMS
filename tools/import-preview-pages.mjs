#!/usr/bin/env node
/**
 * Импорт статических страниц из `preview/` источника.
 *
 * Эталон разметки — HTML-превью шаблона `elv_bootstrap` (единственный
 * исполняемый оракул, `docs/qa-checklist.md` §B). Скрипт берёт `<main>`
 * готовой страницы, переписывает относительные адреса в абсолютные
 * (`assets/…` → `/themes/elv/assets/…`, `news/` → `/news/`) и записывает
 * CMS-страницу October с front matter.
 *
 * Переносятся только 13 корневых страниц: списки и детальные (91 элемент)
 * собираются из Tailor-записей шаблонами `themes/elv/pages/*.htm`.
 *
 *   node tools/import-preview-pages.mjs [--source D:/nlbbootstrap-main/preview]
 *
 * Повторный прогон перезаписывает страницы — это идемпотентно.
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const sourceIndex = process.argv.indexOf('--source');
const source = sourceIndex !== -1 ? process.argv[sourceIndex + 1] : 'D:/nlbbootstrap-main/preview';
const pagesDir = path.join(root, 'themes/elv/pages');
const themeUrl = '/themes/elv/';

const pages = [
    { file: 'index', url: '/', name: 'index' },
    { file: 'about/index', url: '/about/', name: 'about' },
    { file: 'auth/index', url: '/auth/', name: 'auth' },
    { file: 'contacts/index', url: '/contacts/', name: 'contacts' },
    { file: 'feedback/index', url: '/feedback/', name: 'feedback' },
    { file: 'privacy/index', url: '/privacy/', name: 'privacy' },
    { file: 'rules/index', url: '/rules/', name: 'rules' },
    { file: 'search/index', url: '/search/', name: 'search' },
    { file: 'sitemap/index', url: '/sitemap/', name: 'sitemap' },
    { file: 'subscribe/index', url: '/subscribe/', name: 'subscribe' },
    { file: 'terms/index', url: '/terms/', name: 'terms' },
    { file: '404', url: '/404', name: '404', hidden: true },
];

function readPreview(relative) {
    const file = path.join(source, relative + '.html');
    if (!fs.existsSync(file)) {
        throw new Error(`нет файла превью: ${file}`);
    }

    return fs.readFileSync(file, 'utf8');
}

function mainBlock(html, relative) {
    const start = html.indexOf('<main');
    const end = html.lastIndexOf('</main>');

    if (start === -1 || end === -1) {
        throw new Error(`в ${relative} нет <main>`);
    }

    return html.slice(start, end + '</main>'.length);
}

function rewriteUrl(url) {
    const value = url.trim();

    if (value === '' || /^(https?:|mailto:|tel:|data:|javascript:)/i.test(value)) {
        return value;
    }

    const hadUp = /^\.\.\//.test(value);
    let rest = value.replace(/^(\.\.\/)+/, '');

    if (rest.startsWith('#')) {
        return hadUp ? '/' + rest : rest;
    }

    if (rest === '' || rest === '.' || rest === './') {
        return '/';
    }

    if (rest.startsWith('assets/')) {
        return themeUrl + rest;
    }

    rest = rest.replace(/^\.\//, '');

    if (rest.startsWith('/')) {
        return rest;
    }

    return '/' + rest;
}

function rewriteSrcset(value) {
    return value
        .split(',')
        .map((part) => {
            const trimmed = part.trim();
            if (trimmed === '') return '';
            const [url, ...descriptor] = trimmed.split(/\s+/);

            return [rewriteUrl(url), ...descriptor].join(' ');
        })
        .filter(Boolean)
        .join(', ');
}

function rewriteMarkup(markup) {
    return markup
        .replace(/(\s(?:href|src|action))="([^"]*)"/g, (all, attr, value) => `${attr}="${rewriteUrl(value)}"`)
        .replace(/(\s(?:href|src|action))='([^']*)'/g, (all, attr, value) => `${attr}='${rewriteUrl(value)}'`)
        .replace(/(\ssrcset)="([^"]*)"/g, (all, attr, value) => `${attr}="${rewriteSrcset(value)}"`)
        .replace(/(\sdata-bs-target)="([^"]*)"/g, (all, attr, value) => `${attr}="${value}"`);
}

function frontMatter(page, html) {
    const title = (html.match(/<title>([\s\S]*?)<\/title>/) || [])[1] || '';
    const description = (html.match(/<meta\s+name="description"\s+content="([^"]*)"/) || [])[1] || '';
    const lines = [
        `title = "${title.replace(/\s+/g, ' ').trim().replace(/"/g, '\\"')}"`,
        `url = "${page.url}"`,
        'layout = "default"',
    ];

    if (page.hidden) lines.push('hidden = 1');
    if (description) lines.push(`seo_description = "${description.replace(/"/g, '\\"')}"`);

    return lines.join('\n');
}

const personal = `<main class="site-main" id="main">
<section class="page-hero">
    <div class="container">
        <span class="eyebrow eyebrow--green">Кабинет</span>
        <h1 class="page-title">Личный кабинет</h1>
        <p class="page-lead">Страница доступна после входа: продление книг, брони и подписка.</p>
        <a class="btn btn-elv btn-lg-elv" href="/auth/">Войти в кабинет</a>
    </div>
</section>
</main>`;

fs.mkdirSync(pagesDir, { recursive: true });

let written = 0;

for (const page of pages) {
    const html = readPreview(page.file);
    const markup = rewriteMarkup(mainBlock(html, page.file));

    if (/<link|<style|<script\s+src=/i.test(markup)) {
        console.error(`ВНИМАНИЕ: ${page.name} тянет внешние ресурсы — проверьте вручную`);
    }

    const content = `${frontMatter(page, html)}\n==\n${markup}\n`;

    fs.writeFileSync(path.join(pagesDir, page.name + '.htm'), content, 'utf8');
    written++;
}

const personalHtml = readPreview('personal/index');
const personalTitle = (personalHtml.match(/<title>([\s\S]*?)<\/title>/) || [])[1] || 'Личный кабинет';
const personalPage = `title = "${personalTitle.trim()}"\nurl = "/personal/"\nlayout = "default"\nseo_description = "Вход в личный кабинет читателя: продление книг, брони и подписка."\n==\n${personal}\n`;
fs.writeFileSync(path.join(pagesDir, 'personal.htm'), personalPage, 'utf8');
written++;

console.log(`записано страниц: ${written} → themes/elv/pages/`);

const sitemapFile = path.join(pagesDir, 'sitemap.htm');
if (!fs.existsSync(sitemapFile)) {
    console.error('ВНИМАНИЕ: sitemap.htm не создан');
    process.exit(1);
}
