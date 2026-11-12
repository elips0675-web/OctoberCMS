#!/usr/bin/env node
/**
 * PWA-оболочка живого сайта: manifest, service worker и офлайн-страница.
 *
 * Что проверяется (M10, Stage 12):
 *   - `/manifest.webmanifest` — 200 `application/manifest+json`, JSON
 *     разбирается, есть `id`/`name`/`start_url`/`scope`/`display`, каждая
 *     иконка и каждый shortcut отвечают 200;
 *   - `/sw.js` — 200, `javascript`, `Service-Worker-Allowed: /`, подставлены
 *     версия (8 hex) и список `SHELL`, плейсхолдеров не осталось, каждый файл
 *     оболочки отвечает 200, в списке есть `/offline.html` и manifest;
 *   - `/offline.html` — 200 `text/html`, без `<script>` и `onclick=`
 *     (их заблокирует CSP Stage 10), есть ссылка на главную;
 *   - на главной есть `<link rel="manifest">` и скрипт регистрации
 *     `assets/js/pwa.js`.
 *
 *   node tools/check-pwa.mjs [--base=http://127.0.0.1:8080]
 *
 * Требуется запущенный `php artisan serve` (порт по умолчанию 8080).
 */

const baseArg = process.argv.find((arg) => arg.startsWith('--base='));
const base = (baseArg ? baseArg.slice('--base='.length) : 'http://127.0.0.1:8080').replace(/\/$/, '');

async function main() {
    const failures = [];

    const manifestResponse = await fetch(base + '/manifest.webmanifest');
    const manifestBody = await manifestResponse.text();

    if (manifestResponse.status !== 200) {
        failures.push(`/manifest.webmanifest: HTTP ${manifestResponse.status}`);
    }
    if (!/application\/manifest\+json/.test(manifestResponse.headers.get('content-type') || '')) {
        failures.push(`/manifest.webmanifest: тип ${manifestResponse.headers.get('content-type')}`);
    }

    let manifest = null;
    try {
        manifest = JSON.parse(manifestBody);
    }
    catch (error) {
        failures.push('/manifest.webmanifest: не разбирается как JSON (' + error.message + ')');
    }

    if (manifest) {
        for (const field of ['id', 'name', 'short_name', 'start_url', 'scope', 'display', 'lang']) {
            if (!manifest[field]) {
                failures.push(`/manifest.webmanifest: нет поля ${field}`);
            }
        }

        if (manifest.scope !== '/') {
            failures.push(`/manifest.webmanifest: scope «${manifest.scope}», ожидался «/»`);
        }

        if (!Array.isArray(manifest.icons) || manifest.icons.length < 2) {
            failures.push('/manifest.webmanifest: меньше двух иконок');
        }

        for (const icon of manifest.icons || []) {
            const response = await fetch(base + icon.src);
            if (response.status !== 200) {
                failures.push(`иконка ${icon.src}: HTTP ${response.status}`);
            }
            if (!/image\//.test(response.headers.get('content-type') || '')) {
                failures.push(`иконка ${icon.src}: тип ${response.headers.get('content-type')}`);
            }
        }

        for (const shortcut of manifest.shortcuts || []) {
            const response = await fetch(base + shortcut.url);
            if (response.status !== 200) {
                failures.push(`shortcut ${shortcut.url}: HTTP ${response.status}`);
            }
        }
    }

    const workerResponse = await fetch(base + '/sw.js');
    const workerBody = await workerResponse.text();

    if (workerResponse.status !== 200) {
        failures.push(`/sw.js: HTTP ${workerResponse.status}`);
    }
    if (!/javascript/.test(workerResponse.headers.get('content-type') || '')) {
        failures.push(`/sw.js: тип ${workerResponse.headers.get('content-type')}`);
    }
    if (workerResponse.headers.get('service-worker-allowed') !== '/') {
        failures.push('/sw.js: нет Service-Worker-Allowed: /');
    }
    if (workerBody.includes('__VERSION__') || workerBody.includes('__SHELL__')) {
        failures.push('/sw.js: не подставлены VERSION или SHELL');
    }

    const version = /const VERSION = '([0-9a-f]{8})';/.exec(workerBody);
    if (!version) {
        failures.push('/sw.js: нет 8-символьной версии кеша');
    }

    const shellMatch = /const SHELL = (\[[^\]]*\]);/.exec(workerBody);
    if (!shellMatch) {
        failures.push('/sw.js: нет списка SHELL');
    }
    else {
        let shell = [];
        try {
            shell = JSON.parse(shellMatch[1]);
        }
        catch (error) {
            failures.push('/sw.js: SHELL не разбирается (' + error.message + ')');
        }

        for (const url of shell) {
            const response = await fetch(base + url);
            if (response.status !== 200) {
                failures.push(`оболочка ${url}: HTTP ${response.status}`);
            }
        }

        for (const required of ['/offline.html', '/manifest.webmanifest']) {
            if (!shell.includes(required)) {
                failures.push(`/sw.js: в SHELL нет ${required}`);
            }
        }
    }

    if (!workerBody.includes("url.pathname.startsWith('/admin/')")) {
        failures.push('/sw.js: админка не исключена из перехвата');
    }

    const offlineResponse = await fetch(base + '/offline.html');
    const offlineBody = await offlineResponse.text();

    if (offlineResponse.status !== 200) {
        failures.push(`/offline.html: HTTP ${offlineResponse.status}`);
    }
    if (!/text\/html/.test(offlineResponse.headers.get('content-type') || '')) {
        failures.push(`/offline.html: тип ${offlineResponse.headers.get('content-type')}`);
    }
    if (offlineBody.includes('<script')) {
        failures.push('/offline.html: инлайновый <script> заблокирует CSP');
    }
    if (offlineBody.includes('onclick=')) {
        failures.push('/offline.html: инлайновый обработчик заблокирует CSP');
    }
    if (!/href="\/"/.test(offlineBody)) {
        failures.push('/offline.html: нет ссылки на главную');
    }

    const home = await (await fetch(base + '/')).text();

    if (!/<link rel="manifest" href="https?:\/\/[^"]+\/manifest\.webmanifest">/.test(home)) {
        failures.push('главная: нет <link rel="manifest">');
    }
    if (!/assets\/js\/pwa\.js/.test(home)) {
        failures.push('главная: нет скрипта регистрации service worker');
    }

    if (failures.length > 0) {
        console.error('FAIL: ' + failures.length + ' проблем');
        for (const line of failures) {
            console.error('  - ' + line);
        }
        process.exit(1);
    }

    console.log(`OK: manifest (${manifest.icons.length} иконки, ${manifest.shortcuts.length} shortcut), sw.js версии ${version[1]}, офлайн-страница и регистрация на главной`);
}

main().catch((error) => {
    console.error('FAIL: ' + error.message);
    process.exit(1);
});
