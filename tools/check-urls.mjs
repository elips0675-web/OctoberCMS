#!/usr/bin/env node
/**
 * Проверка 110 адресов сайта на живом October (`docs/qa-checklist.md` §B).
 *
 * Инвентарь берётся из `docs/content.md`: разделы 1.2–1.7 дают слаги
 * детальных страниц, раздел 1.1 — корневые адреса. Скрипт не подменяет
 * DOM-сверку с `preview/`, он отвечает на другой вопрос: адрес отвечает,
 * отдаёт непустую страницу и не тянет внешний CSS.
 *
 * 110 адресов инвентаря = 109 страниц (12 корневых + 6 списков + 91 детальная)
 * плюс 404, который проверяется адресом `/non-existent/`.
 *
 *   node tools/check-urls.mjs [--base=http://127.0.0.1:8080]
 *
 * Требуется запущенный `php artisan serve` (порт по умолчанию 8080).
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const baseArg = process.argv.find((arg) => arg.startsWith('--base='));
const base = (baseArg ? baseArg.slice('--base='.length) : 'http://127.0.0.1:8080').replace(/\/$/, '');

const expect = {
    2: { prefix: 'books', count: 24 },
    3: { prefix: 'printing', count: 37 },
    4: { prefix: 'news', count: 10 },
    5: { prefix: 'services', count: 8 },
    6: { prefix: 'events', count: 6 },
    7: { prefix: 'collections', count: 6 },
};

const rootPages = [
    '/',
    '/books/',
    '/printing/',
    '/news/',
    '/services/',
    '/events/',
    '/collections/',
    '/about/',
    '/auth/',
    '/contacts/',
    '/feedback/',
    '/personal/',
    '/privacy/',
    '/rules/',
    '/search/',
    '/sitemap/',
    '/subscribe/',
    '/terms/',
];

function readInventory() {
    const doc = fs.readFileSync(path.join(root, 'docs/content.md'), 'utf8');
    const lines = doc.split(/\r?\n/);
    const urls = [];
    const problems = [];

    let section = null;
    for (const line of lines) {
        const heading = /^### 1\.(\d)\./.exec(line);
        if (heading) {
            section = Number(heading[1]);
            continue;
        }
        if (/^## /.test(line)) {
            section = null;
            continue;
        }
        if (!section || !expect[section]) {
            continue;
        }

        const found = line.match(/`([a-z0-9-]+)`/g) || [];
        for (const token of found) {
            const slug = token.slice(1, -1);
            urls.push('/' + expect[section].prefix + '/' + slug + '/');
        }
    }

    for (const [number, group] of Object.entries(expect)) {
        const actual = urls.filter((url) => url.startsWith('/' + group.prefix + '/')).length;
        if (actual !== group.count) {
            problems.push(
                `раздел 1.${number}: в docs/content.md ${actual} слагов, ожидалось ${group.count}`
            );
        }
    }

    return { urls: [...rootPages, ...urls], problems };
}

const standalone = new Set(['/personal/']);

function h1Count(html) {
    return (html.match(/<h1[\s>]/g) || []).length;
}

function inlineCssBytes(html) {
    const match = /<style data-nlb-css="styles">([\s\S]*?)<\/style>/i.exec(html);

    return match ? Buffer.byteLength(match[1], 'utf8') : 0;
}

async function main() {
    const { urls, problems: inventoryProblems } = readInventory();
    const failures = [...inventoryProblems];

    if (urls.length !== 109) {
        failures.push(`адресов ${urls.length}, ожидалось 109 (110-й — 404 по /non-existent/)`);
    }

    let checked = 0;
    let mainDocument = null;

    for (const url of urls) {
        let response;
        try {
            response = await fetch(base + url, { redirect: 'manual' });
        } catch (error) {
            failures.push(`${url}: запрос не выполнен (${error.message})`);
            continue;
        }

        const html = await response.text();
        checked += 1;

        if (response.status !== 200) {
            failures.push(`${url}: HTTP ${response.status}`);
            continue;
        }
        if (html.length < (standalone.has(url) ? 500 : 5000)) {
            failures.push(`${url}: документ ${html.length} знаков — пустая страница`);
            continue;
        }
        if (/Fatal error|Whoops|Exception found/i.test(html)) {
            failures.push(`${url}: страница содержит текст ошибки`);
        }
        if (!standalone.has(url) && !/<main[\s>]/.test(html)) {
            failures.push(`${url}: нет <main>`);
        }
        if (h1Count(html) !== 1) {
            failures.push(`${url}: заголовков h1 — ${h1Count(html)}, ожидался 1`);
        }
        if (html.includes('fonts.googleapis') || html.includes('fonts.gstatic')) {
            failures.push(`${url}: подключены внешние шрифты Google`);
        }
        if (/<link[^>]+rel=["']stylesheet["']/i.test(html)) {
            failures.push(`${url}: внешняя таблица стилей`);
        }
        if (!/lang="ru"/.test(html)) {
            failures.push(`${url}: нет lang="ru"`);
        }

        if (url === '/') {
            mainDocument = html;
        }
    }

    const notFound = await fetch(base + '/non-existent/');
    const notFoundHtml = await notFound.text();
    if (notFound.status !== 404) {
        failures.push(`/non-existent/: HTTP ${notFound.status}, ожидался 404`);
    }
    if (!/Страница не найдена/.test(notFoundHtml)) {
        failures.push('/non-existent/: нет страницы 404, отдана пустота');
    }

    const withQuery = await fetch(base + '/services/cafe/?utm_source=test');
    if (withQuery.status !== 200) {
        failures.push(`/services/cafe/?utm_source=test: HTTP ${withQuery.status}`);
    }

    if (mainDocument) {
        const chars = mainDocument.length;
        if (chars > 190412) {
            failures.push(`главная: ${chars} знаков, бюджет 190 412`);
        }
        const css = inlineCssBytes(mainDocument);
        if (css > 57795) {
            failures.push(`главная: инлайн styles.css ${css} байт, бюджет 57 795`);
        }
    }

    if (failures.length > 0) {
        console.error('FAIL: ' + failures.length + ' проблем');
        for (const line of failures) {
            console.error('  - ' + line);
        }
        process.exit(1);
    }

    console.log(`OK: ${checked} страниц отвечают 200, /non-existent/ — 404 со страницей, внешнего CSS нет`);
    console.log(`главная: ${mainDocument.length} знаков, инлайн styles.css ${inlineCssBytes(mainDocument)} байт`);
}

main().catch((error) => {
    console.error('FAIL: ' + error.message);
    process.exit(1);
});
