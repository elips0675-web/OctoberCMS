# База данных: сущности, поля, индексы

## 1. Зачем отдельный слой

В источнике схема — это `db/config/iblocks.php` (28 832 байта) + 13 PHP-миграций
(`db/migrations/001…013`), которые создают инфоблоки, свойства, разделы, элементы, меню,
шаблон сайта и три формы. В October схема — обычные Laravel-миграции, а контент —
Tailor-записи поверх своих таблиц.

Схему источника **не копируем вслепую**: один инфоблок → одна таблица, свойства → колонки
или JSON, `L`-списки → словари/`enum`.

## 2. Таблицы

| Таблица | Тип | Источник | Записей при импорте |
|---|---|---|---|
| `nlb_news` | Tailor | `news` | 10 |
| `nlb_events` | Tailor | `events` | 6 |
| `nlb_collections` | Tailor | `collections` | 6 |
| `nlb_books` | Tailor | `books` | 24 |
| `nlb_services` | Tailor | `services` | 8 |
| `nlb_printing` | Tailor | `printing` | 37 |
| `nlb_requests` | Eloquent | `requests` | 0 (истории нет) |
| `nlb_bookings` | Eloquent | `bookings` | 0 (истории нет) |
| `nlb_orders` | Eloquent | `orders` | 0 (истории нет) |
| `nlb_slots` | Eloquent | — (новое, P1-9) | генерируется |
| `nlb_subscribers` | Eloquent | — (P0-5) | 0 |
| `nlb_push_subscriptions` | Eloquent | `nlb_push.php` (JSON-файл) | 0 |
| `nlb_search_documents`, `nlb_search_terms` | Eloquent | `db/search/index.json` | 99 / 1 340 |

Разделы Tailor — штатная механика (`tailor_structure`), 12 записей: `news` 2,
`events` 4, `books` 6.

## 3. Общие колонки контентных таблиц

| Колонка | Тип | Источник |
|---|---|---|
| `id` | bigint unsigned, PK | — |
| `xml_id` | varchar(64), **UNIQUE** | `nlb-<iblock>-<N>`, разделы `nlb-sec-<iblock>-<code>` |
| `slug` | varchar(191), UNIQUE | `code` элемента |
| `title` | varchar(255) | `name` |
| `sort` | int, default 100 | `sort` |
| `published_at` | datetime, nullable | `active_from` |
| `excerpt` | text, nullable | `preview_text` |
| `content` | longtext, nullable | `detail_text` |
| `image` | varchar(255), nullable | `image` (у 32 из 91 элемента) |
| `parent_id` | bigint, nullable | `section` |
| `created_at`, `updated_at` | datetime | — |

`xml_id` — **естественный ключ идемпотентного импорта**. Повторный `nlb:import` не создаёт
дублей: `updateOrCreate(['xml_id' => ...], [...])`. Это проверяется тестом «второй прогон —
0 новых строк».

## 4. Поля контента

### 4.1. `nlb_news` (4 свойства)

| Колонка | Тип | Источник | Значения |
|---|---|---|---|
| `author` | varchar(191) | `AUTHOR` (S) | — |
| `tags` | json | `TAGS` (L, multi) | `books`, `digital`, `events`, `kids`, `science`, `library` |
| `read_time` | varchar(32) | `READ_TIME` (S) | — |
| `source_url` | varchar(255) | `SOURCE_URL` (S) | — |

### 4.2. `nlb_events` (8 свойств)

| Колонка | Тип | Источник | Значения |
|---|---|---|---|
| `event_date` | datetime | `DATE` (DateTime) | — |
| `place` | varchar(191) | `PLACE` | — |
| `category` | varchar(32) | `CATEGORY` (L) | `literature`, `children`, `tech`, `lecture` |
| `format` | varchar(16) | `FORMAT` (L) | `offline`, `online`, `hybrid` |
| `seats` | int unsigned, nullable | `SEATS` (N) | — |
| `price` | varchar(64), nullable | `PRICE` | — |
| `duration` | varchar(64), nullable | `DURATION` | — |
| `register_url` | varchar(255), nullable | `REGISTER_URL` | — |

### 4.3. `nlb_collections` (3 свойства)

| Колонка | Тип | Значения |
|---|---|---|
| `items_count` | int unsigned | — (`COUNT`) |
| `color` | varchar(16) | `primary`, `green`, `sky`, `pink`, `orange` |
| `icon` | varchar(32) | — |

### 4.4. `nlb_books` (9 свойств)

| Колонка | Тип | Значения |
|---|---|---|
| `author` | varchar(191) | — |
| `year` | varchar(8) | — |
| `isbn` | varchar(32) | — |
| `publisher` | varchar(191) | — |
| `pages` | int unsigned | — |
| `age_limit` | varchar(16) | `all`, `kids`, `adult` |
| `available` | varchar(16) | `available`, `reserved`, `unavailable` |
| `shelf` | varchar(32) | — |
| `price` | int unsigned | — |

Внимание: названия разделов `books` в источнике — `Брелоки`, `Посуда и фарфор`, `Одежда`,
`Канцелярия`, `Магниты и открытки`, `Аксессуары` (коды `fiction`, `history`, `science`,
`children`, `business`, `art`). Это остаток от сувениров. Коды и названия переносятся
**как есть**: переименование — отдельный пункт бэклога, не самодеятельность переезда.

### 4.5. `nlb_services` (11 свойств)

| Колонка | Тип | Примечание |
|---|---|---|
| `icon` | varchar(32) | — |
| `color` | varchar(16) | те же 5 значений |
| `subtitle` | varchar(255) | — |
| `price_from` | int unsigned, nullable | — |
| `price_label` | varchar(64) | — |
| `duration` | varchar(64) | — |
| `schedule` | json | `SCHEDULE` (multi) — построчно |
| `phone` | varchar(32) | — |
| `email` | varchar(191) | — |
| `features` | json | `FEATURES` (multi) |
| `tariffs` | json | `TARIFFS` (multi) |

### 4.6. `nlb_printing` (4 свойства)

| Колонка | Тип |
|---|---|
| `icon` | varchar(32) |
| `color` | varchar(16) |
| `price_from` | int unsigned, nullable |
| `unit` | varchar(64) |

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

- Ключ идемпотентности — `xml_id` (`nlb-books-1`, `nlb-sec-books-fiction`).
- Разделы создаются до элементов (порядок важен для `parent_id`).
- `properties` раскладываются по колонкам согласно разделу 4.
- `L`-значения пишутся кодом (`xml_id` списка), не русским текстом.
- Повторный запуск: 0 новых строк, 91 обновлённая запись.
- Импорт **не удаляет** записи, созданные в админке вручную.
- Дата: `active_from` парсится как `Y-m-d`, без сдвига на сутки (известные грабли
  источника: DATE уезжает на день при `strtotime` в UTC).

## 8. Что переносится из миграций источника

| Миграция источника | Куда |
|---|---|
| `001_iblock_types`, `002_iblocks` | таблицы контента (Tailor) |
| `003_properties` | колонки (раздел 4) |
| `004_sections` | `tailor_structure` (12 разделов) |
| `005_elements` | импорт 91 элемента |
| `006_menus`, `011_section_menus` | компонент `Menu` + данные навигации |
| `007_site_template` | тема `nlb` (layout) |
| `008_requests`, `012_booking_mail`, `013_request_source` | `nlb_requests`, письма, поле `source` |
| `009_iblocks_sync` | не переносится (синхронизация инфоблоков Битрикса) |
| `010_souvenirs` | `nlb_books`, `nlb_collections`, `nlb_printing` (фото сувениров) |

## 9. Ограничения

- Мок БД не доказывает существование колонки. Проверка схемы — только
  `php artisan migrate:fresh --seed` + живой запрос.
- Массовое удаление (`delete_bulk`) пишется в аудит: удаление заявки без следа —
  дефект, а не оптимизация.
- Апсерт без `UNIQUE`-ключа молча вырождается в `INSERT` — поэтому `xml_id` и
  `UNIQUE(service_code, date, time)` объявлены в миграциях, а не «подразумеваются».
