# NLB on October CMS — Project Context

## 1. Что это

Сайт библиотеки (Библиотека 2026, Минск) переносится с 1С-Битрикс на **October CMS
(Laravel + Tailor)**. Переносится не дизайн, а платформа: разметка, тексты, URL и CSS
остаются 1:1 с источником `D:\nlbbootstrap-main`.

Источник правды по внешнему виду и контенту на время переезда — репозиторий источника:
`preview/**` (110 страниц), `local/templates/nlb_bootstrap/styles.css` (81 179 байт),
`script.js` (75 359 байт), `assets/css/bootstrap.min.css` (61 433 байта).

## 2. Стек

| Слой | Технология | Где |
|---|---|---|
| CMS | October CMS **4.4.13**, Laravel 12.71.0 | `composer.lock` в git |
| Язык | PHP 8.3.30 (Laragon, ZTS) | `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe` |
| Фреймворк | Laravel 12.71.0 (внутри October): Eloquent, миграции, очереди, планировщик | `vendor/laravel` |
| Контент | Tailor blueprints (6 сущностей) | `plugins/nlb/library/blueprints/` |
| Заявки | Eloquent-модели + миграции (3 сущности) | `plugins/nlb/library/models/`, `updates/` |
| CSS-фреймворк | Bootstrap 5.3.3, урезанный `tools/purge-css.mjs`, инлайн | layout темы |
| Свой CSS | `styles.css` без переписывания + минификация на PHP | тема |
| JS | `bootstrap.bundle.min.js` + `script.js` как есть | тема |
| Поиск | свой Okapi BM25, индекс в БД, команда переиндексации | `plugins/nlb/library/classes/Search` |
| БД | MySQL 8.4.3, `127.0.0.1:3306`, БД `nlb_october` | `.env` |
| Письма | `Mail` + `ShouldQueue`; SMTP у владельца нет → `MAIL_MAILER=log` | `.env` |
| PWA | `manifest.webmanifest`, `sw.js`, `offline.html` — файлы в `themes/nlb/assets/pwa/`, маршруты с корня | `classes/Pwa.php`, `classes/Http/PwaController.php` |
| Сборка | `tools/*` (purge-css, build-image-variants, гейты) | `tools/` |
| Тесты | PHPUnit + `RefreshDatabase` | `plugins/nlb/library/tests/` |

Заглушка ядра `db/tests/stub/bitrix.php` (31 750 байт) **выбрасывается**: тесты пишутся
на PHPUnit с реальной БД, данные — фабрики.

## 3. Принятые решения (не пересматривать)

1. **Один плагин** `nlb/library`, vendor `nlb`. Никаких демо-плагинов и RainLab.Blog.
2. **Tailor — только контент**: `news`, `events`, `collections`, `books`, `services`,
   `printing`. Read-mostly сущности правятся blueprints-полями в админке.
3. **Заявки — Eloquent**: `requests`, `bookings`, `orders` + миграции. Причина: транзакции,
   `UNIQUE`, политики, статусный переход `new → confirmed → cancelled`, идемпотентность, тесты.
4. **Bootstrap 5.3.3 остаётся**, CSS инлайн, ноль внешних CSS-запросов.
5. **Шрифты системные**, как в источнике. Google Fonts не подключать.
6. **URL, тексты, разметка, классы — 1:1.** Переименование URL — только по согласованию.
7. **Секреты только в `.env`**, в репозитории `.env.example`.
8. **Лицензия October — self-host.** Первая лицензия бесплатна (включая
   коммерческие проекты), обновления — $39/год после первого года; скачанные
   версии остаются навсегда, но Update Gateway требует активной лицензии.
   Ключ/аккаунт оформляет владелец: без его письменного решения ничего не
   покупаем и не регистрируем. Перед релизом — архив установки.
   Платные плагины не нужны ни для одной задачи проекта: бесплатный контур
   (свой код, free-tier хостинг, бесплатный SMTP) — `docs/free-plan.md`;
   единственная неизбежная плата — домен.
9. **Winter CMS не берём.** Форк October 1.0 на Laravel 9 (security-фиксы
   закончились 06.02.2024, в его `composer.json` они прямо заглушены), Tailor
   отсутствует — девять blueprints и `type: structure` пришлось бы переписать.
   Подробно — `docs/plugins.md`, раздел 2.
10. **Ветка October — 4.x (4.4.13), не 3.x LTS.** Решение владельца 10.11.2026:
    у 3.x только security-фиксы и Laravel 9, у 4.x активная поддержка и Laravel 12,
    Tailor на месте. Документация 4.x — `docs.octobercms.com/4.x/`.
11. **PHP 8.3.30**, не 8.5.12: Laravel 12 официально тестируется на 8.2–8.4,
    на 8.5 больше deprecation-шума; 8.4 нет в Laragon, её ставим отдельно при
    выборе хостинга. `ext-zip` включён в `php.ini` — требование 4.x.

## 4. Структура каталога (цель)

```
plugins/nlb/library/
  Plugin.php
  blueprints/            # 6 контентных сущностей (Tailor)
  models/                # Request, Booking, Order, Slot, Subscriber, ...
  updates/               # миграции Eloquent-таблиц
  classes/               # Search (BM25), TextFilter, Media, Seo, Booking, Push
  components/            # Header, Footer, Menu, Breadcrumbs, Forms, Search, Chat, Picture
  console/               # nlb:import, nlb:search-index, nlb:slots-generate
  http/                  # контроллеры (search, chat, api)
  routes.php             # web/ajax/api маршруты
  tests/                 # PHPUnit + RefreshDatabase
themes/nlb/
  layouts/default.htm    # инлайн CSS, спрайт, мета
  pages/                 # CMS-страницы: index, about, contacts, 404, ...
  partials/              # списки, карточки, крошки
  assets/                # картинки, шрифты, js темы
  assets/pwa/            # manifest.webmanifest, sw.js, offline.html — отдаются с корня маршрутом
index.php, .htaccess     # корень проекта = веб-корень (public/ в 4.x нет)
                         # robots.txt и sitemap.xml — тоже маршруты плагина, не файлы
tools/                   # purge-css.mjs, build-image-variants.php, гейты
docs/                    # документация (карта — docs/README.md)
```

## 5. Что переносится и в каком объёме

| Источник | Объём (проверено) | Куда |
|---|---|---|
| `db/config/iblocks.php` | 9 инфоблоков, 74 свойства | `docs/architecture.md` (таблица), blueprints + модели |
| `db/config/data/*.php` | 91 элемент, 12 разделов | БД October, команда `nlb:import` |
| `preview/**` | 110 HTML | CMS-страницы + маршруты `{slug}` |
| `db/migrations/*.php` | 13 файлов | Laravel-миграции (схема пересматривается, не копируется) |
| `local/php_interface/include/*` | 8 файлов, 4 585 строк | классы плагина |
| `local/templates/nlb_bootstrap/*` | header 269, footer 323 строки, CSS 81 179, JS 75 359 | layout, компоненты, ассеты |
| `db/menu/*.menu.php` | 4 меню | компонент меню / Tailor-навигация |
| `urlrewrite.php` | 7 правил | `routes.php` |
| `db/search/index.json` | 99 документов, 1 340 терминов | индекс в БД + команда переиндексации |

## 6. Карта страниц (110 URL)

| Раздел | Список + детальные | Всего |
|---|---|---|
| `books` | 1 + 24 | 25 |
| `printing` | 1 + 37 | 38 |
| `news` | 1 + 10 | 11 |
| `services` | 1 + 8 | 9 |
| `events` | 1 + 6 | 7 |
| `collections` | 1 + 6 | 7 |
| корень | about, auth, contacts, feedback, index, personal, privacy, rules, search, sitemap, subscribe, terms, 404 | 13 |
| **итого** | | **110** |

## 7. Точки интеграции (аналог `bitrix-integration.md`)

| Что | Источник | Куда в October |
|---|---|---|
| Меню `top` | `header.php:172` | компонент `Menu`, данные — Tailor/навигация |
| Крошки | `header.php:256` | компонент `Breadcrumbs` |
| Поиск (модалка) | `header.php:228` | компонент `Search` + `GET /search/?q=` + `/ajax/search` |
| Инлайн CSS | `header.php:70` | layout: `<style>` с минификацией и кешем по `mtime` |
| Спрайт иконок | `header.php:79` | partial спрайта (переносится как есть) |
| Подписка | `footer.php:92` | компонент `Subscribe` (double opt-in) |
| Обратная связь | `footer.php:258` | компонент `Feedback` → модель `Request` |
| Чат, FAB, модалки | `footer.php:169-281` | partial + `script.js` как есть |
| Скрипты | `footer.php:308-321` | layout: bundle, script.js, pwa.js, push.js, consent.js |
| Правила чата | `script.js:169` (11 правил), `script.js:281` (10 подсказок) | данные в плагине, не `if` в JS |
| Чат-эндпоинт | `script.js:155-156` (`USE_REAL_AI=false`, `/api/chat`) | контроллер `/api/chat` (правила + оператор) |
| SEO | `nlb_seo.php` | `classes/Seo.php` + layout: meta, OG/Twitter, canonical, JSON-LD `Library`/`WebSite` + узел записи; `/rss.xml` — `classes/Rss.php` (Stage 11) |
| Заголовки | `nlb_security.php` | `classes/Http/SecurityHeaders.php`: CSP с nonce, `X-Frame-Options`, HSTS; статические — ещё и `nginx.conf` (Stage 10) |

## 8. Ограничения и что ещё не проверено

- **October установлен, контент в БД, сайт собран.** v4.4.13, Laravel 12.71.0,
  БД `nlb_october`. Плагин `nlb/library` версии 1.1.0: 9 blueprints,
  `nlb:import` перенёс 91 элемент и 12 разделов, тесты плагина — 231 зелёный
  (`phpunit.nlb.xml`, 889 утверждений). Тема `nlb` — активная (`ACTIVE_THEME=nlb`), демо-тема
  и демо-плагин удалены; 109 адресов и 404 отвечают, DOM и текст совпадают
  с `preview/` (гейты `tools/check-urls.mjs`, `tools/check-dom-parity.mjs`),
  `/sitemap.xml` (106 адресов), `/robots.txt`, 301, JSON-LD и `BreadcrumbList` —
  `tools/check-seo.mjs`, входящие ссылки — `tools/check-orphans.mjs`;
  мета записи и микроразметка `Article`/`Event`/`Product`/`Service` —
  `tests/SeoTest.php` (67 тестов). Публичный JSON контента —
  `GET /api/v1/{handle}` и `/api/v1/{handle}/{slug}`
  (`plugins/nlb/library/classes/Http/ApiController.php`, гейт
  `tools/check-api.mjs`): 6 разделов, 91 запись, только опубликованное.
- **Формы, админка, SEO и заголовки подключены** (этапы 5–10): `POST /feedback/`,
  `/booking/`, `/order/`, `/subscribe/`, подтверждение и отписка; админка
  «Библиотека» со статусами и освобождением слота; sitemap, robots, 301,
  JSON-LD, мета записи из blueprints и узел записи в `@graph`; CSP с nonce,
  `X-Frame-Options`, HSTS-условие и статические заголовки в `nginx.conf`;
  `/health` (БД, кэш, очередь), часовой `nlb:health-report` и ежедневная
  генерация слотов по расписанию.
  **Поиск, чат и внешние API — ещё нет** (M8, P1-16, P1-17).
- **Живой SMTP отсутствует** — письма проверяются через `MAIL_MAILER=log`; код
  обязан быть готов к реальному SMTP без правок.
- **Демо-контент.** Все тексты, контакты, цены, события, фото — заглушки.
- **Только светлая тема.** Решение владельца, `prefers-color-scheme` не подключается.
- **Источник не имеет истории заявок**: `requests`, `bookings`, `orders` пусты, переносить
  нечего — проверено в бэклоге источника.
- **Числа, которые в промте разошлись с фактом**: `header.php` 269 (не 251),
  `footer.php` 323 (не 305), правил чата 11 (не 10). Замер — Этап 0, запись в `Что сделано.txt`.
- **Админ-пользователь создан 10.11.2026**: логин `admin`, email `admin@nlb.local`,
  superuser. Пароль — только в `.local/admin-credentials.md` (каталог в `.gitignore`),
  в документацию и git не попадает. Вход проверен реальным `POST
  /admin/backend/auth/signin` → `/admin/dashboard` (200). `adminAuth` в October
  штатный, dev-логина нет.

## 9. Соглашения

- Классы плагина — namespace `Nlb\Library\...`, PSR-4.
- Модели — единственное число (`Booking`), таблицы — множественное (`nlb_bookings`).
- Blueprint-хендлы — как коды инфоблоков: `news`, `events`, `collections`, `books`,
  `services`, `printing`.
- Blade-компоненты — `nlb:*` (`<x-nlb::picture>`), partials — `partials/`.
- Миграции — `updates/YYYY_MM_DD_NNNN_<name>.php`, forward-only.
- Запуск локально — `запуск.bat` в корне (PHP 8.3, MySQL 3306, `artisan serve`
  8080, ожидание `/health`, браузер); ключ `/nobrowser` — без браузера.
- Комментарии в коде — по-русски, блоками с номерами разделов.
- CSS-классы не переименовываются: `nlb-`, `.hero`, `.print-card` остаются как в источнике.
