#!/usr/bin/env node
/**
 * Контракт публичного JSON (`/api/v1/...`, P2-14) на живом сайте.
 *
 * Что проверяется:
 *   - каждый раздел из `Sitemap::SECTIONS` отвечает 200 `application/json`,
 *     `meta.section` совпадает с handle, `meta.count` — с длиной `data`;
 *   - у каждой записи есть обязательные поля контракта (`id`, `slug`, `title`,
 *     `excerpt`, `image`, `url`, `updated_at`), `url` абсолютный;
 *   - **каждый `url` из списка открывается 200**: API не отдаёт запись,
 *     которой нет в вёрстке (фильтр `applyVisibleFrontend` не разошёлся
 *     с темой);
 *   - `{handle}/{slug}` первой записи отвечает 200 с тем же `id`;
 *   - неизвестный handle — 404 в JSON, `limit=0` — 422.
 *
 *   node tools/check-api.mjs [--base=http://127.0.0.1:8080]
 *
 * Требуется запущенный `php artisan serve` (порт по умолчанию 8080).
 */

const baseArg = process.argv.find((arg) => arg.startsWith('--base='));
const base = (baseArg ? baseArg.slice('--base='.length) : 'http://127.0.0.1:8080').replace(/\/$/, '');

const SECTIONS = ['books', 'printing', 'news', 'services', 'events', 'collections'];

const REQUIRED = ['id', 'slug', 'title', 'excerpt', 'image', 'url', 'updated_at'];

const failures = [];

/**
 * getJson забирает JSON и возвращает пару «ответ, тело» либо null с ошибкой.
 */
async function getJson(path) {
    const response = await fetch(base + path);
    const text = await response.text();

    try {
        return { response, data: JSON.parse(text) };
    }
    catch {
        failures.push(`${path}: тело не JSON (${text.slice(0, 80)})`);
        return { response, data: null };
    }
}

function checkItem(path, item) {
    for (const field of REQUIRED) {
        if (!Object.prototype.hasOwnProperty.call(item, field)) {
            failures.push(`${path}: в записи нет поля ${field}`);
        }
    }

    if (!/^https?:\/\//.test(item.url || '')) {
        failures.push(`${path}: url не абсолютный (${item.url})`);
    }
}

async function main() {
    let total = 0;

    for (const section of SECTIONS) {
        const path = `/api/v1/${section}`;
        const { response, data } = await getJson(path);

        if (response.status !== 200) {
            failures.push(`${path}: HTTP ${response.status}`);
            continue;
        }

        if (!(response.headers.get('content-type') || '').includes('application/json')) {
            failures.push(`${path}: content-type ${response.headers.get('content-type')}`);
        }

        if (!data || !data.meta || data.meta.section !== section) {
            failures.push(`${path}: meta.section = ${data && data.meta ? data.meta.section : 'нет'}`);
            continue;
        }

        if (!Array.isArray(data.data) || data.meta.count !== data.data.length) {
            failures.push(`${path}: meta.count не совпадает с длиной data`);
            continue;
        }

        for (const item of data.data) {
            checkItem(path, item);
        }

        total += data.data.length;

        const first = data.data[0];

        if (!first) {
            continue;
        }

        const detail = await getJson(`${path}/${first.slug}`);

        if (detail.response.status !== 200) {
            failures.push(`${path}/${first.slug}: HTTP ${detail.response.status}`);
        }
        else if (!detail.data || !detail.data.data || detail.data.data.id !== first.id) {
            failures.push(`${path}/${first.slug}: id не совпал со списком`);
        }

        for (const item of data.data) {
            const page = await fetch(base + new URL(item.url).pathname, { redirect: 'manual' });

            if (page.status !== 200) {
                failures.push(`${new URL(item.url).pathname}: страница отдала ${page.status}`);
            }
        }
    }

    const unknown = await getJson('/api/v1/unknown');

    if (unknown.response.status !== 404) {
        failures.push(`/api/v1/unknown: HTTP ${unknown.response.status}, ожидался 404`);
    }

    const badLimit = await getJson('/api/v1/books?limit=0');

    if (badLimit.response.status !== 422) {
        failures.push(`/api/v1/books?limit=0: HTTP ${badLimit.response.status}, ожидался 422`);
    }

    if (failures.length > 0) {
        console.error('FAIL: ' + failures.length + ' проблем');
        for (const line of failures) {
            console.error('  - ' + line);
        }
        process.exit(1);
    }

    console.log(`OK: API ${SECTIONS.length} разделов, ${total} записей, поля контракта, страницы записей 200, 404/422 на ошибки`);
}

main().catch((error) => {
    console.error('FAIL: ' + error.message);
    process.exit(1);
});
