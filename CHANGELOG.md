# CHANGELOG

История версий сайта ELV на October CMS. Проект ведётся с 10.11.2026; до этого
сайт существовал как шаблон `elv_bootstrap` на 1С-Битрикс (источник
`D:\nlbbootstrap-main`, его версии — в его собственном `CHANGELOG.md`).

Формат — [Keep a Changelog](https://keepachangelog.com/ru/1.1.0/),
версии — [SemVer](https://semver.org/lang/ru/).

## [Unreleased]

### Изменено

- Этап 31: из `Что доделать.txt` удалён раздел «Закрытые пункты» (78 строк,
  16 пунктов) — он дублировал `Что сделано.txt` и противоречил правилу из шапки
  бэклога; ссылки в P1-9 и в записи Stage 24 переписаны на журнал.
- Этап 30: разбор двух черновиков про счётчик установок и лицензии —
  `docs/roadmap.md` §12 (14 тезисов с проверкой; события `cms.theme.activated` и
  класса `System\Models\Setting` в ядре нет), новый пункт **P2-32** в
  `Что доделать.txt` (клиентская часть здесь, сервер лицензий — вне репозитория),
  связка с моделью 4 в `docs/monetization.md` §7.
- Этап 29: разбор трёх черновиков владельца (Filament, лендинг, монетизация
  сборки) — `docs/roadmap.md` §11 с вердиктами по каждому тезису; прайс четырёх
  моделей продаж помечен как внешние ориентиры (`docs/monetization.md` §7);
  чек-лист оптимизации при передаче клиенту (`docs/qa-checklist.md` §C2);
  исправлены ложные факты: `elv_search_*`/`elv_push_subscriptions` в
  `docs/db.md` (таблиц нет, `Search.php` нет) и устаревшее описание настроек
  темы в `docs/monetization.md` §3; добавлен пункт P2-31 (платежи и автовыдача).
- Этап 28: переименование `nlb` → `elv` целиком — тема `themes/elv`
  (`code: elv`), плагин `plugins/elv/library` и namespace `Elv\Library`,
  Tailor-handle `Elv\*`, Twig-функции `elv_*`, классы `.elv-*`, команды
  `elv:import` / `elv:slots-generate` / `elv:health-report`, таблицы `elv_*`
  и база `elv_october`. Старые таблицы переименовывает миграция версии
  **2.0.0** `rename_legacy_tables_to_elv.php` (данные сохранены: 79 таблиц,
  15 410 строк до и после); create-миграции страхуют тот же случай на чистой
  установке. `phpunit.nlb.xml` → `phpunit.elv.xml`, гейты `tools/*.mjs`
  обновлены. Проверено: 231 тест плагина, 10 живых гейтов.

### Добавлено

- Этап 20: конструктор страниц — blueprint `Elv\Page` и миксин `Elv\Blocks`
  (шесть блоков: текст, текст с картинкой, цитата, сетка записей, видео,
  оглавление), класс `PageBuilder`, CMS-страница `/:slug` с 404-веткой,
  страницы конструктора в `/sitemap.xml`, `tests/PageBuilderTest.php`
  (12 тестов), документ `docs/theme-universality.md`.

- Этап 5: формы обратной связи, брони, заказа и подписки — `POST /feedback/`,
  `/booking/`, `/order/`, `/subscribe/`, подтверждение и отписка; записи в
  `elv_*` и письма в очереди (`Mail::queue`); `tests/FormsTest.php` (25 тестов).
- Этап 6: админка «Библиотека» — списки обращений, броней, заказов и
  подписчиков за своими правами, смена статуса; отмена брони освобождает слот;
  `tests/AdminTest.php` (13 тестов).
- Этап 7: `/sitemap.xml` (106 адресов), `/robots.txt`, 301 со старых адресов
  Битрикса и JSON-LD `Library` + `WebSite`; `tests/SeoTest.php` (17 тестов),
  гейт `tools/check-seo.mjs`.
- Этап 8: мета записи — `seo_title`/`seo_description` в шести blueprints,
  `Seo::applyRecord()` выставляет их в `<head>` (пустые поля откатываются на имя
  записи и `excerpt`), canonical совпадает с `<loc>` карты; `tests/SeoTest.php`
  вырос до 27 тестов. В плагине 127 тестов, 462 утверждения.
- Этап 9: микроразметка записи — `Article` (новость), `Event` (афиша),
  `Product` (сувенир и печать), `Service` (услуга) в `@graph` рядом с `Library`
  и `WebSite`; `og:image`/`twitter:image` берутся из картинки записи; в
  `Product` бренд — библиотека, а не упаковка. `tests/SeoTest.php` вырос до
  50 тестов, в плагине 150 тестов, 535 утверждений; `tools/check-seo.mjs`
  проверяет узел записи на каждой детальной странице.
- Этап 10: CSP и заголовки безопасности — middleware `SecurityHeaders` с
  одноразовым nonce для инлайнового скрипта фильтров, `X-Frame-Options`
  (`DENY` на сайте, `SAMEORIGIN` в админке без CSP), `Referrer-Policy`,
  `Permissions-Policy`, HSTS на HTTPS; статические заголовки дублируются в
  `nginx.conf`. `tests/SecurityHeadersTest.php` (12 тестов), гейт
  `tools/check-headers.mjs`; в плагине 162 теста, 569 утверждений.
- Этап 11: лента новостей `/rss.xml` — RSS 2.0, 20 свежих новостей по
  `published_at` (откат на `created_at`), описание из `excerpt` или текста без
  разметки, отключённая запись не попадает, `<link rel="alternate">` в layout;
  `tests/RssTest.php` (9 тестов), `tools/check-seo.mjs` проверяет ленту.
  В плагине 171 тест, 616 утверждений.
- Этап 12: PWA — `manifest.webmanifest`, `sw.js` и `offline.html` отдаются с
  корня маршрутами (`classes/Http/PwaController.php`), файлы лежат в
  `themes/elv/assets/pwa/`, чтобы область service worker не сузилась до каталога
  темы; версия кеша — восемь hex из `mtime` файлов `SHELL` (`Pwa::version()`),
  `/admin/` из перехвата исключён, офлайн-страница без инлайнового JS.
  `tests/PwaTest.php` (9 тестов), гейт `tools/check-pwa.mjs`; попутно исправлен
  `Sitemap::themePath()` (в October 4 `Theme::getPath()` принимает имя каталога,
  из-за чего `lastmod` текстовых страниц молча подменялся текущей датой).
  В плагине 181 тест, 684 утверждения.
- Этап 13: health-check — `GET /health` отвечает `200`, если живы БД (`SELECT 1`),
  кэш и очередь, и `503` с именем отказавшего компонента; текст исключения
  уходит в лог, ответ с `Cache-Control: no-store` и без `Set-Cookie`.
  `elv:health-report` пишет часовую JSON-строку (ответ БД в мс, размер очереди,
  свободный диск) в `storage/logs/health.log`, `registerSchedule()` ставит её и
  ежедневную генерацию слотов; `tests/HealthTest.php` (12 тестов), гейт
  `tools/check-health.mjs`; в плагине 193 теста, 724 утверждения.
- Этап 14: `запуск.bat` — запуск в один щелчок (PHP из PATH или Laragon,
  проверка/подъём MySQL, `artisan serve` на 8080, ожидание `/health`, браузер;
  ключ `/nobrowser`), CRLF закреплён в `.gitattributes`. README: таблица
  статуса приведена к факту (этапы 3–5 и 8 закрыты), убрана несуществующая
  `elv:search-index`; `docs/architecture.md` §2 — реальные классы плагина,
  `Search`/`TextFilter`/`Push` помечены планом (M8/M9).
- Этап 15: `docs/roadmap.md` §6 — пять тезисов оценки по шаблону и Tailor
  больше не выпадают молча (миксины SEO-полей — P2-19, работа без JS — P1-5,
  атомарные паршалы — отклонено, `ml_` — заметка в P2-1, zero-downtime —
  P0-9); в §1 добавлены сверки по `npm audit` (это ядро October, не проект)
  и по очередям (`Mail::queue` уже в четырёх контроллерах форм).
- Этап 16: `BreadcrumbList` в JSON-LD — «Главная → раздел → страница» из адреса
  (`Seo::breadcrumbs()`), подписи разделов в `Seo::SECTIONS` сверяются тестом с
  `<h1>` страниц; на главной и на 404 крошек нет. Гейт
  `tools/check-orphans.mjs` — у всех 105 адресов карты есть входящая ссылка
  (`--self-test` проверяет сам разбор), `check-seo.mjs` валидирует `position`,
  имена и последнюю крошку. `FAQPage` отклонён: на сайте нет видимого блока
  «вопрос-ответ». `tests/SeoTest.php` 51 → 67, в плагине 209 тестов,
  780 утверждений.
- Этап 17: публичный JSON контента — `GET /api/v1/{handle}` и
  `/api/v1/{handle}/{slug}` (`classes/Http/ApiController.php`): 6 разделов из
  `Sitemap::SECTIONS`, поля `id/slug/title/excerpt/image/url/updated_at`,
  только опубликованные записи, `limit` до 100, `throttle:60,1`,
  `Cache-Control: public, max-age=60`; 404 в JSON на чужой раздел и slug,
  422 на негодный `limit`. Гейт `tools/check-api.mjs` (91 запись, каждая
  `url` открывается 200), `tests/ApiTest.php` (7 тестов); в плагине 216
  тестов, 832 утверждения.
- Этап 18: триаж черновика «Что взять из WPShop» — `docs/roadmap.md` §8
  (16 тезисов: 2 закрыты, 1 правка, 5 приняты с условиями, 5 отложены,
  4 отклонены). Бэклог: P2-24 — конструктор страниц из блоков (Tailor
  `structure`, партиал на тип блока, TOC без JS), P2-25 — год в подвале
  вместо жёсткого «© 2026» (`themes/elv/partials/footer.htm:76`).
- Этап 19: P2-25 закрыт — подвал печатает `© {{ 'now'|date('Y') }}`
  (`themes/elv/partials/footer.htm:76`), бренд «Библиотека 2026» остался
  текстом; `tests/FooterTest.php` (2 теста: источник без литерала и рендер
  главной с текущим годом); в плагине 218 тестов, 836 утверждений.

### Планируется

- Поиск BM25, роль редактора, подписи дашборда.

## [0.4.0] — 11.11.2026

Тема `elv` и 110 адресов. Разметка и тексты совпадают с эталоном `preview/`,
демо-тема и демо-плагин удалены, инлайн CSS в бюджете.

### Добавлено

- Тема `themes/elv`: layout с инлайн-CSS и спрайтом, 25 CMS-страниц, 11 partials,
  ассеты 1:1 из источника (`styles.css`, варианты 480/960, иконки, Bootstrap).
- Хелперы плагина `1.1.0`: `classes/{Content,Labels,Media}.php`,
  `Slot::occupiedMap()` / `jsonOccupiedMap()`, теги `elv_*` в `Plugin.php`.
- Гейты `tools/check-urls.mjs` (109×200 + 404) и `tools/check-dom-parity.mjs`
  (title, h1, h2, карточки, текст против `preview/`).
- Тесты `MediaTest` (19), `LabelsTest` (7), `SlotMapTest` (6) — в плагине 61 тест.

### Изменено

- `script.js` побайтово равен источнику; правка «дата не в прошлом» откачена.
- `/personal/` совпадает с эталоном: страница-редирект `meta refresh` на `/auth/`.
- `Media::imagePath()` больше не отдаёт `[]` (пустой mediafinder) как путь картинки.
- `.gitignore`: `themes/*/assets/vendor/*.min.css` попадает в git.

### Удалено

- `themes/demo`, `plugins/october/demo`, 24 таблицы `xc_*` демо-блюпринтов,
  8 строк `tailor_content_schema` и запись `October.Demo`; `october:fresh`
  не запускался (он снёс бы контент).

## [0.3.0] — 10.11.2026

Eloquent-слой заявок и броней, слоты услуг. Гонка слота закрыта `UNIQUE` и
транзакцией; публичных форм и маршрутов ещё нет.

### Добавлено

- Миграции плагина `1.0.2`: `elv_requests`, `elv_bookings`, `elv_orders`,
  `elv_slots` (`UNIQUE(service_code, date, time)`), `elv_subscribers`
  (`UNIQUE(email)`, `UNIQUE(token)`).
- Модели `Elv\Library\Models\{Request,Booking,Order,Slot,Subscriber}` с русской
  валидацией; double opt-in в `Subscriber`.
- `classes/BookingService.php`: создание брони в `DB::transaction` +
  `lockForUpdate`, условный `UPDATE … WHERE taken < capacity`, перехват
  `QueryException` на дубликат → `SlotUnavailableException`; подтверждение,
  отмена с освобождением слота, свободные часы.
- Команда `php artisan elv:slots-generate [--days=30] [--from=] [--service=*]
  [--times=]` — идемпотентная генерация слотов 10:00–21:00.
- Тесты: `BookingTest`, `ModelsTest`, `SlotsGenerateTest` (всего в плагине
  29 тестов, 116 утверждений).

### Изменено

- `docs/db.md` (разделы 2, 5.4, 9), `docs/architecture.md` (`BookingService.php`),
  `docs/installation.md` — факты схемы, команда слотов, тесты.
- Живая БД `elv_october`: 100 таблиц, слотов на 14 дней — 1 344.

## [0.2.0] — 10.11.2026

Плагин `elv/library` и контент в БД: 91 элемент, 12 разделов. Сайта ещё нет —
`/` отдаёт демо-тему.

### Добавлено

- Плагин `plugins/elv/library`: `Plugin.php`, `composer.json`,
  `updates/version.yaml` (`1.0.1`), автообнаружение консольных команд.
- 9 Tailor-blueprints с русскими метками: `news`, `events`, `books`
  (раздел + элемент), `collections`, `services`, `printing`. Таблицы созданы
  `php artisan tailor:migrate` (9 контентных + 3 раздела + парные репитер-таблицы).
- Команда `php artisan elv:import [--source=] [--dry-run]`: разделы → элементы,
  `properties` → колонки, `FEATURES`/`TARIFFS` → репитеры, идемпотентность по
  `xml_id`.
- Тесты плагина: `phpunit.elv.xml` + `plugins/elv/library/tests/ImportTest.php`
  (8 тестов, 42 утверждения) на sqlite `:memory:` с фикстурами.

### Изменено

- `docs/db.md` приведён к фактам: имена таблиц `xc_<uuid>c` вместо `elv_news`,
  `section_id` вместо `parent_id`, `xml_id` — `text` без `UNIQUE`, репитеры
  в отдельной таблице, фактические типы колонок, ключи импорта.
- Бэклог: закрыты M4 (импорт) и M5 (blueprints), добавлены M18 (роль «Редактор
  контента», сейчас всё доступно только superuser) и M19 (русские подписи
  собственных блоков плагина на дашборде).

### Проверено

- `elv:import`: первый прогон — 91 элемент и 12 разделов, второй — 0 новых,
  91 обновление.
- `SELECT COUNT(*)` по шести контентным таблицам = 91, по разделам = 12,
  дублей `xml_id` — 0.
- Чтение моделями: `section`, `tags`, репитеры `features`/`tariffs` с
  `sort_order`, даты без сдвига на сутки.
- `vendor/phpunit/phpunit/phpunit -c phpunit.elv.xml` — 8 тестов, 0 упавших.

## [0.1.0] — 10.11.2026

Платформа установлена и проверена. Своего кода ещё нет: работает демо-тема.

### Добавлено

- October CMS **4.4.13** (Laravel **12.71.0**) на PHP **8.3.30**, MySQL 8.4.3,
  база `elv_october`, 44 таблицы.
- `composer.json` + `composer.lock` — версия зафиксирована.
- `.env.example` (проектные значения: `APP_NAME=ELV`, `DB_DATABASE=elv_october`,
  `QUEUE_CONNECTION=database`, `MAIL_MAILER=log`) и `.gitignore`, объединяющий
  правила October и проекта.
- Документация переписана под 4.x: реальные команды (`october:about`,
  `vendor/phpunit/phpunit/phpunit`), три грабли установки (PATH, `theme:seed`,
  `ext-zip`), требования хостинга.
- Каркас документации: `AGENTS.md`, `project-context.md`, `README.md`,
  `Что сделано.txt`, `Что доделать.txt` (41 открытый пункт), `docs/` — 9 документов.
- Администратор `admin` (`admin@elv.local`, superuser) создан `october:admin`
  10.11.2026; вход проверен реальным `POST /admin/backend/auth/signin` →
  `/admin/dashboard` (200). Пароль — в `.local/admin-credentials.md`, каталог
  в `.gitignore`, в репозиторий не попадает.

### Изменено

- Ветка October: 3.x LTS → **4.x**. Причина: у 3.x только security-фиксы и
  Laravel 9, у 4.x активная поддержка и Laravel 12; документация 4.x живёт на
  `docs.octobercms.com/4.x/`, а не на `octobercms.com/docs/4.x/` (там 404).
- Числа промта заменены замерами: `header.php` 269 строк (не 251),
  `footer.php` 323 (не 305), правил чата 11 (не 10).

### Решено (владелец, 10.11.2026)

- Почта — `MAIL_MAILER=log`; реальный SMTP подключается позже без правок кода.
- Публикация — push в `https://github.com/elips0675-web/OctoberCMS`, ветка `main`.
- PHP — 8.3.30 локально (Laravel 12 официально тестируется на 8.2–8.4).

### Проверено

- PHPUnit 9.6.38: 1 171 тест, 3 896 утверждений, 8 пропущено, 0 упавших, 2:46.
- `GET /` → 200 (27 830 байт, демо-тема), `GET /admin` → 200 (10 914 байт).
- `php -l` по 28 PHP-файлам вне `vendor/` и `storage/` — 0 ошибок.

## [0.0.0] — 10.11.2026

Точка отсчёта: каталог `D:\October CMS` пуст, сайт работает только в источнике
(Битрикс и статическое превью).
