#!/usr/bin/env node
/**
 * Health-эндпоинт живого сайта (P1-21, Stage 13).
 *
 * Что проверяется:
 *   - `/health` — 200 `application/json`, `status: "ok"`, все три компонента
 *     (`db`, `cache`, `queue`) отвечают true;
 *   - `Cache-Control` содержит `no-store` (пробу нельзя кэшировать);
 *   - нет `Set-Cookie` (проба не должна плодить сессии);
 *   - в теле нет DSN, SQL, путей и текста исключений: 503 обязан объяснять
 *     отказ именами компонентов, а не `SQLSTATE[…]`.
 *
 *   node tools/check-health.mjs [--base=http://127.0.0.1:8080]
 *
 * Требуется запущенный `php artisan serve`. Отказ БД здесь не воспроизводится
 * (останавливать базу под живым сервером нельзя) — этот путь закрыт
 * `tests/HealthTest.php`.
 */

const baseArg = process.argv.find((arg) => arg.startsWith('--base='));
const base = (baseArg ? baseArg.slice('--base='.length) : 'http://127.0.0.1:8080').replace(/\/$/, '');

const leaks = ['SQLSTATE', 'mysql', 'password', '127.0.0.1', 'Exception', 'vendor/', 'storage/'];

async function main() {
    const failures = [];

    const response = await fetch(base + '/health');
    const body = await response.text();

    if (response.status !== 200) {
        failures.push(`/health: HTTP ${response.status}`);
    }
    if (!/application\/json/.test(response.headers.get('content-type') || '')) {
        failures.push(`/health: тип ${response.headers.get('content-type')}`);
    }
    if (!/no-store/.test(response.headers.get('cache-control') || '')) {
        failures.push(`/health: Cache-Control «${response.headers.get('cache-control')}», ожидался no-store`);
    }
    if (response.headers.get('set-cookie')) {
        failures.push('/health: ответ ставит cookie — маршрут попал в сессионную группу');
    }

    let payload = null;
    try {
        payload = JSON.parse(body);
    }
    catch (error) {
        failures.push('/health: не разбирается как JSON (' + error.message + ')');
    }

    if (payload) {
        if (payload.status !== 'ok') {
            failures.push(`/health: status «${payload.status}»`);
        }
        for (const component of ['db', 'cache', 'queue']) {
            if (payload.checks?.[component] !== true) {
                failures.push(`/health: ${component} — ${JSON.stringify(payload.checks?.[component])}`);
            }
        }
        if (!payload.time) {
            failures.push('/health: нет отметки времени');
        }
    }

    const lower = body.toLowerCase();
    for (const needle of leaks) {
        if (lower.includes(needle.toLowerCase())) {
            failures.push(`/health: в теле есть «${needle}»`);
        }
    }

    if (failures.length > 0) {
        console.error('FAIL: ' + failures.length + ' проблем');
        for (const line of failures) {
            console.error('  - ' + line);
        }
        process.exit(1);
    }

    console.log(`OK: /health 200, db/cache/queue живы, no-store, без cookie и внутренних деталей (${payload.time})`);
}

main().catch((error) => {
    console.error('FAIL: ' + error.message);
    process.exit(1);
});
