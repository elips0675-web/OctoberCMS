/*!
 * Service worker приложения.
 *
 * Стратегия:
 *  - оболочка (CSS/JS/иконки/manifest/offline) — cache-first, обновляется
 *    при смене VERSION;
 *  - переходы между страницами — network-first, офлайн отдаётся offline.html;
 *  - HTML-страницы не кешируются: на сайте есть авторизация и персональные
 *    данные, показывать чужой или устаревший ответ из кеша нельзя;
 *  - запросы в админку и служебные пути не перехватываются.
 *
 * VERSION и SHELL подставляет PwaController: версия — хеш времени правки
 * файлов оболочки, поэтому кеш обновляется сам, когда меняется ассет.
 */

const VERSION = '__VERSION__';
const SHELL = __SHELL__;
const SHELL_CACHE = `elv-shell-${VERSION}`;
const OFFLINE_URL = '/offline.html';

const STATIC_RE = /\.(?:css|js|mjs|png|jpe?g|gif|svg|webp|avif|ico|woff2?|ttf|otf|webmanifest)$/i;

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(SHELL_CACHE);
        // addAll падает целиком из-за одного 404 — кладём по одному.
        await Promise.all(SHELL.map(async (url) => {
            try {
                await cache.add(new Request(url, { cache: 'reload' }));
            } catch (error) {
                // Файл мог не попасть в сборку — установка из-за этого не отменяется.
            }
        }));
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(keys
            .filter((key) => key !== SHELL_CACHE && key.startsWith('elv-'))
            .map((key) => caches.delete(key)));
        await self.clients.claim();
    })());
});

self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

function isSkipped(url) {
    if (url.pathname.startsWith('/admin/')) return true;
    if (url.search.toLowerCase().includes('logout')) return true;
    return false;
}

async function cacheFirst(request) {
    const cache = await caches.open(SHELL_CACHE);
    const cached = await cache.match(request, { ignoreSearch: true });
    if (cached) return cached;

    try {
        const response = await fetch(request);
        if (response && response.ok && response.type === 'basic') {
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        return new Response('', { status: 504, statusText: 'Offline' });
    }
}

async function networkFirstNavigation(request) {
    try {
        return await fetch(request);
    } catch (error) {
        const cache = await caches.open(SHELL_CACHE);
        const offline = await cache.match(OFFLINE_URL);
        return offline || new Response('<!doctype html><meta charset="utf-8"><title>Нет сети</title><p>Нет подключения к интернету.', {
            status: 200,
            headers: { 'Content-Type': 'text/html; charset=utf-8' }
        });
    }
}

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;
    if (isSkipped(url)) return;

    if (request.mode === 'navigate') {
        event.respondWith(networkFirstNavigation(request));
        return;
    }

    if (STATIC_RE.test(url.pathname)) {
        event.respondWith(cacheFirst(request));
    }
});

/* ------------------------------------------------------------------
 * Web Push: уведомления о бронировании.
 *
 * Payload: { title, body, url, tag, icon }. Текст приходит готовым:
 * уведомление показывает система, а не страница.
 * ------------------------------------------------------------------ */

const NOTIFICATION_ICON = '/themes/elv/assets/icons/icon-192.png';

self.addEventListener('push', (event) => {
    let payload = {};
    if (event.data) {
        try {
            payload = event.data.json();
        } catch (error) {
            payload = { body: event.data.text() };
        }
    }

    const title = payload.title || '__BRAND__';
    const options = {
        body: payload.body || '',
        icon: payload.icon || NOTIFICATION_ICON,
        badge: NOTIFICATION_ICON,
        tag: payload.tag || 'elv-notification',
        renotify: true,
        data: { url: payload.url || '/' }
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const target = new URL(
        (event.notification.data && event.notification.data.url) || '/',
        self.location.origin
    ).href;

    event.waitUntil((async () => {
        const clientList = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const client of clientList) {
            if (client.url === target && 'focus' in client) {
                return client.focus();
            }
        }
        if (self.clients.openWindow) {
            return self.clients.openWindow(target);
        }
        return undefined;
    })());
});
