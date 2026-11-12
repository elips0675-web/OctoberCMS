# QA-чеклист

Приёмка этапа. Пункт без отметки «чем проверено» — не закрыт.

## A. Перед закрытием любого этапа

```powershell
$php = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
$env:PATH = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;' + $env:PATH
& $php vendor/phpunit/phpunit/phpunit -c phpunit.nlb.xml
& $php vendor/phpunit/phpunit/phpunit
node tools/purge-css.mjs --check
node tools/check-css-budget.mjs
node tools/check-urls.mjs
node tools/check-dom-parity.mjs
node tools/check-seo.mjs
node tools/check-orphans.mjs
node tools/check-clean-tree.mjs
```

- [ ] PHPUnit плагина (`-c phpunit.nlb.xml`) — 0 упавших; без `-c` плагин не
      регистрируется (корневой конфиг подменяет `PLUGINS_PATH`) и гейт врёт.
- [ ] PHPUnit ядра — 0 упавших, число тестов записано в журнал.
- [ ] `october:migrate` на чистой БД поднимает схему (не «на моей базе работает»).
- [ ] `php -l` по всем PHP-файлам вне `vendor/` и `storage/` — 0 ошибок.
- [ ] `check-urls` — 109 страниц `200`, 404 со страницей, внешнего CSS нет.
- [ ] `check-dom-parity` — 0 расхождений с эталоном (title, h1, h2, карточки, текст).
- [ ] `check-seo` — robots.txt, sitemap.xml на 106 адресов (все 200), 301 со
      старых адресов, JSON-LD `Library` + `WebSite` на главной.
- [ ] `check-orphans` — у каждого адреса карты сайта есть входящая ссылка
      (`--self-test` — самопроверка разбора ссылок и поиска сирот).
- [ ] `check-clean-tree` — дерево чистое, черновики владельца не в индексе.
- [ ] `git status` чистый, коммит поимённый, запись в журнале есть.

## B. 110 адресов (этап 4)

- [ ] Каждый из 110 URL отвечает `200`:
  - книги 25 (`/books/` + 24 детальных),
  - печать 38 (`/printing/` + 37),
  - новости 11, услуги 9, события 7, подборки 7,
  - корень 13 (`/`, `/about/`, `/auth/`, `/contacts/`, `/feedback/`, `/personal/`,
    `/privacy/`, `/rules/`, `/search/`, `/sitemap/`, `/subscribe/`, `/terms/`, 404).
- [ ] `/non-existent/` отвечает `404` и показывает страницу 404, а не пустоту.
- [ ] Query-строка не теряется: `/services/{slug}/?utm_source=test` отвечает 200,
  параметр виден в обработчике.
- [ ] DOM-сверка с `preview/`: те же заголовки, порядок блоков, число карточек в списках.
- [ ] Сверка геометрии: 0 расхождений (P2-12 — purge-CSS на живых страницах).

## B2. SEO и микроразметка (этапы 7–9)

- [ ] `/sitemap.xml` — 200 `application/xml`, 106 `<loc>` с `lastmod`;
  `/robots.txt` — 200 `text/plain` с `Sitemap:` текущего хоста.
- [ ] Старые адреса (`/index.php`, `/sitemap.php`, `/{section}/index.php`,
  `/{section}/detail.php?CODE=…`) отвечают `301`, `CODE` отброшен, `utm_*` цел.
- [ ] JSON-LD в `<head>` разбирается как JSON: `Library` + `WebSite` с
  `SearchAction` на всех страницах; на детальной — свой узел (`Article`,
  `Event`, `Product`, `Service`), у подборки — только общие узлы.
- [ ] `BreadcrumbList` на всех страницах разделов и детальных:
  «Главная → раздел → страница», `position` по порядку, `item` абсолютный,
  последняя крошка — адрес самой страницы; на главной и на 404 крошек нет.
- [ ] `canonical` совпадает с `<loc>` карты побайтово (хвостовой слэш),
  `og:image` и `twitter:image` — абсолютные и из записи, если картинка есть.
- [ ] `node tools/check-seo.mjs` — exit 0.
- [ ] `/rss.xml` — 200 `application/rss+xml`, RSS 2.0, 20 свежих новостей,
  у каждой `title`/`link`/`guid`/`pubDate`; отключённая новость в ленту не
  попадает; на главной есть `<link rel="alternate" type="application/rss+xml">`.

## B3. Заголовки безопасности (этап 10)

- [ ] Каждая публичная страница отдаёт `X-Content-Type-Options: nosniff`,
  `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`,
  `Permissions-Policy` без камеры, микрофона и геолокации.
- [ ] CSP: `default-src 'self'`, `frame-ancestors 'none'`, `object-src 'none'`,
  `script-src 'self' 'nonce-…'`, `style-src` с `'unsafe-inline'`, без
  `unsafe-eval`; nonce в заголовке совпадает с `nonce` инлайнового скрипта
  фильтров, у следующего запроса nonce другой.
- [ ] `/admin` (или `BACKEND_URI`) — без CSP, `X-Frame-Options: SAMEORIGIN`,
  виджеты и редакторы October работают.
- [ ] 404 получает тот же набор, статика на проде — от nginx (`nginx.conf`).
- [ ] `node tools/check-headers.mjs` — exit 0.

## B4. PWA (этап 12)

- [ ] `/manifest.webmanifest` — 200 `application/manifest+json`, JSON разбирается,
      `name`/`short_name`/`start_url`/`scope: "/"`/`display`, ≥2 иконки и shortcuts
      отвечают 200; на главной есть `<link rel="manifest">`.
- [ ] `/sw.js` — 200 `application/javascript`, `Service-Worker-Allowed: /`,
      в списке `SHELL` нет плейсхолдеров, версия — восемь hex, каждый адрес
      оболочки отвечает 200, `/admin/` не перехватывается.
- [ ] `/offline.html` — 200 `text/html`, без `<script>` и `onclick=` (их
      заблокирует CSP), ссылка на главную работает.
- [ ] `node tools/check-pwa.mjs` — exit 0.

## B5. Health-check и мониторинг (этап 13)

- [ ] `/health` — 200 `application/json`, `status: "ok"`, все три компонента
      (`db`, `cache`, `queue`) — `true`.
- [ ] Ответ с `Cache-Control: no-store` и без `Set-Cookie` (проба не создаёт
      сессию).
- [ ] В теле нет `SQLSTATE`, DSN, паролей и путей — только имена компонентов.
- [ ] Остановить MySQL → `/health` отдаёт 503 с `db: false`; поднять → снова 200
      (проверяется на стенде, не под живой базой).
- [ ] `php artisan nlb:health-report` пишет JSON-строку в `storage/logs/health.log`
      (`at`, `db_ms`, `queue_size`, `disk_free_bytes`, `checks`), exit 0.
- [ ] `php artisan schedule:list` показывает `nlb:health-report` раз в час;
      внешняя проба на `/health` заведена (владелец, M17).
- [ ] `node tools/check-health.mjs` — exit 0.

## C. Lighthouse (этап 8)

- [ ] Три прогона подряд через gzip-сервер, мобильный профиль: 100/100/100/100.
- [ ] Внешних CSS-запросов — 0; инлайн CSS ≤ 56,4 КБ; документ главной ≤ 190 412 знаков.
- [ ] Hero с `fetchpriority="high"`; ниже экрана `loading="lazy"`; у всех картинок
  `width`/`height`; блокирующих шрифтов нет.
- [ ] Числа (FCP, LCP, TBT, CLS) записаны в `Что сделано.txt`.

## D. Функциональная приёмка (этапы 3, 5, 7)

### D1. Гонка слота (P1-20) — первый по важности

- [ ] `UNIQUE(service_code, date, time)` объявлен в миграции.
- [ ] Запись брони идёт в `DB::transaction` с `lockForUpdate()`.
- [ ] Тест: две параллельные попытки занять один слот → ровно одна успешная, второй
  ответ `409`, в БД одна строка.
- [ ] Тест падает на старом коде: временно убрать `UNIQUE` → красный, вернуть → зелёный.

### D2. Формы (этап 5)

- [ ] Обратная связь: `POST /feedback/` создаёт `nlb_requests`, письмо в очереди
  (`Mail::fake` + `assertQueued`), редирект `?sent=1`.
- [ ] Бронь: создаёт `nlb_bookings`, занимает слот, письмо клиенту.
- [ ] Заказ: создаёт `nlb_orders`, письмо в очереди; пустая форма → `422` с человеческим
  текстом, не `QueryException`.
- [ ] Подписка: double opt-in (без подтверждения рассылка не идёт), отписка по токену.
- [ ] Антиспам: honeypot + лимитер; капча — только при наличии ключа.
- [ ] Письмо видно в `storage/logs/laravel.log` (`MAIL_MAILER=log`).

### D3. API и слоты (этап 7)

- [ ] `POST /api/bookings` без токена → `401`; с токеном → `201`.
- [ ] Повтор с тем же `external_id` → тот же результат, вторая бронь не создаётся.
- [ ] `400` — битый JSON; `422` — валидация; `409` — слот занят; `429` — лимитер.
- [ ] 6 тестов на коды + тест идемпотентности.
- [ ] Слоты генерируются на сервере (`nlb:slots-generate`), админ-список фильтруется
  по дате.

### D4. Поиск и чат (этап 6)

- [ ] 29 кейсов `db/tests/fixtures/search-cases.json` — те же результаты, что в источнике.
- [ ] Контракт `/ajax/search`: `?q=`, `&limit=`, коды `200/400/405/503`.
- [ ] Индекс пересобирается командой; после правки записи — обновляется хуком.
- [ ] 95 кейсов `db/tests/fixtures/filter-cases.json` — те же вердикты.
- [ ] Промо-подсказки: ≤ 3 за сеанс, не раньше 2-го вопроса, не чаще чем через 3,
  без повторов, `promo:false` у жалоб.

## E. Регресс before/after

- [ ] Источник `D:\nlbbootstrap-main` не изменён (`git status` источника — только
  черновики владельца, добавленные им самим).
- [ ] Vercel-деплой источника продолжает работать.
- [ ] `D:\bitrix doker` не тронут.

## F. Что считается дефектом

- 200 вместо 404 (или наоборот) на любом из 110 адресов.
- Расхождение структуры DOM с `preview/` без записи в бэклоге.
- Внешний CSS/JS-запрос, Google Fonts, рост инлайн CSS сверх бюджета.
- Письмо «отправлено», но в очереди не появилось; заявка «сохранена», но строки нет.
- Две строки на один слот; `409` не приходит; тест на гонку зелёный без `UNIQUE`.
- Сырой текст исключения в интерфейсе вместо человеческого сообщения.
- Закрытый пункт, удалённый из бэклога без записи в журнале.
