# NLB on October CMS

Сайт библиотеки «Библиотека 2026» (Минск), перенесённый с **1С-Битрикс** на
**October CMS (Laravel + Tailor)**.

Источник переезда — `D:\nlbbootstrap-main` (только чтение): шаблон `nlb_bootstrap`,
110 статических страниц, 9 инфоблоков, 74 свойства, 91 элемент, BM25-поиск, чат, PWA.

## Статус

| Этап | Состояние |
|---|---|
| 0. Разведка | **сделана**: числа сверены, карта переноса собрана |
| 1. Установка October | **сделана 10.11.2026**: v4.4.13, Laravel 12.71.0, БД `nlb_october`, 1 171 тест ядра зелёный |
| 2. Плагин `nlb/library` и импорт контента | **сделана 10.11.2026**: 9 blueprints, 91 элемент, 12 разделов |
| 3. Заявки, слоты, гонка слота (P1-20) | **сделана 10.11.2026**: `BookingService`, `UNIQUE` + `lockForUpdate`, 29 тестов |
| 4. Вёрстка и 110 URL | **сделана 11.11.2026**: 109×200 + 404, DOM и текст 110/110 |
| 5. Формы | **сделана 11.11.2026**: 4 формы, записи в БД, письма в очереди |
| 6. Поиск | **не начат** (M8): класса `Search` и команды `nlb:search-index` ещё нет |
| 7. API и слоты | **не начат** (P1-16): `POST /api/bookings` не написан |
| 8. Производительность (Lighthouse 100) | **сделана 11.11.2026**: 4 прогона 100/100/100/100 (FCP 0,9 с, LCP 1,7 с, CLS 0) |
| 9. Документация и бэклог | ведётся постоянно |

Версия October: **4.4.13** (Packagist, релиз 01.11.2026), Laravel **12.71.0**,
PHP **8.3.30**. Ветка 4.x выбрана владельцем 10.11.2026 вместо 3.x LTS:
у 3.x поддержка только security-фиксы и Laravel 9, у 4.x — активная поддержка
и Laravel 12. Версия зафиксирована в `composer.lock`.
Хостинг ещё не выбран — пункт M1 в `Что доделать.txt`.

## Быстрый старт

Двойной щелчок по **`запуск.bat`** в корне: находит PHP 8.3 (PATH или
`C:\laragon\bin\php`), проверяет порт 3306 и при необходимости поднимает
`mysqld`, запускает `php artisan serve` на 8080, ждёт `/health` и открывает
браузер. Ключ `/nobrowser` — без открытия браузера. Остановка — закрыть
свёрнутые окна «NLB October 8080» и «NLB MySQL».

Вручную:

```powershell
$php = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
$env:PATH = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;' + $env:PATH
Set-Location 'D:\October CMS'
php artisan october:migrate
php artisan tailor:migrate            # таблицы контента Tailor
php artisan nlb:import --source="D:/nlbbootstrap-main/db/config/data"
php artisan serve --host=127.0.0.1 --port=8080
```

`nlb:search-index` появится вместе с поиском (M8) — сейчас команды нет.

Тесты плагина: `php vendor/phpunit/phpunit/phpunit -c phpunit.nlb.xml`.

Подробно — `docs/installation.md` (там же три грабли 4.x: PATH, `theme:seed`,
`october:about` вместо `october:version`).

## Админка

`http://127.0.0.1:8080/admin`, логин `admin`, email `admin@nlb.local`
(создан 10.11.2026, superuser). **Пароль в репозитории не хранится** — он
записан в `.local/admin-credentials.md`, каталог исключён в `.gitignore`.
Сброс: `php artisan october:passwd admin`. Подробности — `docs/installation.md` §7.

## Команды

| Команда | Что делает |
|---|---|
| `php vendor/phpunit/phpunit/phpunit` | PHPUnit ядра на реальной БД (`artisan test` в 4.x нет) |
| `php vendor/phpunit/phpunit/phpunit -c phpunit.nlb.xml` | тесты плагина на живой MySQL (216 тестов) |
| `php artisan october:about` | версия October, Laravel, PHP (`october:version` нет) |
| `php artisan october:fresh` | **сносит контент**: схема + демо с нуля; для этапа 4 не использовался |
| `php artisan migrate:fresh` | схема с нуля |
| `php artisan nlb:import` | идемпотентный импорт 91 элемента и 12 разделов |
| `php artisan nlb:slots-generate` | генерация слотов услуг |
| `node tools/purge-css.mjs` | урезание `bootstrap.min.css` |
| `node tools/check-css-budget.mjs` | бюджет встроенного CSS и отсутствие внешних таблиц |
| `node tools/check-urls.mjs` | 110 адресов на живом `serve`: 109×200 + 404 |
| `node tools/check-dom-parity.mjs` | сверка title/h1/h2/карточек/текста с `preview/` |
| `node tools/check-seo.mjs` | robots.txt, sitemap.xml (106 адресов), 301, JSON-LD и `BreadcrumbList` на живом `serve` |
| `node tools/check-orphans.mjs` | у каждого адреса карты сайта есть входящая ссылка (`--self-test` — самопроверка) |
| `node tools/check-api.mjs` | `/api/v1/*`: 6 разделов, 91 запись, поля контракта, страницы записей 200, 404/422 |
| `node tools/check-headers.mjs` | CSP с nonce, `X-Frame-Options`, админка без CSP на живом `serve` |
| `node tools/check-pwa.mjs` | manifest, версия и `SHELL` в `sw.js`, офлайн-страница на живом `serve` |
| `node tools/check-health.mjs` | `/health` 200, `no-store`, без cookie и текста исключений |
| `php artisan nlb:health-report` | JSON-строка состояния в `storage/logs/health.log` |
| `php tools/build-image-variants.php` | WebP/AVIF, 480/960/1280 |

## Документация

| Файл | Роль |
|---|---|
| `AGENTS.md` | правила работы агента: границы, гейты, конец этапа |
| `project-context.md` | стек, решения, структура, точки интеграции |
| `docs/README.md` | карта документации |
| `docs/architecture.md` | плагин, Tailor vs Eloquent, таблица переноса 9 инфоблоков |
| `docs/db.md` | схема, поля, перечисления, `UNIQUE` слотов, импорт |
| `docs/october-integration.md` | шапка, подвал, меню, формы, поиск, чат, push |
| `docs/installation.md` | установка, `.env`, artisan, сбои |
| `docs/security-performance.md` | CSP, бюджеты, картинки, протокол Lighthouse |
| `docs/design-system.md` | токены и запреты на изменение дизайна |
| `docs/content.md` | инвентарь 110 URL, разделы, где живёт контент |
| `docs/plugins.md` | лицензия и Winter CMS, что ставим из плагинов и что нет, чего не хватает до блога/отзывов |
| `docs/free-plan.md` | план «бесплатно»: лицензия, плагины, хостинг, почта, чек-лист запуска и риски free-tier |
| `docs/qa-checklist.md` | приёмка этапов |
| `docs/roadmap.md` | внешняя оценка: принято, отложено, отклонено |
| `Что сделать.txt` → `Что сделано.txt` | журнал: что, где, чем проверено, что не проверено |
| `Что доделать.txt` | только открытые пункты |

## Ключевые ограничения

- URL, тексты, разметка, классы — 1:1 с `preview/`. 110 адресов обязаны отвечать 200.
- Bootstrap 5.3.3, инлайн CSS ≤ 56,4 КБ, ноль внешних CSS-запросов, системные шрифты.
- Lighthouse 100/100/100/100 на мобильном профиле, три прогона подряд через gzip.
- Один плагин `nlb/library`; никаких демо-плагинов, RainLab.Blog, Tailwind, React, Vite.
- Секреты — только `.env`; в репозитории `.env.example`.
- Источник не меняется, пока переезд не сдан.

## Лицензия

October CMS — self-host. Ключ/аккаунт оформляет владелец; исполнитель не покупает
и не регистрирует ничего от его имени.
