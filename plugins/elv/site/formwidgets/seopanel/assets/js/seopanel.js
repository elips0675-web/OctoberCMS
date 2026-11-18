/**
 * seopanel.js — предпросмотр сниппета и живой пересчёт SEO-анализа.
 *
 * Текст правил живёт в PHP (`SeoAnalyzer`): скрипт только собирает текущие
 * значения формы и просит сервер пересчитать панель через обработчик виджета.
 * Предпросмотр сниппета считается на месте — это длины, а не правила.
 */
(function () {
    'use strict';

    var TITLE_LIMIT = 60;
    var DESCRIPTION_LIMIT = 155;
    var DEBOUNCE_MS = 700;

    function truncate(text, limit) {
        text = String(text || '').replace(/\s+/g, ' ').trim();
        if (text.length <= limit) {
            return text;
        }

        return text.slice(0, limit - 1).replace(/[\s,;:.-]+$/, '') + '…';
    }

    function fieldValue(form, selector) {
        var element = form.querySelector(selector);

        return element ? String(element.value || '') : '';
    }

    function contentValue(form) {
        var element = form.querySelector('.field-richeditor textarea[data-richeditor-textarea]')
            || form.querySelector('.field-richeditor textarea')
            || form.querySelector('[name$="[content]"]');

        return element ? String(element.value || '') : '';
    }

    function collect(form) {
        return {
            title: fieldValue(form, '[name$="[title]"]'),
            seo_title: fieldValue(form, '[name$="[seo_title]"]'),
            seo_description: fieldValue(form, '[name$="[seo_description]"]'),
            slug: fieldValue(form, '[name$="[slug]"]'),
            keyphrase: fieldValue(form, '[data-seo-source="keyphrase"]'),
            content: contentValue(form)
        };
    }

    function counter(root, name, length, limit) {
        var element = root.querySelector('[data-seo-counter="' + name + '"]');
        if (!element) {
            return;
        }

        element.textContent = length + ' / ' + limit;
        element.classList.toggle('is-over', length > limit);
    }

    function updatePreview(root, data) {
        var slug = String(data.slug || '').replace(/^\/+|\/+$/g, '');
        var url = root.querySelector('[data-seo-preview="url"]');
        var title = root.querySelector('[data-seo-preview="title"]');
        var description = root.querySelector('[data-seo-preview="description"]');

        var effectiveTitle = data.seo_title || data.title;
        var effectiveDescription = data.seo_description;

        if (url) {
            url.textContent = slug ? '/' + slug + '/' : '/…';
        }

        if (title) {
            title.textContent = truncate(effectiveTitle || 'Заголовок страницы', TITLE_LIMIT);
        }

        if (description) {
            description.textContent = truncate(
                effectiveDescription || 'Описание для поиска не заполнено.',
                DESCRIPTION_LIMIT
            );
        }

        counter(root, 'title', String(effectiveTitle || '').length, TITLE_LIMIT);
        counter(root, 'description', String(effectiveDescription || '').length, DESCRIPTION_LIMIT);
    }

    function initRoot(root) {
        if (root.dataset.seoReady === '1') {
            return;
        }

        var form = root.closest('form');
        var handler = root.dataset.seoHandler;
        var panelId = root.dataset.seoPanel;

        if (!form || !handler || !panelId) {
            return;
        }

        root.dataset.seoReady = '1';

        var lastSent = '';
        var timer = null;
        var pending = false;

        function refreshPanel() {
            if (pending) {
                return;
            }

            var data = collect(form);
            var signature = JSON.stringify(data);
            if (signature === lastSent) {
                return;
            }

            if (!window.jQuery || typeof window.jQuery.fn.request !== 'function') {
                return;
            }

            pending = true;
            lastSent = signature;

            var request = { data: data, update: {} };
            request.update['_analysis'] = '#' + panelId;

            window.jQuery(form).request(handler, request).always(function () {
                pending = false;
            });
        }

        function schedule() {
            var data = collect(form);
            updatePreview(root, data);

            window.clearTimeout(timer);
            timer = window.setTimeout(refreshPanel, DEBOUNCE_MS);
        }

        form.addEventListener('input', schedule, true);
        form.addEventListener('change', schedule, true);

        var richTextareas = form.querySelectorAll('textarea[data-richeditor-textarea]');
        if (window.jQuery && richTextareas.length) {
            window.jQuery(richTextareas).on('froalaEditor.contentChanged syncContent.oc.richeditor', schedule);
        }

        schedule();
    }

    function initAll() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-seopanel]'), initRoot);
    }

    document.addEventListener('DOMContentLoaded', initAll);
    document.addEventListener('turbo:load', initAll);
    document.addEventListener('page:load', initAll);
    window.setTimeout(initAll, 600);
})();
