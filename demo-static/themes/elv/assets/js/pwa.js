/*!
 * PWA: регистрация service worker.
 *
 * Установка приложения и баннер «Установить» живут в разметке темы, а этот
 * файл отвечает только за регистрацию: без service worker сайт работает как
 * обычный, поэтому ошибка регистрации не показывается пользователю.
 * Требуется HTTPS или localhost.
 */

(function () {
    'use strict';

    if (!('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', function () {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () {
            // Офлайн-режим недоступен — остальной сайт работает.
        });
    });
})();
