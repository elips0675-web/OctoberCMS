# Архитектура

## 1. Место в стеке

Сайт — обычное Laravel-приложение October CMS. Поток запроса:

```
nginx/apache → public/index.php → October bootstrap → plugin routes / CMS page
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
    Booking.php            # слоты: генерация, занятость, транзакция
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

### 6.2. Служебные

| Маршрут | Назначение | Контракт |
|---|---|---|
| `GET /ajax/search` | мгновенная выдача | `?q=`, `&limit=`; коды `200/400/405/503` (как `local/ajax/search.php`) |
| `POST /api/chat` | проверка сообщения чата | `200/400/403/405/413/422/503`, `{ok, verdict, error}` |
| `POST /api/bookings` | REST для Telegram-бота (P1-16) | `Bearer`-токен, идемпотентность по `external_id`, `200/201/400/401/409/422/429` |
| `POST /ajax/push/subscribe`, `POST /ajax/push/unsubscribe` | Web Push | VAPID из `.env` |

## 7. Очереди и планировщик

| Задача | Тип | Что делает |
|---|---|---|
| `SendFormNotification` | `ShouldQueue` | письмо администратору о заявке/брони/заказе |
| `SendBookingConfirmation` | `ShouldQueue` | подтверждение клиенту |
| `SendSubscriptionConfirmation` | `ShouldQueue` | double opt-in |
| `NotifyStaffPush` | `ShouldQueue` | Web Push персоналу о брони |
| `nlb:slots-generate` | schedule (ежедневно) | генерация слотов из расписания услуг |
| `nlb:search-index` | schedule + хуки `saved`/`deleted` | переиндексация BM25 |
| `ExpireUnconfirmedBookings` | schedule | снятие просроченных броней |

SMTP у владельца нет: `MAIL_MAILER=log`, письма проверяются в `storage/logs/laravel.log`
и Mailpit. Код обязан работать с реальным SMTP без правок.

## 8. Медиа и CSS

- Картинки: варианты 480/960/1280 в WebP/AVIF собирает `tools/build-image-variants.php`
  (GD, `imagewebp`, `imageavif`) — переносится как есть.
- `<picture>` выводит `Picture`-компонент: `srcset`, `width`/`height`,
  `fetchpriority="high"` у hero, `loading="lazy"` ниже первого экрана.
- CSS: `bootstrap.min.css` (61 433 байта) урезается `tools/purge-css.mjs`, вместе со
  `styles.css` (81 179 байт) минифицируется хелпером `Media::minifyCss()` и встраивается
  инлайном в layout. Кеш — по `mtime`, отдельного `styles.min.css` не существует
  (рассинхрон «файл есть, а его никто не пересобрал» недопустим).

## 9. Зависимости

Плагин не тянет сторонних пакетов, кроме идущих с October. Запрещено: демо-плагины,
RainLab.Blog, «универсальные» SEO-пакеты, Tailwind, React, Vite, Google Fonts.
Причина: лишние запросы и лишний CSS убивают 100/100/100/100.
