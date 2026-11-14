# Безопасность и производительность

Перенос не должен ухудшить то, что уже есть: 100/100/100/100 на мобильном профиле,
ноль внешних CSS-запросов, инлайн CSS ≤ 56,4 КБ.

## 1. Заголовки и CSP

Источник — `elv_security.php` (77 строк): CSP, `X-Frame-Options`, `Referrer-Policy`,
`Permissions-Policy`. В October набор разделён осознанно, потому что два
заголовка зависят от запроса и статикой не ставятся:

| Заголовок | Где | Почему |
|---|---|---|
| `Content-Security-Policy` | `SecurityHeaders` (PHP) | в `script-src` одноразовый `nonce` на каждый ответ, nginx его не знает |
| `X-Frame-Options` | `SecurityHeaders` (PHP) | фронт `DENY`, админка `SAMEORIGIN` — зависит от пути |
| `Strict-Transport-Security` | nginx 443 (PHP — только если запрос уже HTTPS) | за прокси `isSecure()` не видит `X-Forwarded-Proto`, HSTS обязан ставить периметр |
| `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` | `SecurityHeaders` **и** `nginx.conf` | одинаковы для всех; nginx скрывает версию PHP (`fastcgi_hide_header`), чтобы не было дублей |

Middleware — `plugins/elv/library/classes/Http/SecurityHeaders.php`, регистрация
в `Plugin.php::boot()` рядом с `CompressResponse` и `LegacyRedirect`.

CSP и инлайн-стили: CSS встраивается в документ намеренно (это и даёт FCP 0,9 с),
поэтому `style-src` требует `'unsafe-inline'` **осознанно** — инлайн-блок
формируется сервером, а не пользователем. `script-src` — `'self'` плюс nonce:
инлайновый скрипт фильтров (`themes/elv/partials/filter-script.htm:1`) помечен
`nonce="{{ elv_csp_nonce() }}"`, `'unsafe-inline'` для скриптов не выдаётся.
`unsafe-eval` не разрешён нигде.

Админка исключена из CSP: October подключает инлайновые скрипты и eval в бэкенде,
строгая политика ломает редакторы и виджеты. Для неё остаются статические
заголовки и `X-Frame-Options: SAMEORIGIN` (всплывающие окна).

Приёмка: `node tools/check-headers.mjs` — 12 публичных страниц, CSP с уникальным
nonce, nonce скрипта совпадает с заголовком, админка без CSP, 404 с заголовками.
Дополнительно: `curl -I` на HTML и на статике; в консоли браузера нет нарушений
CSP. Заголовки статики на проде ставит nginx (`nginx.conf`), потому что файлы
до PHP не доходят.

`GET /health` — исключение из «красивых» ответов: тело только с именами
компонентов (`db`, `cache`, `queue`) и «да/нет», текст исключения уходит в лог,
`Cache-Control: no-store`, сессия не создаётся. Иначе проба на 503 сама стала бы
источником утечки (DSN, SQL, пути) — это проверяют `tests/HealthTest.php` и
`node tools/check-health.mjs`.

## 2. Бюджеты

| Метрика | Предел | Как мерить |
|---|---|---|
| Встроенный CSS после минификации | ≤ 56,4 КБ | `node tools/check-css-budget.mjs` |
| Документ главной | ≤ 190 412 знаков | длина HTML главной |
| initial JS | без роста относительно источника | сумма `bootstrap.bundle` + `script.js` |
| Внешние CSS-запросы | 0 | waterfall Lighthouse / grep `<link rel="stylesheet"` |
| Шрифты | системные | grep `fonts.googleapis`, `@font-face` с внешним URL |

Превышение бюджета — не «готово». Причина превышения ищется в разметке/данных,
а не «оптимизируется» удалением слайдов, секций или контента.

## 3. Картинки

- `tools/build-image-variants.php` (GD, `imagewebp`, `imageavif`) собирает варианты
  480/960/1280 — переносится как есть.
- Вывод — компонент `Picture`: `<picture>` с `srcset`/`sizes`, `width`/`height`
  у всех изображений (иначе CLS), `fetchpriority="high"` только у hero,
  `loading="lazy"` ниже первого экрана.
- 59 позиций без снимков (books 24, printing 29, collections 6) — дефект источника,
  пункт P1-11, а не повод ставить заглушки молча.

## 4. Кеш и сжатие

| Что | Как |
|---|---|
| Инлайн CSS | кеш по `mtime` файлов `bootstrap.min.css` и `styles.css` |
| Поисковый индекс | таблицы + команда `elv:search-index`, хуки на `saved`/`deleted` |
| HTML | без полностраничного кеша: TTFB в October дешевле на порядок |
| Конфиг | `php artisan config:clear` после правок `.env` |
| Сжатие | gzip в периметре (`nginx.conf`) **и** в приложении (`CompressResponse`) |
| Ассеты | `Cache-Control: public, max-age=31536000, immutable` на `/themes|plugins|modules/*/assets|resources/*`, `max-age=604800` на `/storage/app/*` |

Рассинхрон «файл есть, а его никто не пересобрал» недопустим: отдельного
`styles.min.css` не существует, минификация — на лету.

### 4.1. Сжатие: почему это не косметика

Замер 11.11.2026 на главной: документ **188 907 → 33 817 байт** (gzip, level 6),
`script.js` **75 359 → 17 337 байт**. Без сжатия Lighthouse давал
**Performance 83** (FCP 1,7 с, LCP 3,7 с), со сжатием — **100** (FCP 0,9 с,
LCP 1,7 с, TBT 0–60 мс, CLS 0). Причина одна: 188 КБ HTML качались дольше,
чем рендерился документ, а LCP-картинка запрашивалась уже после их разбора.

Где живёт:

1. `nginx.conf` — `gzip on` + `expires` в статических `location`. Это прод:
   статику и HTML на проде отдаёт периметр, приложение до этого не доходит.
2. `plugins/elv/library/classes/Http/CompressResponse.php` — глобальный
   middleware (регистрация в `Plugin.php::boot()`): gzip для текстовых
   ответов и `Cache-Control` для ассетов. Нужен там, где nginx нет
   (shared-хостинг, `php artisan serve`). Уже сжатый ответ middleware не
   трогает — сравнивает `Accept-Encoding` и наличие `Content-Encoding`,
   поэтому двойного сжатия с nginx не бывает.
3. `server.php` + `tools/serve-static.php` — роутер для `php artisan serve`.
   Стандартный роутер Laravel отдаёт существующие файлы напрямую, мимо
   приложения: локальный замер видел несжатые 75 КБ JS и без `Cache-Control`,
   то есть мерил не то, что увидит посетитель. На проде файл не участвует.

Проверка: `curl -sI -H 'Accept-Encoding: gzip' /themes/elv/assets/js/script.js`
показывает `Content-Encoding: gzip`, `Content-Length: 17337`,
`Cache-Control: public, max-age=31536000, immutable`.

Отдельно: `backdrop-filter: blur(16px)` в шапке — самая дорогая краска на
странице (5 вхождений в `styles.css:403-404, 638-639, 3161`). Прогон с
`backdrop-filter: none` даёт TBT 10–60 мс вместо 0–110 мс. Оставлено как в
макете: это дизайн-решение, а не дефект, и 100 достигается без его снятия.

## 5. Протокол Lighthouse

1. Прогреть страницу (иначе первый прогон даёт ложные 88).
2. Мобильный профиль PSI (simulated throttling), сервер с gzip: локально это
   `php artisan serve --host=127.0.0.1 --port=8080` (роутер `server.php`
   сжимает статику так же, как `nginx.conf` на проде).
3. Три прогона подряд, все **100/100/100/100** (Performance, Accessibility,
   Best Practices, SEO).
4. Сохранить числа: FCP, LCP, TBT, CLS — в `Что сделано.txt`.
5. Если 100 не выходит — сначала замер и доказательство (три прогона, waterfall),
   потом вывод. «Ускорить» за счёт контента запрещено.

## 6. Доступность

- axe/Lighthouse a11y 100 на всех типах страниц: главная, список, детальная, форма,
  поиск, 404.
- Иконка-кнопка без текста — `aria-label`; текущий пункт — `aria-current="page"`;
  модалки — `aria-labelledby`; чат — `role="log"`, `aria-live="polite"`.
- Фокус не теряется при открытии модалок и чата, клавиатурная навигация работает.

## 7. Приватность и данные

- Cookie — только технические (сессия, вход, защита форм), аналитики и трекеров нет.
- Сообщения чата не логируются и не сохраняются (как в источнике:
  `local/ajax/chat.php` — «текст не сохраняется и не пишется в журнал»).
- Персональные данные заявок — в БД, доступ только через админку с авторизацией.
- Секреты — только `.env`. В документации и коммитах их нет.
- Бэкап БД перед миграциями на проде; миграции forward-only, откат — из бэкапа.
