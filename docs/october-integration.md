# Интеграция: что где вызывается

Аналог `docs/bitrix-integration.md` источника. Таблица «источник → цель» с точками вызова.

## 1. Layout и шапка

| Что | Источник | Цель |
|---|---|---|
| `<!doctype>`, `lang`, meta | `header.php:18-23` | `themes/nlb/layouts/default.htm` |
| meta/robots/keywords/description/canonical | `header.php:30-38` | компонент `Header` + `Seo::head()` |
| OG, Twitter, JSON-LD | `header.php:41-42` (`nlb_seo_head()`, `nlb_seo_jsonld()`) | `Seo` |
| manifest, иконки, apple-meta | `header.php:44-51` | layout + файлы в корне веб-корня |
| инлайн CSS | `header.php:70` (`<style data-nlb-css>`) | layout + `Media::minifyCss()` с кешем по `mtime` |
| спрайт SVG | `header.php:79` | `partials/sprite.htm` (как есть) |
| topbar (адрес, часы, телефон) | `header.php:130-152` | компонент `Header` (данные — конфиг сайта) |
| бренд, мобильное меню | `header.php:156-170` | `Header` + Bootstrap collapse |
| верхнее меню | `header.php:172` (`bitrix:menu`, тип `top`, предок `left`) | компонент `Menu` |
| поиск, кабинет, вход | `header.php:191-214` | `Search`, ссылки на `/personal/`, `/auth/` |
| модалка поиска | `header.php:220-252` | `components/Search` |
| крошки | `header.php:254-267` | компонент `Breadcrumbs` (кроме главной) |

## 2. Подвал

| Что | Источник | Цель |
|---|---|---|
| ссылки «О библиотеке … Контакты» | `footer.php:5-101` | `Footer` (навигация `bottom` — 5 пунктов) |
| форма подписки | `footer.php:92` | компонент `Subscribe` → `nlb_subscribers` |
| копирайт, правовые ссылки, Cookie | `footer.php:105-116` | `Footer` + `consent.js` |
| баннеры cookie и PWA | `footer.php:118-167` | partial + `consent.js`, `pwa.js` |
| FAB (Telegram, WhatsApp, звонок, mail, форма, ИИ) | `footer.php:169-199` | partial |
| чат-виджет | `footer.php:208-244` | компонент `Chat` |
| «наверх» | `footer.php:246-248` | partial |
| модалка обратной связи | `footer.php:250-281` | компонент `Feedback` → `nlb_requests` |
| модалка реквизитов | `footer.php:283-306` | partial (заглушки, P0-6) |
| скрипты | `footer.php:308-321` | layout: bundle, `script.js`, `pwa.js`, `push.js`, `consent.js` |

## 3. Меню

Источник — 4 файла `db/menu/*.menu.php`:

| Меню | Пунктов | Куда |
|---|---|---|
| `top` | 7 (Главная, Новости, События, Услуги, Печать, Каталог продукции, Контакты) | `Menu` (`top`) |
| `bottom` | 5 (О библиотеке, Правила, Политика, Карта сайта, Контакты) | `Footer` |
| `services.left` | 9 (все услуги + 8 пространств) | выпадающий список «Услуги» |
| `printing.left` | 9 (все услуги печати + 8) | выпадающий список «Печать» |

Пункт «Интерактивная карта» (`/map/`) в меню **не входит** — страница вне объёма работ.
Поведение сохраняется.

## 4. Формы

| Форма | Источник | Цель | Запись | Письма |
|---|---|---|---|---|
| Обратная связь | `footer.php:258`, `/feedback/` | `Feedback` | `nlb_requests` | админу (очередь) |
| Бронь услуги | детальная `/services/{slug}/` | `Booking` | `nlb_bookings` + `nlb_slots` | клиенту и админу |
| Заказ сувенира | детальная `/books/{slug}/` | `Order` | `nlb_orders` | клиенту и админу |
| Подписка | `footer.php:92`, `/subscribe/` | `Subscribe` | `nlb_subscribers` | double opt-in |

Требования к каждой: `FormRequest`, honeypot, лимитер, редирект `?sent=1` как в источнике,
человеческий текст ошибки (не `QueryException`), `Mail::queue`.

## 5. Поиск

| Что | Источник | Цель |
|---|---|---|
| BM25, математика | `nlb_search.php` (1 272 строки) | `classes/Search.php` |
| индекс | `db/search/index.json` (99 документов, 1 340 терминов, `k1=1.5`, `b=0.75`) | таблицы `nlb_search_documents/terms` |
| сборка индекса | `tools/build-search-index.php --check` | `php artisan nlb:search-index` |
| хуки пересборки | `nlb_search_index_event()` | observers `saved`/`deleted` |
| AJAX | `local/ajax/search.php` | `GET /ajax/search` (коды `200/400/405/503` сохранены) |
| страница | `/search/?q=` | CMS-страница + компонент `Search` |
| живой поиск | `script.js:1296-1367` | `script.js` как есть, `data-search-endpoint` |

Приёмка: 29 кейсов `db/tests/fixtures/search-cases.json` дают те же результаты.

## 6. Чат и ИИ-помощник

| Что | Источник | Цель |
|---|---|---|
| 11 правил ответов | `script.js:169` | данные плагина (`config/chat_rules.php`), не `if` в JS |
| 10 промо-подсказок | `script.js:281` | данные плагина, лимиты: ≤ 3 за сеанс, не раньше 2-го вопроса, не чаще чем через 3, без повторов, `promo:false` у жалоб |
| фильтр 95 кейсов | `nlb_text_filter.php` (176 строк), `db/tests/fixtures/filter-cases.json` | `classes/TextFilter.php` + тот же фикстур |
| серверная проверка | `local/ajax/chat.php` (коды `200/400/403/405/413/422/503`) | `POST /api/chat` |
| клиент | `script.js:155-156` (`USE_REAL_AI=false`, `API_URL='/api/chat'`) | без изменений; LLM не подключать без ключа владельца |

Режим по умолчанию — «правила + передача оператору», честный: живого оператора в демо нет,
и это написано в ответе правила.

## 7. Web Push

| Что | Источник | Цель |
|---|---|---|
| VAPID, шифрование, отправка | `nlb_push.php` (769 строк) | `classes/Push.php`, ключи в `.env` |
| подписки | JSON-файл `subscriptions` | `nlb_push_subscriptions` |
| эндпоинт | `local/ajax/push.php` | `POST /ajax/push/subscribe`, `/ajax/push/unsubscribe` |
| генератор ключей | `tools/gen-vapid.php` | `tools/gen-vapid.php` (как есть) |
| клиент | `assets/js/push.js`, `window.NLB_PUSH` (`footer.php:312-319`) | layout передаёт `enabled`, `publicKey`, `endpoint` |

## 8. SEO, карта сайта, PWA

| Что | Источник | Цель |
|---|---|---|
| meta, OG, canonical | `nlb_seo.php` (229 строк) | layout `themes/nlb/layouts/default.htm:10-35` |
| JSON-LD | `nlb_seo.php` | `Seo::jsonld()` (`plugins/nlb/library/classes/Seo.php:74`) |
| `/sitemap.xml` | `sitemap.php` | `Sitemap::xml()` — 106 адресов (15 статических + 91 детальная) с `lastmod` |
| `/sitemap/` (HTML) | страница | CMS-страница |
| `robots.txt` | корень | маршрут `Seo::robots()`: домен из запроса, а не статический файл |
| 301 со старых адресов | `urlrewrite.php` | `classes/Http/LegacyRedirect.php` |
| `manifest.webmanifest`, `sw.js`, `offline.html` | корень | корень веб-корня (пути не менять) |
| версия SW | `sw.js` | поднимается при изменении ассетов |

Состояние 11.11.2026 (Stage 9): meta, OG, Twitter и canonical отдаёт layout,
у записи своя мета (`Seo::applyRecord()`, `classes/Seo.php:169`) — `seo_title`
и `seo_description` из blueprints перекрывают имя и `excerpt`; картинка записи
уходит в `og:image` и `twitter:image` (`seo_image`), без неё остаётся hero.
JSON-LD `Library` + `WebSite` подключён в `<head>` через `nlb_jsonld()`, а
детальная страница добавляет свой узел: `Article` (новость, автор и даты),
`Event` (начало, место, формат, цена), `Product` (SKU, цена, наличие) и
`Service` (подзаголовок, «от» цена, город); у подборки узла нет — только общая
связка. `price` приводит «1 490 ₽» и «Бесплатно» к числу, `withoutEmpty`
не отдаёт пустые ключи, `reset()` сбрасывает узел между запросами.
`/sitemap.xml` — 200 `application/xml`, 106 `<loc>` с `lastmod` (110 из
инвентаря минус `/404` и три закрытых в robots адреса: `/auth/`,
`/personal/`, `/search/`); `/robots.txt` — 200 `text/plain` с `Sitemap:`
текущего хоста; `/index.php`, `/sitemap.php`, `/{section}/index.php` и
`/{section}/detail.php?CODE=…` — 301 на новые адреса с сохранением `utm_*`.
Canonical совпадает с `<loc>` карты побайтово (`Seo::canonical()`,
`classes/Seo.php:52`).
Проверка — `node tools/check-seo.mjs` (гейт: карта, robots, 301, узел записи,
`og:image`) и `tests/SeoTest.php` (50 тестов).
Осталось: `/rss.xml` — P2-6; `BreadcrumbList`, FAQ-разметка и CWV — P2-22;
валюта цен (₽ в шаблонах и `RUB` в JSON-LD) — P1-24; валидация на живом
домене — M17.

## 9. Безопасность

| Что | Источник | Цель |
|---|---|---|
| CSP, X-Frame-Options, Referrer-Policy, Permissions-Policy | `nlb_security.php` (77 строк) | middleware `SecurityHeaders` (`classes/Http/SecurityHeaders.php`) + `nginx.conf` |
| CSRF | `bitrix_sessid` | Laravel CSRF (формы и AJAX) |
| Лимитеры | `nlb_forms.php` (антиспам) | `RateLimiter` на формы и API |
| Доступ к админке | `nlb_security_is_admin()` | штатный backend October + политики |

Сделано на Stage 10. CSP и `X-Frame-Options` ставит только PHP: в CSP
одноразовый `nonce` на каждый ответ (инлайновый скрипт фильтров подписан
`nonce="{{ nlb_csp_nonce() }}"`), а `X-Frame-Options` зависит от пути — фронт
`DENY`, админка `SAMEORIGIN`, потому что October использует фреймы во виджетах.
Статические `X-Content-Type-Options`, `Referrer-Policy` и `Permissions-Policy`
дублируются в `nginx.conf` (статику PHP не отдаёт), а версия приложения в
FastCGI-блоке скрывается через `fastcgi_hide_header`, чтобы не было дублей.
HSTS ставит nginx в блоке 443: за прокси `isSecure()` не видит
`X-Forwarded-Proto`. Проверка — `node tools/check-headers.mjs`.

## 10. Чего в переносе нет

- `db/tests/stub/bitrix.php` (31 750 байт) — выбрасывается.
- `api/telegram_booking.php` (2 926 байт) — непригоден (секрет-заглушка, `Authorize(1)`,
  чужие коды свойств), пишется заново как `POST /api/bookings`.
- `db/check.php`, `db/lint.php`, `db/run.php` — заменяются PHPUnit и миграциями.
- Любые вызовы `bitrix:*` — запрещены в новом коде.
