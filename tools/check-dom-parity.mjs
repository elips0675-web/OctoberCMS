#!/usr/bin/env node
/**
 * DOM-сверка 110 страниц с эталоном `preview/` источника.
 *
 * Проверяется то, что легко протухает молча: `<title>`, `h1`, `h2`
 * (заголовки модалок исключены — они скрыты и порядок вставки не влияет
 * на вид), число карточек `<article>` и текст `<main>`.
 *
 *   node tools/check-dom-parity.mjs [--source=D:/nlbbootstrap-main/preview]
 *                                  [--base=http://127.0.0.1:8080] [--no-text]
 *
 * Без источника (`preview/` нет) гейт красный: сверять не с чем.
 * Намеренное изменение текста (реальные контакты, P0-6) сначала попадает
 * в эталон, иначе гейт справедливо падает.
 *
 * Обезличивание бренда (Stage 27) — осознанное отклонение: название
 * организации, заголовок страницы «О центре» и подпись демо-тега
 * нормализуются в токены, поэтому смена бренда не роняет сверку, а весь
 * остальной текст по-прежнему сравнивается побайтово.
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const arg = (name, fallback) => {
    const found = process.argv.find((value) => value.startsWith('--' + name + '='));

    return found ? found.slice(name.length + 3) : fallback;
};
const source = arg('source', 'D:/nlbbootstrap-main/preview').replace(/\/$/, '');
const base = arg('base', 'http://127.0.0.1:8080').replace(/\/$/, '');
const compareText = !process.argv.includes('--no-text');

if (!fs.existsSync(source)) {
    console.error(`FAIL: нет эталона ${source} (--source=путь)`);
    process.exit(1);
}

function files(dir, prefix = '') {
    const out = [];
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        if (entry.isDirectory()) {
            out.push(...files(path.join(dir, entry.name), prefix + entry.name + '/'));
        }
        else if (entry.name.endsWith('.html')) {
            out.push(prefix + entry.name);
        }
    }

    return out;
}

function urlOf(relative) {
    if (relative === 'index.html') {
        return '/';
    }
    if (relative === '404.html') {
        return '/non-existent/';
    }

    return '/' + relative.replace(/index\.html$/, '').replace(/\.html$/, '/');
}

const decode = (value) => value
    .replace(/&nbsp;/g, ' ')
    .replace(/&laquo;/g, '«')
    .replace(/&raquo;/g, '»')
    .replace(/&mdash;/g, '—')
    .replace(/&ndash;/g, '–')
    .replace(/&amp;/g, '&')
    .replace(/&quot;/g, '"')
    .replace(/&#39;/g, "'")
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>');

function text(html) {
    return textBlock(html).replace(/\s+/g, ' ').trim();
}

function textBlock(html) {
    const start = html.indexOf('<main');
    const end = html.lastIndexOf('</main>');
    const block = start === -1 ? html : html.slice(start, end);

    return decode(block
        .replace(/<script[\s\S]*?<\/script>/gi, ' ')
        .replace(/<style[\s\S]*?<\/style>/gi, ' ')
        .replace(/<[^>]+>/g, ' '));
}

function title(html) {
    const found = /<title>([\s\S]*?)<\/title>/i.exec(html);

    return found ? decode(found[1]).trim() : '';
}

function headings(html, tag) {
    const pattern = new RegExp('<' + tag + '([^>]*)>([\\s\\S]*?)</' + tag + '>', 'gi');

    return [...html.matchAll(pattern)]
        .filter((match) => !/modal-title/.test(match[1]))
        .map((match) => textBlock(match[2]).replace(/\s+/g, ' ').trim());
}

function articles(html) {
    return (html.match(/<article[\s>]/g) || []).length;
}

const BRAND_SWAPS = [
    [/Библиотека 2026/g, '{{brand}}'],
    [/Ваша организация/g, '{{brand}}'],
    [/Библиотека как пространство/g, '{{about}}'],
    [/О центре/g, '{{about}}'],
    [/Библиотека/g, '{{org}}'],
    [/О нас/g, '{{org}}'],
];

function normalizeBrand(value) {
    return BRAND_SWAPS.reduce((acc, [pattern, token]) => acc.replace(pattern, token), value);
}

async function main() {
    const failures = [];
    const pages = files(source).sort();

    if (pages.length !== 110) {
        failures.push(`в эталоне ${pages.length} HTML, ожидалось 110`);
    }

    for (const relative of pages) {
        const url = urlOf(relative);
        const reference = fs.readFileSync(path.join(source, relative), 'utf8');

        let html;
        try {
            const response = await fetch(base + url);
            html = await response.text();
        }
        catch (error) {
            failures.push(`${url}: запрос не выполнен (${error.message})`);
            continue;
        }

        if (normalizeBrand(title(reference)) !== normalizeBrand(title(html))) {
            failures.push(`${url}: title «${title(reference)}» ≠ «${title(html)}»`);
        }

        const referenceH1 = normalizeBrand(headings(reference, 'h1').join(' | '));
        const liveH1 = normalizeBrand(headings(html, 'h1').join(' | '));
        if (referenceH1 !== liveH1) {
            failures.push(`${url}: h1 «${referenceH1}» ≠ «${liveH1}»`);
        }

        const referenceH2 = normalizeBrand(headings(reference, 'h2').join(' | '));
        const liveH2 = normalizeBrand(headings(html, 'h2').join(' | '));
        if (referenceH2 !== liveH2) {
            failures.push(`${url}: h2 «${referenceH2}» ≠ «${liveH2}»`);
        }

        if (articles(reference) !== articles(html)) {
            failures.push(`${url}: карточек ${articles(html)}, в эталоне ${articles(reference)}`);
            continue;
        }

        if (compareText && normalizeBrand(text(reference)) !== normalizeBrand(text(html))) {
            failures.push(`${url}: текст <main> расходится с эталоном`);
        }
    }

    if (failures.length > 0) {
        console.error(`FAIL: ${failures.length} расхождений на ${pages.length} страницах`);
        for (const line of failures.slice(0, 60)) {
            console.error('  - ' + line);
        }
        if (failures.length > 60) {
            console.error(`  … ещё ${failures.length - 60}`);
        }
        process.exit(1);
    }

    console.log(`OK: ${pages.length} страниц совпадают с эталоном${compareText ? ' (title, h1, h2, карточки, текст)' : ' (title, h1, h2, карточки)'}`);
}

main().catch((error) => {
    console.error('FAIL: ' + error.message);
    process.exit(1);
});
