#!/usr/bin/env node
/**
 * Бюджет встроенного CSS и внешних запросов темы elv.
 *
 * Проверяет то, что видит браузер до первой отрисовки:
 *   - минифицированный `styles.css` (встраивается в <style>) влезает в бюджет;
 *   - `bootstrap.min.css` не вырос выше базовой линии источника;
 *   - в разметке темы нет `<link rel="stylesheet">`, Google Fonts и внешних
 *     `@font-face` — шрифты системные, каждая внешняя таблица стилей блокирует
 *     рендер.
 *
 * Размеры считает `tools/css-size.php` тем же `Media::minifyCss`, что и макет:
 * гейт меряет ровно ту строку, которая попадёт в HTML.
 *
 *   node tools/check-css-budget.mjs
 *   node tools/check-css-budget.mjs --url http://127.0.0.1:8080/
 *
 * `--url` дополнительно проверяет живую страницу: документ ≤ 190 412 знаков,
 * ноль `<link rel="stylesheet">`, встроенные `<style>` в бюджете.
 */

import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const theme = path.join(root, 'themes/elv');

// 56,4 КиБ в документации = 57 753 байта; фактическая базовая линия источника
// 57 794 байта, у перенесённой темы — 57 795. Бюджет держим на 56,5 КиБ.
const STYLES_BUDGET = 57856;
const BOOTSTRAP_BUDGET = 61377;
const DOCUMENT_BUDGET = 190412;

const stylesFile = 'themes/elv/assets/css/styles.css';
const bootstrapFile = 'themes/elv/assets/vendor/bootstrap.min.css';

const failures = [];

function phpBinary() {
    if (process.env.PHP_BIN) return process.env.PHP_BIN;
    const candidates = [
        'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe',
        'php',
    ];

    for (const candidate of candidates) {
        try {
            execFileSync(candidate, ['-v'], { stdio: 'ignore' });
            return candidate;
        } catch {
            continue;
        }
    }

    return null;
}

function minifiedSizes(files) {
    const php = phpBinary();
    if (!php) {
        failures.push('PHP не найден: задайте PHP_BIN или PATH, иначе размер CSS не измерить');
        return [];
    }

    const out = execFileSync(php, [path.join(root, 'tools/css-size.php'), ...files], {
        cwd: root,
        encoding: 'utf8',
    });

    return out
        .split(/\r?\n/)
        .filter((line) => line.trim() !== '')
        .map((line) => JSON.parse(line));
}

function walk(dir, out) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        if (entry.isDirectory()) {
            if (entry.name === 'node_modules') continue;
            walk(path.join(dir, entry.name), out);
            continue;
        }
        if (/\.(htm|html|php)$/i.test(entry.name)) out.push(path.join(dir, entry.name));
    }

    return out;
}

const rel = (file) => path.relative(root, file).replace(/\\/g, '/');
const kb = (bytes) => (bytes / 1024).toFixed(1);

console.log(`бюджет styles.css: ${STYLES_BUDGET} байт (${kb(STYLES_BUDGET)} КБ)`);

for (const size of minifiedSizes([stylesFile, bootstrapFile])) {
    const budget = size.file.endsWith('styles.css') ? STYLES_BUDGET : BOOTSTRAP_BUDGET;
    const verdict = size.minified <= budget ? 'OK' : 'ПРЕВЫШЕНИЕ';
    console.log(`${size.file}: ${size.raw} → ${size.minified} байт (${kb(size.minified)} КБ) — ${verdict}`);
    if (size.minified > budget) {
        failures.push(`${size.file}: ${size.minified} байт больше бюджета ${budget}`);
    }
}

const sources = walk(theme, []);
const forbidden = [
    [/<link[^>]+rel=["']stylesheet["']/i, '<link rel="stylesheet"> (внешняя таблица стилей)'],
    [/fonts\.googleapis\.com|fonts\.gstatic\.com/i, 'Google Fonts'],
    [/@font-face/i, '@font-face (шрифты должны быть системными)'],
];

for (const file of sources) {
    const text = fs.readFileSync(file, 'utf8');
    for (const [pattern, label] of forbidden) {
        if (pattern.test(text)) failures.push(`${rel(file)}: ${label}`);
    }
}

if (!sources.length) failures.push('в themes/elv нет разметки — проверять нечего');

const urlIndex = process.argv.indexOf('--url');
if (urlIndex !== -1) {
    const url = process.argv[urlIndex + 1];
    if (!url) {
        failures.push('--url без адреса');
    } else {
        const html = await fetch(url).then((response) => {
            if (!response.ok) throw new Error(`${url} ответил ${response.status}`);
            return response.text();
        });

        const links = [...html.matchAll(/<link[^>]+rel=["']stylesheet["'][^>]*>/gi)].map((m) => m[0]);
        const inline = [...html.matchAll(/<style[^>]*>([\s\S]*?)<\/style>/gi)].map((m) => m[1].length);
        const total = inline.reduce((sum, size) => sum + size, 0);

        console.log(`${url}: документ ${html.length} знаков, встроенный CSS ${inline.join(' + ')} = ${total} байт`);

        if (html.length > DOCUMENT_BUDGET) failures.push(`документ ${html.length} знаков больше ${DOCUMENT_BUDGET}`);
        if (links.length) failures.push(`внешних таблиц стилей: ${links.length}`);
        if (total > STYLES_BUDGET + BOOTSTRAP_BUDGET) {
            failures.push(`встроенный CSS ${total} байт больше ${STYLES_BUDGET + BOOTSTRAP_BUDGET}`);
        }
    }
}

if (failures.length) {
    console.error('\nПРОВАЛ:');
    for (const failure of failures) console.error(` - ${failure}`);
    process.exit(1);
}

console.log('\nOK: бюджет CSS соблюдён, внешних таблиц стилей и шрифтов нет');
