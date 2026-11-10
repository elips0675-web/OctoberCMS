# База данных: сущности, поля, индексы

## 1. Зачем отдельный слой

В источнике схема — это `db/config/iblocks.php` (28 832 байта) + 13 PHP-миграций
(`db/migrations/001…013`), которые создают инфоблоки, свойства, разделы, элементы, меню,
шаблон сайта и три формы. В October схема — обычные Laravel-миграции, а контент —
Tailor-записи поверх своих таблиц.

Схему источника **не копируем вслепую**: один инфоблок → одна таблица, свойства → колонки
или JSON, `L`-списки → словари/`enum`.

## 2. Таблицы

Контентные таблицы создаёт **Tailor**, а не миграции плагина: имя строится из `uuid`
blueprint-а (`EntryBlueprint::makeBlueprintTableName`) как `xc_<uuid без дефисов>c`.
Поэтому таблиц `nlb_news`/`nlb_books` в этой схеме нет — есть таблица с uuid в имени.
`uuid` живёт в `plugins/nlb/library/blueprints/**/*.yaml` и не меняется, иначе исчезнет
связь с уже созданной таблицей.

| Blueprint | `uuid` | Таблица | Источник | Записей |
|---|---|---|---|---|
| `Nlb\News` | `ffcc9263-f919-4cf3-8243-991793a60e9c` | `xc_ffcc9263f9194cf38243991793a60e9cc` | `news` | 10 |
| `Nlb\NewsSection` | `7d052fe5-0e4b-4705-9aa3-a5f4c4cccb4f` | `xc_7d052fe50e4b47059aa3a5f4c4cccb4fc` | разделы `news` | 2 |
| `Nlb\Event` | `824265df-14f0-48a1-b18e-3d1d4ff62f4e` | `xc_824265df14f048a1b18e3d1d4ff62f4ec` | `events` | 6 |
| `Nlb\EventSection` | `5c9f95ad-7215-406f-9bf7-5be0aee76f30` | `xc_5c9f95ad7215406f9bf75be0aee76f30c` | разделы `events` | 4 |
| `Nlb\Book` | `f92fe151-9985-42b9-b2a5-e77aed59abec` | `xc_f92fe151998542b9b2a5e77aed59abecc` | `books` | 24 |
| `Nlb\BookSection` | `16285221-4859-451f-b8c6-f7f194b1ee73` | `xc_162852214859451fb8c6f7f194b1ee73c` | разделы `books` | 6 |
| `Nlb\Collection` | `257f4b4d-a9b8-467a-8f53-1fba6d496a5b` | `xc_257f4b4da9b8467a8f531fba6d496a5bc` | `collections` | 6 |
| `Nlb\Service` | `845567bb-fc03-453c-ab9a-e0988d992b44` | `xc_845567bbfc03453cab9ae0988d992b44c` | `services` | 8 |
| `Nlb\Printing` | `ca04aafc-a5d3-41c8-b1e9-998c1cc4d475` | `xc_ca04aafca5d341c8b1e9998c1cc4d475c` | `printing` | 37 |

Разделы — не `tailor_structure`, а три отдельные таблицы (`type: structure`, по одной
на инфоблок): 2 + 4 + 6 = 12 записей. Таблицы создаются `php artisan tailor:migrate`,
а не `october:migrate`.

Repeater-поля (`services.features`, `services.tariffs`) живут **не колонками**, а в парной
таблице `<таблица>r` (`xc_845567bbfc03453cab9ae0988d992b44r`): `host_id`, `host_field`,
`content_value` (JSON), `sort_order`. Связь — `hasMany`, поэтому
`$record->features = [...]` падает с `Nested arrays may not be passed to whereIn method`;
писать нужно через `$record->features()->make()` / `->create()`.

Заявки, брони, заказы, слоты, подписчики, push и поисковый индекс — **Eloquent-таблицы**
(обычные миграции плагина):

| Таблица | Тип | Источник | Записей при импорте |
|---|---|---|---|
| `nlb_requests` | Eloquent | `requests` | 0 (истории нет) |
| `nlb_bookings` | Eloquent | `bookings` | 0 (истории нет) |
| `nlb_orders` | Eloquent | `orders` | 0 (истории нет) |
| `nlb_slots` | Eloquent | — (новое, P1-9) | генерируется |
| `nlb_subscribers` | Eloquent | — (P0-5) | 0 |
| `nlb_push_subscriptions` | Eloquent | `nlb_push.php` (JSON-файл) | 0 |
| `nlb_search_documents`, `nlb_search_terms` | Eloquent | `db/search/index.json` | 99 / 1 340 |

## 3. Общие колонки контентных таблиц

Колонки-«шапку» добавляет сам Tailor (`HasCommonColumns`); из них импорт пишет только
свои, остальные (`draft_mode`, `is_version`, `primary_id`, `site_id`, `site_root_id`,
`blueprint_uuid`, `content_group`, `deleted_*`) — служебные.

| Колонка | Фактический тип | Источник | Пишет импорт |
|---|---|---|---|
| `id` | `int unsigned`, PK | — | — |
| `xml_id` | `text`, nullable | `nlb-news-001`, `nlb-sec-news-library` | да |
| `slug` | `varchar(255)`, nullable | `code` элемента | да |
| `title` | `varchar(255)`, nullable | `name` | да |
| `is_enabled` | `tinyint(1)` | — (`1`) | да |
| `sort` | `int`, nullable | `sort` | да |
| `published_at` | `timestamp`, nullable | `active_from` (для книг — `published_at`, см. 4.4) | да |
| `section_id` | `int unsigned`, nullable | `section` элемента (поле `entries`, `maxItems: 1`) | да |
| `excerpt` | `mediumtext`, nullable | `preview_text` | да |
| `content` | `mediumtext`, nullable | `detail_text` | да |
| `image` | `mediumtext`, nullable | `image` (у 32 из 91 элемента) | да |
| `created_at`, `updated_at` | `timestamp` | — | — |

`xml_id` — **естественный ключ идемпотентного импорта**, но `UNIQUE(xml_id)` в Tailor
объявить нельзя: колонка `text`, а MySQL требует длину ключа. Поэтому идемпотентность
держит не схема, а код: перед записью импортёр делает
`EntryRecord::inSection(<blueprint>)->where('xml_id', $id)->first()`; второй прогон даёт
0 новых строк (тест `plugins/nlb/library/tests/ImportTest.php`,
`testSecondRunUpdatesWithoutCreatingDuplicates`).

## 4. Поля контента

### 4.1. `Nlb\News` (4 свойства)

| Колонка | Фактический тип | Источник | Значения |
|---|---|---|---|
| `author` | `text`, nullable | `AUTHOR` (S) | — |
| `tags` | `mediumtext` (jsonable-массив) | `TAGS` (L, multi) | `books`, `digital`, `events`, `kids`, `science`, `library` |
| `read_time` | `text`, nullable | `READ_TIME` (S) | — |
| `source_url` | `text`, nullable | `SOURCE_URL` (S) | в выгрузке свойства нет — колонка пустая |

### 4.2. `Nlb\Event` (8 свойств)

| Колонка | Фактический тип | Источник | Значения |
|---|---|---|---|
| `event_date` | `timestamp`, nullable | `DATE` (DateTime) | — |
| `place` | `text`, nullable | `PLACE` | — |
| `category` | `text`, nullable | `CATEGORY` (L) | `literature`, `children`, `tech`, `lecture` |
| `format` | `text`, nullable | `FORMAT` (L) | `offline`, `online`, `hybrid` |
| `seats` | `int`, nullable | `SEATS` (N) | — |
| `price` | `text`, nullable | `PRICE` | — |
| `duration` | `text`, nullable | `DURATION` | — |
| `register_url` | `text`, nullable | `REGISTER_URL` | — |

### 4.3. `Nlb\Collection` (3 свойства)

| Колонка | Фактический тип | Значения |
|---|---|---|
| `items_count` | `int`, nullable | — (`COUNT`) |
| `color` | `text`, nullable | `primary`, `green`, `sky`, `pink`, `orange` |
| `icon` | `text`, nullable | — |

### 4.4. `Nlb\Book` (9 свойств)

| Колонка | Фактический тип | Значения |
|---|---|---|
| `author` | `text`, nullable | — |
| `year` | `text`, nullable | — |
| `isbn` | `text`, nullable | — |
| `publisher` | `text`, nullable | — |
| `pages` | `int`, nullable | — |
| `age_limit` | `text`, nullable | `all`, `kids`, `adult`; в выгрузке свойства нет — колонка пустая |
| `available` | `text`, nullable | `available`, `reserved`, `unavailable` |
| `shelf` | `text`, nullable | — |
| `price` | `int`, nullable | — |

Внимание: названия разделов `books` в источнике — `Брелоки`, `Посуда и фарфор`, `Одежда`,
`Канцелярия`, `Магниты и открытки`, `Аксессуары` (коды `fiction`, `history`, `science`,
`children`, `business`, `art`). Это остаток от сувениров. Коды и названия переносятся
**как есть**: переименование — отдельный пункт бэклога, не самодеятельность переезда.

### 4.5. `Nlb\Service` (11 свойств)

| Колонка | Фактический тип | Примечание |
|---|---|---|
| `icon` | `text`, nullable | — |
| `color` | `text`, nullable | те же 5 значений |
| `subtitle` | `text`, nullable | — |
| `price_from` | `int`, nullable | — |
| `price_label` | `text`, nullable | — |
| `duration` | `text`, nullable | — |
| `schedule` | `mediumtext`, nullable | `SCHEDULE` (multi) — одна строка на пункт, `textarea` |
| `phone` | `text`, nullable | — |
| `email` | `text`, nullable | — |
| `features` | таблица `<таблица>r` | `FEATURES` (multi): `Заголовок\|Описание` → строки репитера |
| `tariffs` | таблица `<таблица>r` | `TARIFFS` (multi): `Заголовок\|Цена\|Включи1\|Включи2;Включи3\|флаг` |

Семантика `TARIFFS`: третий сегмент — первый пункт «что входит», четвёртый —
остальные через `;`, пятый непустой — «популярный тариф». Разбор повторяет
источник (`components/bitrix/news.detail/nlb_service/template.php:48-86`).

### 4.6. `Nlb\Printing` (4 свойства)

| Колонка | Фактический тип |
|---|---|
| `icon` | `text`, nullable |
| `color` | `text`, nullable |
| `price_from` | `int`, nullable |
| `unit` | `text`, nullable |

## 5. Заявки

### 5.1. `nlb_requests` (9 свойств)

`email` varchar(191), `phone` varchar(32), `subject` varchar(255), `message` text,
`page` varchar(255), `source` varchar(64), `created_by` varchar(191),
`date_processed` datetime nullable, `status` enum(`new`,`in_progress`,`done`) default `new`.

### 5.2. `nlb_bookings` (14 свойств)

`service` varchar(191), `service_code` varchar(64), `tariff` varchar(191),
`price` varchar(64), `date` date, `time` varchar(8), `person` varchar(191),
`phone` varchar(32), `email` varchar(191), `comment` text, `source` varchar(64),
`created_by` varchar(191), `date_processed` datetime nullable,
`status` enum(`new`,`confirmed`,`cancelled`) default `new`.

Индексы: `(status, date)`, `(service_code, date)`, `email`.

### 5.3. `nlb_orders` (12 свойств)

`item` varchar(191), `article` varchar(64), `price` int unsigned, `qty` int unsigned,
`person` varchar(191), `phone` varchar(32), `email` varchar(191), `comment` text,
`source` varchar(64), `created_by` varchar(191), `date_processed` datetime nullable,
`status` enum(`new`,`processing`,`done`,`cancelled`) default `new`.

### 5.4. `nlb_slots` — новое (P1-20, P1-9)

| Колонка | Тип | Примечание |
|---|---|---|
| `id` | bigint unsigned PK | — |
| `service_code` | varchar(64) | код услуги |
| `date` | date | — |
| `time` | varchar(8) | `HH:MM` |
| `capacity` | int unsigned default 1 | вместимость |
| `taken` | int unsigned default 0 | занято |
| `booking_id` | bigint nullable | ссылка на бронь |

**`UNIQUE (service_code, date, time)`** — единственный способ закрыть гонку слота.
Запись — только в `DB::transaction(...)` с `lockForUpdate()` и перехватом `QueryException`
на дубликат → `409`. Проверка «прочитал-записал» без уникального индекса — дефект,
а не реализация.

### 5.5. `nlb_subscribers` (P0-5)

`email` varchar(191) UNIQUE, `token` varchar(64) UNIQUE, `confirmed_at` datetime nullable,
`unsubscribed_at` datetime nullable, `created_at`, `updated_at`.
Double opt-in: подписка активна только после подтверждения по токену.

## 6. Перечисления

Значения берутся из `db/config/iblocks.php` и **не расширяются** кодом:

| Перечисление | Значения |
|---|---|
| `requests.status` | `new`, `in_progress`, `done` |
| `bookings.status` | `new`, `confirmed`, `cancelled` |
| `orders.status` | `new`, `processing`, `done`, `cancelled` |
| `events.category` | `literature`, `children`, `tech`, `lecture` |
| `events.format` | `offline`, `online`, `hybrid` |
| `books.age_limit` | `all`, `kids`, `adult` |
| `books.available` | `available`, `reserved`, `unavailable` |
| `color` (collections, services, printing) | `primary`, `green`, `sky`, `pink`, `orange` |
| `news.tags` | `books`, `digital`, `events`, `kids`, `science`, `library` |

Правило: ограничение в схеме (`ENUM`, `CHECK`) — это `500` в ответе MySQL, а не `400`
пользователю. Поэтому диапазон и белый список проверяются **до** записи — в `FormRequest`
и в модели, а не только в БД.

## 7. Импорт

```
php artisan nlb:import --source="D:/nlbbootstrap-main/db/config/data" [--dry-run]
```

Факт прогона 10.11.2026: первый запуск — 91 элемент и 12 разделов, второй — 0 новых,
91 обновление (таблица в выводе команды).

- Ключ идемпотентности — `xml_id`: элементы `nlb-news-001`, `nlb-evt-001`,
  `nlb-book-001`, `nlb-col-001`, `nlb-srv-001`, `nlb-prn-001`; разделы
  `nlb-sec-<iblock>-<code>` (`nlb-sec-books-fiction`).
- Разделы создаются до элементов (нужны `id` для `section_id`).
- `properties` раскладываются по колонкам согласно разделу 4.
- `L`-значения пишутся кодом (`xml_id` списка), не русским текстом.
- `features`/`tariffs` пишутся в таблицу репитера через `$record->features()->make()`;
  перед записью старые строки удаляются, `sort_order` = порядок в выгрузке.
- `--dry-run` считает, но не пишет; при отсутствии каталога или `iblocks.php` команда
  возвращает код 1, а не «0 создано».
- Импорт **не удаляет** записи, созданные в админке вручную (нет `delete` по таблице).
- Дата: `active_from` парсится как `Y-m-d H:i:s`/`Y-m-d` и сохраняется без сдвига на
  сутки (`2026-11-02 10:00:00` читается обратно как `2026-11-02 10:00`).
- Тесты: `vendor/phpunit/phpunit/phpunit -c phpunit.nlb.xml` (sqlite `:memory:`,
  фикстуры в `plugins/nlb/library/tests/fixtures/db/config`).

## 8. Что переносится из миграций источника

| Миграция источника | Куда |
|---|---|
| `001_iblock_types`, `002_iblocks` | таблицы контента (Tailor) |
| `003_properties` | колонки (раздел 4) |
| `004_sections` | таблицы разделов Tailor (`Nlb\NewsSection` и др., 12 записей) |
| `005_elements` | импорт 91 элемента |
| `006_menus`, `011_section_menus` | компонент `Menu` + данные навигации |
| `007_site_template` | тема `nlb` (layout) |
| `008_requests`, `012_booking_mail`, `013_request_source` | `nlb_requests`, письма, поле `source` |
| `009_iblocks_sync` | не переносится (синхронизация инфоблоков Битрикса) |
| `010_souvenirs` | `Nlb\Book`, `Nlb\Collection`, `Nlb\Printing` (фото сувениров) |

## 9. Ограничения

- Мок БД не доказывает существование колонки. Проверка схемы — только
  `php artisan october:migrate` + `php artisan tailor:migrate` на чистой БД и живой запрос.
- Массовое удаление (`delete_bulk`) пишется в аудит: удаление заявки без следа —
  дефект, а не оптимизация.
- Апсерт без `UNIQUE`-ключа молча вырождается в `INSERT`. У контента Tailor `UNIQUE`
  невозможен (`xml_id` — `text`), ключ держит код импортёра; у Eloquent-таблиц
  `UNIQUE(service_code, date, time)` объявляется в миграции, а не «подразумевается».
