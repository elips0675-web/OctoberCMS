# Архитектура

## 1. Место в стеке

Сайт — обычное Laravel-приложение October CMS. Поток запроса:

```
nginx/apache → index.php (корень проекта) → October bootstrap → plugin routes / CMS page
  → компонент (Header, Menu, Breadcrumbs, List, Detail, Form, Search)
  → Eloquent / Tailor-запись
  → Blade-шаблон темы nlb → HTML (инлайн CSS)
```

Никакого SPA, клиентского роутинга и сборки фронтенда. JS — `bootstrap.bundle` +
`script.js` как в источнике.

## 2. Плагин `nlb/library`

```
plugins/nlb/library/
  Plugin.php               # регистрация компонентов, маршрутов, консольных команд, хуков
  blueprints/              # 6 контентных сущностей Tailor (YAML)
  models/                  # Eloquent: Request, Booking, Order, Slot, Service, Subscriber
  updates/                 # миграции (forward-only)
  classes/
    Search.php             # порт BM25 (источник: nlb_search.php, 1 272 строки)
    TextFilter.php         # порт фильтра чата (nlb_text_filter.php, 176 строк)
    Seo.php                # порт nlb_seo.php (229 строк)
    Security/Headers.php   # порт nlb_security.php (77 строк)
    Media.php              # nlb_picture(), nlb_minify_css() (268 строк)
    BookingService.php     # слоты: генерация, занятость, транзакция
    Push.php               # порт nlb_push.php (769 строк)
  components/              # Header, Footer, Menu, Breadcrumbs, Picture, Form, Search, Chat
  console/                 # nlb:import, nlb:search-index, nlb:slots-generate
  http/                    # контроллеры Search, Chat, Api\Booking
  routes.php               # web + ajax + api
  tests/                   # PHPUnit + RefreshDatabase
```

## 3. Tailor или Eloquent: правило разделения

- **Tailor** — read-mostly контент, который правится в админке полями: `news`, `events`,
  `collections`, `books`, `services`, `printing`.
- **Eloquent** — всё, что требует транзакций, уникальных индексов, статусных переходов,
  идемпотентности и тестов: `requests`, `bookings`, `orders`, `slots`, `subscribers`,
  `push_subscriptions`.

Исключение: `services` меняется и в Tailor (контент), и в Eloquent-таблице `slots`
(расписание). Источник правды по расписанию — Eloquent, blueprint услуги хранит только
презентационные поля.

## 4. Таблица переноса: 9 инфоблоков → blueprints/модели

Источник — `db/config/iblocks.php` (28 832 байта): 9 инфоблоков, 74 свойства, 12 разделов,
91 элемент. Проверено разбором файла на этапе 0.

### 4.1. Контент (Tailor-blueprints, 6 сущностей, 27 элементов)

| Хендл | Свойства источника → поле blueprint | Тип поля | Разделов | Элементов |
|---|---|---|---|---|
| `news` | `AUTHOR` | `text` | 2 (`library`, `announcements`) | 10 |
| | `TAGS` (L, multi: books, digital, events, kids, science, library) | `taglist` | | |
| | `READ_TIME` | `text` | | |
| | `SOURCE_URL` | `text` (url) | | |
| `events` | `DATE` (DateTime) | `datepicker` (`mode: datetime`) | 4 (`literature`, `children`, `tech`, `lectures`) | 6 |
| | `PLACE` | `text` | | |
| | `CATEGORY` (L: literature, children, tech, lecture) | `dropdown` | | |
| | `FORMAT` (L: offline, online, hybrid) | `dropdown` | | |
| | `SEATS` (N) | `number` | | |
| | `PRICE` | `text` | | |
| | `DURATION` | `text` | | |
| | `REGISTER_URL` | `text` (url) | | |
| `collections` | `COUNT` (N) | `number` | — | 6 |
| | `COLOR` (L: primary, green, sky, pink, orange) | `dropdown` | | |
| | `ICON` | `text` | | |
| `books` | `AUTHOR` | `text` | 6 (`fiction`, `history`, `science`, `children`, `business`, `art`) | 24 |
| | `YEAR` | `text` | | |
| | `ISBN` | `text` | | |
| | `PUBLISHER` | `text` | | |
| | `PAGES` (N) | `number` | | |
| | `AGE_LIMIT` (L: all, kids, adult) | `dropdown` | | |
| | `AVAILABLE` (L: available, reserved, unavailable) | `dropdown` | | |
| | `SHELF` | `text` | | |
| | `PRICE` (N) | `number` | | |
| `services` | `ICON` | `text` | — | 8 |
| | `COLOR` (L: primary, green, sky, pink, orange) | `dropdown` | | |
| | `SUBTITLE` | `text` | | |
| | `PRICE_FROM` (N) | `number` | | |
| | `PRICE_LABEL` | `text` | | |
| | `DURATION` | `text` | | |
| | `SCHEDULE` (S, multi) | `repeater`/`textarea`, вывод построчно | | |
| | `PHONE` | `text` | | |
| | `EMAIL` | `text` (email) | | |
| | `FEATURES` (S, multi) | `taglist` | | |
| | `TARIFFS` (S, multi) | `repeater`/`textarea` | | |
| `printing` | `ICON` | `text` | — | 37 |
| | `COLOR` (L: primary, green, sky, pink, orange) | `dropdown` | | |
| | `PRICE_FROM` (N) | `number` | | |
| | `UNIT` | `text` | | |

Итого контентных элементов: 10 + 6 + 6 + 24 + 8 + 37 = **91**. Свойств: 4 + 8 + 3 + 9 +
11 + 4 = **39**. Разделов: 2 + 4 + 6 = **12**.

Общие поля элемента в источнике (`db/config/data/*.php`): `xml_id`, `sort`, `name`, `code`,
`active_from`, `section`, `image` (не у всех), `preview_text`, `detail_text`, `properties`.
В Tailor это: `title`, `slug`, `published_at`, `parent` (раздел), `image` (`mediafinder`),
`excerpt`, `content`, плюс поля свойств.

Фото: `image` есть у 32 элементов из 91 (services 8, events 6, news 10, printing 8);
без снимка 59 — books 24, printing 29, collections 6. Это дефект источника (P1-11),
а не свойство переезда.

### 4.2. Заявки (Eloquent, 3 сущности, 35 свойств)

| Таблица | Поля источника → колонки | Перечисление |
|---|---|---|
| `nlb_requests` | `EMAIL`, `PHONE`, `SUBJECT`, `MESSAGE`, `PAGE`, `SOURCE`, `CREATED_BY`, `DATE_PROCESSED` (datetime), `STATUS` | `new`, `in_progress`, `done` (по умолчанию `new`) |
| `nlb_bookings` | `SERVICE`, `SERVICE_CODE`, `TARIFF`, `PRICE`, `DATE`, `TIME`, `PERSON`, `PHONE`, `EMAIL`, `COMMENT`, `SOURCE`, `CREATED_BY`, `DATE_PROCESSED`, `STATUS` | `new`, `confirmed`, `cancelled` (по умолчанию `new`) |
| `nlb_orders` | `ITEM`, `ARTICLE`, `PRICE` (N), `QTY` (N), `PERSON`, `PHONE`, `EMAIL`, `COMMENT`, `SOURCE`, `CREATED_BY`, `DATE_PROCESSED`, `STATUS` | `new`, `processing`, `done`, `cancelled` (по умолчанию `new`) |

Свойств: 9 + 14 + 12 = **35**. Вместе с контентом — **74**, как в источнике.

Правило схемы: перечисление в БД (`ENUM`/белый список кода) обязано совпадать с формой.
Клиент отбивается `FormRequest` **до** записи, а не `QueryException` из MySQL.

## 5. Компоненты

| Компонент | Что делает | Источник |
|---|---|---|
| `Header` | topbar, бренд, меню, поиск, кабинет, спрайт, инлайн CSS, meta | `header.php` |
| `Menu` | верхнее меню, выпадающие списки разделов (услуги, печать) | `db/menu/top.menu.php`, `*.left.menu.php` |
| `Breadcrumbs` | крошки по дереву страниц | `header.php:256` |
| `Search` | модалка, мгновенная выдача, страница `/search/` | `header.php:228`, `local/ajax/search.php` |
| `Chat` | виджет, правила ответов, промо-подсказки | `footer.php:208`, `script.js:169/281` |
| `Subscribe` | форма в подвале, double opt-in, отписка | `footer.php:92` |
| `Feedback` | модалка и страница `/feedback/` | `footer.php:250` |
| `Booking` | форма брони на детальной услуги | `nlb_forms.php` (`booking`) |
| `Order` | заказ сувенира | `nlb_forms.php` (`order`) |
| `Picture` | `<picture>` с WebP/AVIF, `srcset` 480/960/1280 | `nlb_media.php` (`nlb_picture()`) |
| `Footer` | подвал, подписка, FAB, чат, модалки, скрипты | `footer.php` |

## 6. Маршруты

### 6.1. Публичные (110 адресов)

| Шаблон | Страница |
|---|---|
| `/` | главная |
| `/about/`, `/contacts/`, `/rules/`, `/privacy/`, `/terms/`, `/personal/`, `/auth/` | CMS-страницы |
| `/feedback/`, `/subscribe/` | формы (GET + POST) |
| `/search/` | поиск (`?q=`, `&limit=`) |
| `/sitemap/`, `/sitemap.xml` | карта сайта, XML |
| `/news/`, `/news/{slug}/` | список и детальная |
| `/events/`, `/events/{slug}/` | список и детальная |
| `/services/`, `/services/{slug}/` | список и детальная |
| `/printing/`, `/printing/{slug}/` | список и детальная |
| `/books/`, `/books/{slug}/` | список и детальная |
| `/collections/`, `/collections/{slug}/` | список и детальная |
| `/404` | 404 |

Правила источника — `urlrewrite.php:14`: семь правил, `CODE=$1&$2` (query-строка
пробрасывается без потери: `/services/{slug}/?utm_source=...`).

110 адресов инвентаря не значат 110 `<loc>`: в карту сайта входят 106
адресов — 15 статических страниц и 91 детальная. `/404` адресом не является, а
`/auth/`, `/personal/`, `/search/` закрыты в `robots.txt`: `Disallow` и `<loc>`
в карте противоречат друг другу.

### 6.2. Служебные

| Маршрут | Назначение | Контракт |
|---|---|---|
| `POST /feedback/` | обратная связь (P0-4) | `nlb_requests` + письмо в очередь; `201` JSON / `302 ?sent=1#form`; `422` на пустые поля |
| `POST /subscribe/` | подписка (P0-5) | `nlb_subscribers` + письмо double opt-in; повторный адрес не даёт второй строки |
| `GET /subscribe/confirm/{token}` | подтверждение подписки | `200` страница «Подписка подтверждена», `410` на использованный токен |
| `GET /unsubscribe/{token}` | отписка | `200` страница «Вы отписались», повторный переход безопасен |
| `POST /booking/` | бронь услуги (P1-9) | `nlb_bookings` + слот + два письма; `409 SLOT_UNAVAILABLE` на занятое время, `422` вне окна слотов |
| `POST /order/` | заказ сувенира (P1-12) | `nlb_orders` + письмо; `422` на `QTY < 1` и отрицательную цену |
| `GET /ajax/search` | мгновенная выдача | `?q=`, `&limit=`; коды `200/400/405/503` (как `local/ajax/search.php`) |
| `POST /api/chat` | проверка сообщения чата | `200/400/403/405/413/422/503`, `{ok, verdict, error}` |
| `POST /api/bookings` | REST для Telegram-бота (P1-16) | `Bearer`-токен, идемпотентность по `external_id`, `200/201/400/401/409/422/429` |
| `POST /ajax/push/subscribe`, `POST /ajax/push/unsubscribe` | Web Push | VAPID из `.env` |

Формы (`plugins/nlb/library/routes.php`) сидят в группе `web`: обязателен
`_token` (в шаблоне — `{{ nlb_csrf()|raw }}`), лимит `throttle:30,1`.
Поля источника `PROPERTY[EMAIL][0]` разбирает `classes/FormFields.php` в плоские
`EMAIL`, `DATE`, … Возврат — на страницу формы из `Referer` того же хоста,
иначе на `fallbackPath()`: чужой `Referer` не превращается в открытый редирект.

`GET /sitemap.xml` и `GET /robots.txt` — `classes/Http/SeoController.php`:
файлы генерируются, а не лежат в `public/`, потому что домен появится только на
M17, а `Sitemap:`/`<loc>` с чужим доменом уводят поисковиков на чужой сайт.
Адрес берётся из запроса (`url('/')`), как в источнике (`sitemap.php`).

### 6.3. Админка

| Раздел (меню «Библиотека») | Контроллер (`plugins/nlb/library/controllers`) | Право |
|---|---|---|
| Обращения | `Requests.php` — `nlb/library/requests` | `nlb.library.requests` |
| Брони | `Bookings.php` — `nlb/library/bookings` | `nlb.library.bookings` |
| Заказы | `Orders.php` — `nlb/library/orders` | `nlb.library.orders` |
| Подписчики | `Subscribers.php` — `nlb/library/subscribers` | `nlb.library.subscribers` |

Права и меню объявлены в `Plugin.php` (`registerPermissions()`,
`registerNavigation()`): у каждого пункта свой `permissions`, поэтому оператор
видит только выданные разделы. Списки — `ListController` + `columns.yaml`
(поиск по теме, e-mail, телефону, услуге, товару, адресу); правка —
`FormController` + `fields.yaml`, где данные гостя только для чтения
(`disabled`), а редактируется один `status`.

Смена статуса — единственное действие админки, и оно не обходит бизнес-логику:

- `Request::saving` / `Order::saving` ставят `date_processed`, когда статус стал
  «Выполнено»/«Отменён»;
- `Booking::updating` при статусе «Отменена» освобождает слот через
  `BookingService::releaseSlot()` (общий код с `BookingService::cancel()`), а
  возврат отменённой брони в работу запрещён `ApplicationException` — иначе
  слот вернулся бы в продажу без брони.

Демо-подсказка «часть слотов занята» и массовые действия (удаление, экспорт)
не делались: их нет в приёмке.

### 6.4. SEO и старые адреса

| Что | Где | Как работает |
|---|---|---|
| Мета страницы | `themes/nlb/layouts/default.htm:10-35` | `seo_title`/`seo_description` страницы → `<title>`, description, OG, Twitter, canonical, `og:image` |
| Мета записи | `classes/Seo.php:169` | `applyRecord()`: `seo_title`/`seo_description` записи перекрывают имя и `excerpt`, пустые — прежнее поведение; картинка → `seo_image`; зовётся из шести `*-detail.htm:14` |
| JSON-LD | `classes/Seo.php:74` | `Library` (адрес, телефон, часы) + `WebSite` с `SearchAction` + узел записи (`Article`/`Event`/`Product`/`Service`, `recordNode` §211); на всех страницах через `nlb_jsonld()` |
| `robots.txt` | `classes/Seo.php:472` | те же директивы, что в источнике, плюс `Disallow: /admin/`; `Sitemap:` — текущий хост |
| `/rss.xml` | `classes/Rss.php:36` | 20 свежих новостей из `Content::records('Nlb\News')`, сортировка по `published_at` (откат на `created_at`), RSS 2.0 с `atom:self`; отключённая запись не попадает |
| `sitemap.xml` | `classes/Sitemap.php:70` | 15 статических (`PAGES`) + 91 детальная из `Content::records()`; `lastmod` — из `updated_at` записи, у текстовых страниц — `filemtime` |
| 301 | `classes/Http/LegacyRedirect.php:50` | `/index.php`, `/sitemap.php`, `/{section}/index.php`, `/{section}/detail.php?CODE=slug` |
| PWA | `classes/Pwa.php:22` | `SHELL` — один список файлов оболочки; `manifest.webmanifest`, `sw.js` и `offline.html` отдаёт `classes/Http/PwaController.php:19` с корня (`routes.php:24-26`), иначе область service worker ограничилась бы `/themes/nlb/`; версия кеша — `mtime` файлов, восемь hex |
| Заголовки | `classes/Http/SecurityHeaders.php:27` | CSP с nonce на каждый ответ, `X-Frame-Options` (фронт `DENY`, админка `SAMEORIGIN`), HSTS только на HTTPS; статические заголовки дублирует `nginx.conf` |

Карта сайта собирается из тех же записей, что показывает тема: отключённая или
удалённая запись в карту не попадает, а новая страница темы без строки в
`Sitemap::PAGES` роняет `tests/SeoTest.php`, а не выпадает из индекса молча.

`LegacyRedirect` читает сырой `REQUEST_URI` (`:72`): Symfony принимает
`/index.php` за `SCRIPT_NAME` и `path()` отдаёт `/`, иначе старый адрес главной
не отличить от самой главной. `CODE` отбрасывается, остальные параметры
(`utm_*`, `PAGEN_1`) переносятся в новый адрес. Middleware подключён в
`Plugin.php:100` только вне консоли — юнит-тесты гоняют его напрямую; рядом
`SecurityHeaders` (`Plugin.php:104`) и `CompressResponse` (`Plugin.php:96`).

Мета записи: у шести blueprints (`news`, `events`, `books`, `services`,
`printing`, `collections`) есть `seo_title` и `seo_description`,
`Seo::applyRecord()` (`classes/Seo.php:169`) выставляет их в `<head>`, пустые
поля откатываются на имя записи и `excerpt` (обрезка 160 знаков). Canonical
собирается как `getSchemeAndHttpHost() . $path` (`Seo::canonical()`,
`classes/Seo.php:52`), иначе `url('/x/')` срезает хвостовой слэш и адрес
расходится с `<loc>` карты. Микроразметка записи (`Article`, `Event`,
`Product`, `Service`) — `Seo::recordNode()` (`classes/Seo.php:211`), Stage 9;
остаток расширенного SEO (BreadcrumbList, FAQ, CWV) — P2-22.

## 7. Очереди и планировщик

| Задача | Тип | Что делает |
|---|---|---|
| `FeedbackNotification` | `Mailable` в очереди | письмо сотрудникам об обращении с сайта |
| `BookingNotification` | `Mailable` в очереди | письмо сотрудникам о новой брони |
| `BookingConfirmation` | `Mailable` в очереди | подтверждение брони клиенту |
| `OrderNotification` | `Mailable` в очереди | письмо сотрудникам о заказе сувенира |
| `SubscriptionConfirmation` | `Mailable` в очереди | double opt-in и ссылка отписки |
| `NotifyStaffPush` | план | Web Push персоналу о брони |
| `nlb:slots-generate` | schedule (ежедневно) | генерация слотов из расписания услуг |
| `nlb:search-index` | schedule + хуки `saved`/`deleted` | переиндексация BM25 |
| `ExpireUnconfirmedBookings` | план | снятие просроченных броней |

Отдельных job-классов нет: письмо ставит в очередь контроллер формы
(`Mail::queue(new …)`), письма — `plugins/nlb/library/classes/Mail/`. Очередь
`QUEUE_CONNECTION=database`, поэтому без воркера (`php artisan queue:work`)
письма лежат в таблице `jobs` и не уходят — это требование хостинга, а не код.

SMTP у владельца нет: `MAIL_MAILER=log`, письма проверяются в
`storage/logs/system.log` и Mailpit. Код обязан работать с реальным SMTP без правок.

## 8. Медиа и CSS

- Картинки: варианты 480/960/1280 в WebP/AVIF собирает `tools/build-image-variants.php`
  (GD, `imagewebp`, `imageavif`) — переносится как есть.
- `<picture>` выводит `Picture`-компонент: `srcset`, `width`/`height`,
  `fetchpriority="high"` у hero, `loading="lazy"` ниже первого экрана.
- CSS: `bootstrap.min.css` (51 758 байт) собирается `tools/purge-css.mjs` из
  `bootstrap.source.min.css` (232 803 байта) по классам, которые реально есть в
  `themes/nlb/**` и `plugins/nlb/**`; каталог `controllers` в обход сканера —
  это разметка админки, её стилизует тема backend, а не CSS сайта. Вместе со
  `styles.css` (84 995 байт) результат минифицируется хелпером
  `Media::minifyCss()` и встраивается инлайном в layout. Кеш — по `mtime`,
  отдельного `styles.min.css` не существует (рассинхрон «файл есть, а его никто
  не пересобрал» недопустим).

## 9. Зависимости

Плагин не тянет сторонних пакетов, кроме идущих с October. Запрещено: демо-плагины,
RainLab.Blog, «универсальные» SEO-пакеты, Tailwind, React, Vite, Google Fonts.
Причина: лишние запросы и лишний CSS убивают 100/100/100/100.

Порядок установки сторонних плагинов и список допустимых — `docs/plugins.md`.
Там же зафиксировано, почему не берём Winter CMS: нет Tailor и Laravel 9 без
security-фиксов.
