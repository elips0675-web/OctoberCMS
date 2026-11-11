# CHANGELOG

История версий сайта NLB на October CMS. Проект ведётся с 10.11.2026; до этого
сайт существовал как шаблон `nlb_bootstrap` на 1С-Битрикс (источник
`D:\nlbbootstrap-main`, его версии — в его собственном `CHANGELOG.md`).

Формат — [Keep a Changelog](https://keepachangelog.com/ru/1.1.0/),
версии — [SemVer](https://semver.org/lang/ru/).

## [Unreleased]

### Добавлено

- Этап 5: формы обратной связи, брони, заказа и подписки — `POST /feedback/`,
  `/booking/`, `/order/`, `/subscribe/`, подтверждение и отписка; записи в
  `nlb_*` и письма в очереди (`Mail::queue`); `tests/FormsTest.php` (25 тестов).
- Этап 6: админка «Библиотека» — списки обращений, броней, заказов и
  подписчиков за своими правами, смена статуса; отмена брони освобождает слот;
  `tests/AdminTest.php` (13 тестов).
- Этап 7: `/sitemap.xml` (106 адресов), `/robots.txt`, 301 со старых адресов
  Битрикса и JSON-LD `Library` + `WebSite`; `tests/SeoTest.php` (17 тестов),
  гейт `tools/check-seo.mjs`. В плагине 117 тестов.

### Планируется

- Мета записи (`seo_title`/`seo_description` в blueprints), поиск BM25,
  `/rss.xml`, роль редактора, подписи дашборда.

## [0.4.0] — 11.11.2026

Тема `nlb` и 110 адресов. Разметка и тексты совпадают с эталоном `preview/`,
демо-тема и демо-плагин удалены, инлайн CSS в бюджете.

### Добавлено

- Тема `themes/nlb`: layout с инлайн-CSS и спрайтом, 25 CMS-страниц, 11 partials,
  ассеты 1:1 из источника (`styles.css`, варианты 480/960, иконки, Bootstrap).
- Хелперы плагина `1.1.0`: `classes/{Content,Labels,Media}.php`,
  `Slot::occupiedMap()` / `jsonOccupiedMap()`, теги `nlb_*` в `Plugin.php`.
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

- Миграции плагина `1.0.2`: `nlb_requests`, `nlb_bookings`, `nlb_orders`,
  `nlb_slots` (`UNIQUE(service_code, date, time)`), `nlb_subscribers`
  (`UNIQUE(email)`, `UNIQUE(token)`).
- Модели `Nlb\Library\Models\{Request,Booking,Order,Slot,Subscriber}` с русской
  валидацией; double opt-in в `Subscriber`.
- `classes/BookingService.php`: создание брони в `DB::transaction` +
  `lockForUpdate`, условный `UPDATE … WHERE taken < capacity`, перехват
  `QueryException` на дубликат → `SlotUnavailableException`; подтверждение,
  отмена с освобождением слота, свободные часы.
- Команда `php artisan nlb:slots-generate [--days=30] [--from=] [--service=*]
  [--times=]` — идемпотентная генерация слотов 10:00–21:00.
- Тесты: `BookingTest`, `ModelsTest`, `SlotsGenerateTest` (всего в плагине
  29 тестов, 116 утверждений).

### Изменено

- `docs/db.md` (разделы 2, 5.4, 9), `docs/architecture.md` (`BookingService.php`),
  `docs/installation.md` — факты схемы, команда слотов, тесты.
- Живая БД `nlb_october`: 100 таблиц, слотов на 14 дней — 1 344.

## [0.2.0] — 10.11.2026

Плагин `nlb/library` и контент в БД: 91 элемент, 12 разделов. Сайта ещё нет —
`/` отдаёт демо-тему.

### Добавлено

- Плагин `plugins/nlb/library`: `Plugin.php`, `composer.json`,
  `updates/version.yaml` (`1.0.1`), автообнаружение консольных команд.
- 9 Tailor-blueprints с русскими метками: `news`, `events`, `books`
  (раздел + элемент), `collections`, `services`, `printing`. Таблицы созданы
  `php artisan tailor:migrate` (9 контентных + 3 раздела + парные репитер-таблицы).
- Команда `php artisan nlb:import [--source=] [--dry-run]`: разделы → элементы,
  `properties` → колонки, `FEATURES`/`TARIFFS` → репитеры, идемпотентность по
  `xml_id`.
- Тесты плагина: `phpunit.nlb.xml` + `plugins/nlb/library/tests/ImportTest.php`
  (8 тестов, 42 утверждения) на sqlite `:memory:` с фикстурами.

### Изменено

- `docs/db.md` приведён к фактам: имена таблиц `xc_<uuid>c` вместо `nlb_news`,
  `section_id` вместо `parent_id`, `xml_id` — `text` без `UNIQUE`, репитеры
  в отдельной таблице, фактические типы колонок, ключи импорта.
- Бэклог: закрыты M4 (импорт) и M5 (blueprints), добавлены M18 (роль «Редактор
  контента», сейчас всё доступно только superuser) и M19 (русские подписи
  собственных блоков плагина на дашборде).

### Проверено

- `nlb:import`: первый прогон — 91 элемент и 12 разделов, второй — 0 новых,
  91 обновление.
- `SELECT COUNT(*)` по шести контентным таблицам = 91, по разделам = 12,
  дублей `xml_id` — 0.
- Чтение моделями: `section`, `tags`, репитеры `features`/`tariffs` с
  `sort_order`, даты без сдвига на сутки.
- `vendor/phpunit/phpunit/phpunit -c phpunit.nlb.xml` — 8 тестов, 0 упавших.

## [0.1.0] — 10.11.2026

Платформа установлена и проверена. Своего кода ещё нет: работает демо-тема.

### Добавлено

- October CMS **4.4.13** (Laravel **12.71.0**) на PHP **8.3.30**, MySQL 8.4.3,
  база `nlb_october`, 44 таблицы.
- `composer.json` + `composer.lock` — версия зафиксирована.
- `.env.example` (проектные значения: `APP_NAME=NLB`, `DB_DATABASE=nlb_october`,
  `QUEUE_CONNECTION=database`, `MAIL_MAILER=log`) и `.gitignore`, объединяющий
  правила October и проекта.
- Документация переписана под 4.x: реальные команды (`october:about`,
  `vendor/phpunit/phpunit/phpunit`), три грабли установки (PATH, `theme:seed`,
  `ext-zip`), требования хостинга.
- Каркас документации: `AGENTS.md`, `project-context.md`, `README.md`,
  `Что сделано.txt`, `Что доделать.txt` (41 открытый пункт), `docs/` — 9 документов.
- Администратор `admin` (`admin@nlb.local`, superuser) создан `october:admin`
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
