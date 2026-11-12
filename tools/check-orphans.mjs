#!/usr/bin/env node
/**
 * Сироты: у каждого адреса карты сайта должна быть входящая внутренняя ссылка.
 *
 * Что проверяется (P2-22):
 *   - `/sitemap.xml` разбирается, в нём есть адреса;
 *   - каждая страница карты отдаёт 200 (иначе «ссылка есть» не проверяема);
 *   - у каждого адреса, кроме главной и перечисленных в ALLOWED_ORPHANS, есть
 *     хотя бы одна входящая ссылка с другой страницы сайта (ссылка на себя
 *     не считается);
 *   - ссылки с параметрами и якорем сводятся к пути, внешние хосты
 *     игнорируются.
 *
 *   node tools/check-orphans.mjs [--base=http://127.0.0.1:8080]
 *   node tools/check-orphans.mjs --self-test
 *
 * `--self-test` проверяет сам разбор ссылок и поиск сирот на синтетических
 * данных: гейт, который зеленеет потому, что ничего не нашёл, ничего не
 * доказывает. Живой прогон требует `php artisan serve` (порт 8080).
 */

const baseArg = process.argv.find((arg) => arg.startsWith('--base='));
const base = (baseArg ? baseArg.slice('--base='.length) : 'http://127.0.0.1:8080').replace(/\/$/, '');
const origin = new URL(base).origin;

const ALLOWED_ORPHANS = ['/404'];

const CONCURRENCY = 6;

const failures = [];

/**
 * fetchText забирает страницу и возвращает тело либо null с записью ошибки.
 */
async function fetchText(url) {
    const response = await fetch(url, { redirect: 'follow' });

    if (!response.ok) {
        failures.push(`${url.slice(base.length) || '/'}: HTTP ${response.status}`);
        return null;
    }

    return response.text();
}

/**
 * sitemapPaths вытаскивает пути из `<loc>` карты сайта, отбрасывая чужие хосты.
 */
function sitemapPaths(xml) {
    return [...xml.matchAll(/<loc>([^<]+)<\/loc>/g)]
        .map((match) => {
            let url;

            try {
                url = new URL(match[1]);
            }
            catch {
                return null;
            }

            return url.origin === origin ? url.pathname : null;
        })
        .filter((pathname) => pathname !== null);
}

/**
 * internalLinks вытаскивает внутренние пути из `href` страницы.
 */
function internalLinks(html, currentPath) {
    const links = new Set();

    for (const match of html.matchAll(/href="([^"]+)"/g)) {
        const raw = match[1];

        if (raw.startsWith('mailto:') || raw.startsWith('tel:') || raw.startsWith('javascript:')) {
            continue;
        }

        let url;

        try {
            url = new URL(raw, base + currentPath);
        }
        catch {
            continue;
        }

        if (url.origin !== origin) {
            continue;
        }

        const pathname = url.pathname;

        if (pathname === currentPath) {
            continue;
        }

        links.add(pathname);
    }

    return links;
}

/**
 * orphansOf возвращает адреса без входящих ссылок (главная и исключения — нет).
 */
function orphansOf(paths, incoming) {
    return paths.filter((pathname) => {
        if (pathname === '/' || ALLOWED_ORPHANS.includes(pathname)) {
            return false;
        }

        return (incoming.get(pathname) || 0) === 0;
    });
}

/**
 * mapLimit выполняет fn по списку с ограничением параллельности.
 */
async function mapLimit(items, limit, fn) {
    const results = new Array(items.length);
    let index = 0;

    const workers = Array.from({ length: Math.min(limit, items.length) }, async () => {
        while (index < items.length) {
            const current = index++;
            results[current] = await fn(items[current], current);
        }
    });

    await Promise.all(workers);

    return results;
}

/**
 * selfTest проверяет разбор ссылок и поиск сирот без живого сервера.
 */
function selfTest() {
    const problems = [];

    const html = [
        '<a href="/a/">внутренняя</a>',
        '<a href="/a/?page=2#top">с параметром и якорем</a>',
        '<a href="/a/">дубль</a>',
        '<a href="https://other.example/b/">внешняя</a>',
        '<a href="mailto:info@example.by">почта</a>',
        '<a href="#top">якорь на себя</a>',
        '<a href="/a/">ссылка на себя же</a>',
        '<a href="/c/d/">вложенная</a>',
    ].join('');

    const links = internalLinks(html, '/a/');

    if (!links.has('/a/') && links.size === 0) {
        problems.push('internalLinks не нашёл ни одной ссылки');
    }

    if (!links.has('/c/d/')) {
        problems.push('internalLinks потерял вложенный путь /c/d/');
    }

    if (links.has('/b/')) {
        problems.push('internalLinks пропустил внешний хост');
    }

    const selfLinks = internalLinks('<a href="/a/">сама</a>', '/a/');

    if (selfLinks.has('/a/')) {
        problems.push('ссылка на себя считается входящей');
    }

    const paths = sitemapPaths('<urlset><url><loc>' + base + '/a/</loc></url><url><loc>https://other.example/x/</loc></url></urlset>');

    if (paths.length !== 1 || paths[0] !== '/a/') {
        problems.push('sitemapPaths: ' + JSON.stringify(paths));
    }

    const incoming = new Map([['/', 5], ['/a/', 1], ['/b/', 0], ['/404', 0]]);
    const orphans = orphansOf(['/', '/a/', '/b/', '/404'], incoming);

    if (JSON.stringify(orphans) !== JSON.stringify(['/b/'])) {
        problems.push('orphansOf: ' + JSON.stringify(orphans));
    }

    if (problems.length > 0) {
        console.error('FAIL: ' + problems.length + ' проблем самопроверки');
        for (const line of problems) {
            console.error('  - ' + line);
        }
        process.exit(1);
    }

    console.log('OK: самопроверка разбора ссылок и поиска сирот');
}

async function main() {
    if (process.argv.includes('--self-test')) {
        selfTest();
        return;
    }

    const xml = await fetchText(base + '/sitemap.xml');

    if (xml === null) {
        throw new Error('карта сайта недоступна');
    }

    const paths = sitemapPaths(xml);

    if (paths.length === 0) {
        throw new Error('в карте сайта нет адресов');
    }

    const unique = [...new Set(paths)];
    const incoming = new Map(unique.map((pathname) => [pathname, 0]));

    await mapLimit(unique, CONCURRENCY, async (pathname) => {
        const html = await fetchText(base + pathname);

        if (html === null) {
            return;
        }

        for (const link of internalLinks(html, pathname)) {
            if (incoming.has(link)) {
                incoming.set(link, incoming.get(link) + 1);
            }
        }
    });

    for (const pathname of orphansOf(unique, incoming)) {
        failures.push(`${pathname}: ни одной входящей ссылки`);
    }

    const allowed = ALLOWED_ORPHANS.filter((pathname) => unique.includes(pathname)).length;
    const checked = unique.length - allowed - 1;

    if (failures.length > 0) {
        console.error('FAIL: ' + failures.length + ' проблем');
        for (const line of failures) {
            console.error('  - ' + line);
        }
        process.exit(1);
    }

    console.log(`OK: ${checked} адресов, у каждого есть входящая ссылка (карта: ${unique.length}, исключения: ${ALLOWED_ORPHANS.join(', ') || 'нет'})`);
}

main().catch((error) => {
    console.error('FAIL: ' + error.message);
    process.exit(1);
});
