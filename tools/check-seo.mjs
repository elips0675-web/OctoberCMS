#!/usr/bin/env node
/**
 * SEO-периметр живого сайта: robots.txt, карта сайта, 301 и базовая микроразметка.
 *
 * Что проверяется (M20):
 *   - `/robots.txt` — 200 `text/plain`, `Disallow: /admin/`, директива `Sitemap`
 *     с адресом этого же сайта;
 *   - `/sitemap.xml` — 200 `application/xml`, `<lastmod>` у каждого адреса,
 *     каждый `<loc>` отвечает 200, закрытые в robots адреса в карту не попали,
 *     и **все** адреса инвентаря `docs/content.md` (кроме закрытых) в карте есть;
 *   - старые адреса 1С-Битрикс отдают 301 на новые (детальная — с сохранением
 *     `utm_*`, без `CODE`);
 *   - на главной есть JSON-LD `Library` + `WebSite` с `SearchAction`, и это
 *     валидный JSON, а не «почти JSON»;
 *   - на детальной странице узел своего вида (`Article`, `Event`, `Product`,
 *     `Service`), у подборки — только общие узлы, у всех — `og:image`
 *     и `twitter:image` (P2-4, Stage 9).
 *
 *   node tools/check-seo.mjs [--base=http://127.0.0.1:8080]
 *
 * Требуется запущенный `php artisan serve` (порт по умолчанию 8080).
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const baseArg = process.argv.find((arg) => arg.startsWith('--base='));
const base = (baseArg ? baseArg.slice('--base='.length) : 'http://127.0.0.1:8080').replace(/\/$/, '');

const closed = ['/auth/', '/personal/', '/search/'];

const recordTypes = {
    news: 'Article',
    events: 'Event',
    books: 'Product',
    printing: 'Product',
    services: 'Service',
};

const sections = {
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

const redirects = [
    { from: '/index.php', to: '/' },
    { from: '/sitemap.php', to: '/sitemap.xml' },
    { from: '/about/index.php', to: '/about/' },
    { from: '/services/index.php', to: '/services/' },
    { from: '/news/detail.php', to: '/news/' },
    { from: '/books/detail.php?CODE=bad%20code', to: '/books/' },
];

function readInventory() {
    const doc = fs.readFileSync(path.join(root, 'docs/content.md'), 'utf8');
    const urls = [];

    let section = null;
    for (const line of doc.split(/\r?\n/)) {
        const heading = /^### 1\.(\d)\./.exec(line);
        if (heading) {
            section = Number(heading[1]);
            continue;
        }
        if (/^## /.test(line)) {
            section = null;
            continue;
        }
        if (!section || !sections[section]) {
            continue;
        }

        for (const token of line.match(/`([a-z0-9-]+)`/g) || []) {
            urls.push('/' + sections[section].prefix + '/' + token.slice(1, -1) + '/');
        }
    }

    return urls;
}

function detailSlug(url) {
    const match = /^\/([a-z]+)\/([a-z0-9-]+)\/$/.exec(url);

    return match ? { prefix: match[1], slug: match[2] } : null;
}

/**
 * checkDetailPage проверяет микроразметку и карточку шеринга детальной страницы.
 */
function checkDetailPage(url, html) {
    const failures = [];
    const prefix = url.split('/')[1];
    const expected = recordTypes[prefix];

    const match = /<script type="application\/ld\+json">([\s\S]*?)<\/script>/.exec(html);
    if (!match) {
        return [`${url}: нет <script type="application/ld+json">`];
    }

    let graph = [];
    try {
        const data = JSON.parse(match[1]);
        graph = Array.isArray(data['@graph']) ? data['@graph'] : [];
    }
    catch (error) {
        return [`${url}: JSON-LD не разбирается (${error.message})`];
    }

    const types = graph.map((node) => node['@type']);

    if (expected) {
        const node = graph.find((item) => item['@type'] === expected);

        if (!node) {
            failures.push(`${url}: нет узла ${expected} (есть ${types.join(', ') || 'ничего'})`);
        }
        else if (expected === 'Article' && (!node.headline || !node.datePublished)) {
            failures.push(`${url}: у Article нет headline или datePublished`);
        }
        else if (expected === 'Event' && (!node.startDate || !node.location)) {
            failures.push(`${url}: у Event нет startDate или location`);
        }
        else if (expected === 'Product' && !node.sku) {
            failures.push(`${url}: у Product нет sku`);
        }
        else if (expected === 'Service' && !node.serviceType) {
            failures.push(`${url}: у Service нет serviceType`);
        }
    }
    else if (types.join(',') !== 'Library,WebSite') {
        failures.push(`${url}: у подборки лишние узлы (${types.join(', ')})`);
    }

    if (!/<meta property="og:image" content="https?:\/\/[^"]+">/.test(html)) {
        failures.push(`${url}: нет абсолютного og:image`);
    }
    if (!/<meta name="twitter:image" content="https?:\/\/[^"]+">/.test(html)) {
        failures.push(`${url}: нет абсолютного twitter:image`);
    }

    const canonical = /<link rel="canonical" href="([^"]+)">/.exec(html);
    if (!canonical) {
        failures.push(`${url}: нет canonical`);
    }
    else if (canonical[1] !== base + url) {
        failures.push(`${url}: canonical «${canonical[1]}», ожидался «${base}${url}»`);
    }

    return failures;
}

async function main() {
    const failures = [];

    const robots = await fetch(base + '/robots.txt');
    const robotsBody = await robots.text();
    if (robots.status !== 200) {
        failures.push(`/robots.txt: HTTP ${robots.status}`);
    }
    if (!/text\/plain/.test(robots.headers.get('content-type') || '')) {
        failures.push(`/robots.txt: тип ${robots.headers.get('content-type')}`);
    }
    if (!robotsBody.includes('Disallow: /admin/')) {
        failures.push('/robots.txt: нет Disallow: /admin/');
    }
    if (!robotsBody.includes('Sitemap: ' + base + '/sitemap.xml')) {
        failures.push(`/robots.txt: директива Sitemap не указывает на ${base}/sitemap.xml`);
    }
    if (!robotsBody.includes('Clean-param:')) {
        failures.push('/robots.txt: нет Clean-param для фильтров');
    }

    const sitemap = await fetch(base + '/sitemap.xml');
    const xml = await sitemap.text();
    if (sitemap.status !== 200) {
        failures.push(`/sitemap.xml: HTTP ${sitemap.status}`);
    }
    if (!/application\/xml/.test(sitemap.headers.get('content-type') || '')) {
        failures.push(`/sitemap.xml: тип ${sitemap.headers.get('content-type')}`);
    }
    if (!xml.startsWith('<?xml version="1.0" encoding="UTF-8"?>')) {
        failures.push('/sitemap.xml: нет XML-пролога');
    }

    const blocks = [...xml.matchAll(/<url>([\s\S]*?)<\/url>/g)].map((match) => match[1]);
    const locs = blocks.map((block) => {
        const loc = /<loc>([^<]+)<\/loc>/.exec(block);
        const lastmod = /<lastmod>([^<]+)<\/lastmod>/.exec(block);

        return {
            loc: loc ? loc[1] : '',
            lastmod: lastmod ? lastmod[1] : '',
        };
    });

    const inventory = [...rootPages, ...readInventory()];
    const expected = inventory.filter((url) => !closed.includes(url));

    if (locs.length !== expected.length) {
        failures.push(`/sitemap.xml: адресов ${locs.length}, ожидалось ${expected.length}`);
    }

    const seen = new Set();

    for (const entry of locs) {
        if (!entry.loc.startsWith(base + '/')) {
            failures.push(`/sitemap.xml: <loc> ${entry.loc} не на этом сайте`);
            continue;
        }
        if (!/^\d{4}-\d{2}-\d{2}$/.test(entry.lastmod)) {
            failures.push(`/sitemap.xml: у ${entry.loc} lastmod «${entry.lastmod}»`);
        }

        const local = entry.loc.slice(base.length) || '/';
        seen.add(local);

        if (closed.includes(local)) {
            failures.push(`/sitemap.xml: закрытый в robots адрес ${local} попал в карту`);
        }
    }

    for (const url of expected) {
        if (!seen.has(url)) {
            failures.push(`/sitemap.xml: адреса ${url} нет в карте`);
        }
    }

    for (const url of seen) {
        const response = await fetch(base + url, { redirect: 'manual' });
        if (response.status !== 200) {
            failures.push(`/sitemap.xml: ${url} отвечает ${response.status}`);
            continue;
        }

        const detail = /^\/([a-z]+)\/[a-z0-9-]+\/$/.exec(url);
        if (detail) {
            failures.push(...checkDetailPage(url, await response.text()));
        }
    }

    const home = await (await fetch(base + '/')).text();
    const ld = /<script type="application\/ld\+json">([\s\S]*?)<\/script>/.exec(home);
    if (!ld) {
        failures.push('главная: нет <script type="application/ld+json">');
    }
    else {
        let data = null;
        try {
            data = JSON.parse(ld[1]);
        }
        catch (error) {
            failures.push('главная: JSON-LD не разбирается (' + error.message + ')');
        }

        if (data) {
            const graph = Array.isArray(data['@graph']) ? data['@graph'] : [];
            const library = graph.find((node) => node['@type'] === 'Library');
            const website = graph.find((node) => node['@type'] === 'WebSite');

            if (!library) {
                failures.push('главная: в JSON-LD нет Library');
            }
            else {
                if (!library.address || !library.address.streetAddress) {
                    failures.push('главная: в JSON-LD Library нет адреса');
                }
                if (!library.openingHoursSpecification) {
                    failures.push('главная: в JSON-LD Library нет часов работы');
                }
            }

            if (!website || !website.potentialAction) {
                failures.push('главная: в JSON-LD нет WebSite с SearchAction');
            }
        }
    }

    for (const rule of redirects) {
        const response = await fetch(base + rule.from, { redirect: 'manual' });
        const location = response.headers.get('location') || '';

        if (response.status !== 301) {
            failures.push(`${rule.from}: HTTP ${response.status}, ожидался 301`);
            continue;
        }
        if (location !== base + rule.to && !location.endsWith(rule.to)) {
            failures.push(`${rule.from}: Location «${location}», ожидался «${base}${rule.to}»`);
        }
    }

    const detailSample = expected.find((url) => /^\/news\/[a-z0-9-]+\/$/.test(url));
    if (detailSample) {
        const slug = detailSample.split('/')[2];
        const old = '/news/detail.php?CODE=' + slug + '&utm_source=seo-check';
        const response = await fetch(base + old, { redirect: 'manual' });
        const location = response.headers.get('location') || '';

        if (response.status !== 301) {
            failures.push(`${old}: HTTP ${response.status}, ожидался 301`);
        }
        else if (location !== base + detailSample + '?utm_source=seo-check') {
            failures.push(`${old}: Location «${location}», ожидался «${base}${detailSample}?utm_source=seo-check»`);
        }
    }

    if (failures.length > 0) {
        console.error('FAIL: ' + failures.length + ' проблем');
        for (const line of failures) {
            console.error('  - ' + line);
        }
        process.exit(1);
    }

    console.log(`OK: robots.txt и sitemap.xml (${locs.length} адресов, все 200), 301 со старых адресов, JSON-LD Library + WebSite + узел записи (Article/Event/Product/Service)`);
}

main().catch((error) => {
    console.error('FAIL: ' + error.message);
    process.exit(1);
});
