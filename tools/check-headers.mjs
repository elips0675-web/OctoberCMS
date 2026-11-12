#!/usr/bin/env node
/**
 * Заголовки безопасности живого сайта: CSP, X-Frame-Options, Referrer-Policy,
 * Permissions-Policy, HSTS-условие и nonce инлайнового скрипта.
 *
 * Что проверяется (P1-4, Stage 10):
 *   - каждая публичная страница отдаёт полный набор статических заголовков;
 *   - CSP содержит `default-src 'self'`, `frame-ancestors 'none'`,
 *     `object-src 'none'`, `script-src 'self' 'nonce-…'`, `style-src` с
 *     `'unsafe-inline'` (инлайновый CSS темы) и **не** содержит `unsafe-eval`;
 *   - nonce из заголовка совпадает с атрибутом `nonce` инлайнового скрипта
 *     фильтров (`/news/`, `/books/`, `/events/`), а инлайновых скриптов без
 *     nonce на странице нет;
 *   - два запроса получают разные nonce (значение одноразовое);
 *   - админка (`BACKEND_URI`, по умолчанию `/admin`) остаётся без CSP
 *     и с `X-Frame-Options: SAMEORIGIN` — иначе ломаются виджеты October;
 *   - страница 404 получает те же заголовки, а не «голый» ответ.
 *
 *   node tools/check-headers.mjs [--base=http://127.0.0.1:8080]
 *
 * Требуется запущенный `php artisan serve` (порт по умолчанию 8080).
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const baseArg = process.argv.find((arg) => arg.startsWith('--base='));
const base = (baseArg ? baseArg.slice('--base='.length) : 'http://127.0.0.1:8080').replace(/\/$/, '');

const pages = [
    '/',
    '/books/',
    '/printing/',
    '/news/',
    '/services/',
    '/events/',
    '/collections/',
    '/about/',
    '/contacts/',
    '/feedback/',
    '/subscribe/',
    '/search/',
];

const inlinePages = ['/news/', '/books/', '/events/'];

function backendUri() {
    const envFile = path.join(root, '.env');

    if (!fs.existsSync(envFile)) {
        return 'admin';
    }

    const line = fs.readFileSync(envFile, 'utf8')
        .split(/\r?\n/)
        .find((row) => /^BACKEND_URI=/.test(row));

    if (!line) {
        return 'admin';
    }

    return line.slice('BACKEND_URI='.length).trim().replace(/^["']|["']$/g, '').replace(/^\/|\/$/g, '') || 'admin';
}

function nonceOf(csp) {
    const match = /'nonce-([^']+)'/.exec(csp || '');

    return match ? match[1] : '';
}

async function main() {
    const failures = [];
    const seenNonces = new Set();

    for (const url of pages) {
        const response = await fetch(base + url);
        const headers = response.headers;

        if (response.status !== 200) {
            failures.push(`${url}: HTTP ${response.status}`);
            continue;
        }

        const expected = {
            'x-content-type-options': 'nosniff',
            'x-frame-options': 'DENY',
            'referrer-policy': 'strict-origin-when-cross-origin',
        };

        for (const [name, value] of Object.entries(expected)) {
            const actual = headers.get(name);
            if (actual !== value) {
                failures.push(`${url}: ${name} «${actual || 'нет'}», ожидался «${value}»`);
            }
        }

        const permissions = headers.get('permissions-policy') || '';
        for (const feature of ['camera=()', 'microphone=()', 'geolocation=()']) {
            if (!permissions.includes(feature)) {
                failures.push(`${url}: Permissions-Policy без ${feature}`);
            }
        }

        const csp = headers.get('content-security-policy');

        if (!csp) {
            failures.push(`${url}: нет Content-Security-Policy`);
            continue;
        }

        const required = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "img-src 'self' data:",
            "style-src 'self' 'unsafe-inline'",
            "connect-src 'self'",
        ];

        for (const directive of required) {
            if (!csp.includes(directive)) {
                failures.push(`${url}: CSP без «${directive}»`);
            }
        }

        if (csp.includes('unsafe-eval')) {
            failures.push(`${url}: CSP разрешает unsafe-eval`);
        }

        const nonce = nonceOf(csp);

        if (!nonce) {
            failures.push(`${url}: CSP без nonce для инлайновых скриптов`);
            continue;
        }

        seenNonces.add(nonce);

        if (!csp.includes(`script-src 'self' 'nonce-${nonce}'`)) {
            failures.push(`${url}: script-src не содержит nonce этого ответа`);
        }

        if (headers.get('strict-transport-security')) {
            failures.push(`${url}: HSTS на http-ответе`);
        }

        if (!inlinePages.includes(url)) {
            continue;
        }

        const html = await response.text();
        const inline = /<script nonce="([^"]*)">/.exec(html);

        if (!inline) {
            failures.push(`${url}: инлайновый скрипт фильтров без nonce`);
        }
        else if (inline[1] !== nonce) {
            failures.push(`${url}: nonce скрипта «${inline[1]}» не совпадает с заголовком «${nonce}»`);
        }

        const rogue = /<script(?![^>]*\bsrc=)(?![^>]*\bnonce=)(?![^>]*type="application\/ld\+json")[^>]*>/i.exec(html);
        if (rogue) {
            failures.push(`${url}: инлайновый скрипт без nonce — ${rogue[0].slice(0, 80)}`);
        }
    }

    if (seenNonces.size !== pages.length) {
        failures.push(`nonce повторяется: ${seenNonces.size} разных значений на ${pages.length} страниц`);
    }

    const admin = `/${backendUri()}`;
    const backend = await fetch(base + admin);
    const backendHeaders = backend.headers;

    if (![200, 302].includes(backend.status)) {
        failures.push(`${admin}: HTTP ${backend.status}, ожидался 200 или 302`);
    }
    if (backendHeaders.get('content-security-policy')) {
        failures.push(`${admin}: CSP на админке ломает виджеты October`);
    }
    if (backendHeaders.get('x-frame-options') !== 'SAMEORIGIN') {
        failures.push(`${admin}: X-Frame-Options «${backendHeaders.get('x-frame-options') || 'нет'}», ожидался SAMEORIGIN`);
    }
    if (backendHeaders.get('x-content-type-options') !== 'nosniff') {
        failures.push(`${admin}: нет X-Content-Type-Options`);
    }

    const missing = await fetch(base + '/non-existent-page-check/');
    if (missing.status !== 404) {
        failures.push(`/non-existent-page-check/: HTTP ${missing.status}, ожидался 404`);
    }
    if (missing.headers.get('x-frame-options') !== 'DENY') {
        failures.push('404: нет X-Frame-Options DENY');
    }
    if (!missing.headers.get('content-security-policy')) {
        failures.push('404: нет Content-Security-Policy');
    }

    if (failures.length > 0) {
        console.error('FAIL: ' + failures.length + ' проблем');
        for (const line of failures) {
            console.error('  - ' + line);
        }
        process.exit(1);
    }

    console.log(`OK: заголовки на ${pages.length} страницах, CSP с уникальным nonce, админка без CSP (${admin}), 404 с заголовками`);
}

main().catch((error) => {
    console.error('FAIL: ' + error.message);
    process.exit(1);
});
