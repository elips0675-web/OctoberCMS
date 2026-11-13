# AGENTS.md — правила работы с сайтом NLB на October CMS

Короткий вход для агента. Полная карта — `docs/README.md`, состояние — `Что сделано.txt`,
открытые пункты — `Что доделать.txt`. Не пересказывай этот файл в ответе.

## 1. Что это за проект

Сайт библиотеки (шаблон `nlb_bootstrap`) переносится с **1С-Битрикс** на **October CMS
(Laravel + Tailor)**. Источник — `D:\nlbbootstrap-main` (только чтение). Цель — этот каталог.
Сайт не «демо»: 110 страниц, 9 инфоблоков, 74 свойства, 91 элемент, три формы с записью
в БД и письмами, свой поиск (BM25), PWA, Lighthouse 100/100/100/100 на мобильном профиле.

Переезд нужен не ради PageSpeed (100 уже есть), а ради транзакций, уникальных индексов,
очередей, планировщика, REST API и тестов без самодельной заглушки ядра.

### Границы

| Путь | Режим |
|---|---|
| `D:\nlbbootstrap-main` | **только чтение**: эталон для сверки, ни одной правки и ни одного `git add` |
| `D:\October CMS` | рабочая зона (этот каталог) |
| `D:\bitrix doker` | **не заходить**: живой стенд Битрикса из другой сессии |

Имя каталога содержит пробел — все пути в кавычках.

### Обвязка машины

- PHP **не в PATH**. Использовать `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`
  (8.5 ветку October может не поддерживать).
- Composer: `C:\laragon\bin\composer\composer.phar`.
- MySQL 8.4.3 Laragon, `127.0.0.1:3306`, пользователь `root`, пароль пустой, БД `nlb_october`.
- Файлы — UTF-8 без BOM. Кириллица в выводе PowerShell ломается: проверять через `node -e`,
  `php -r` или записывать результат в файл и читать его.

## 2. Что читать

1. Карточку задачи — первый незакрытый пункт `Что доделать.txt`.
2. `project-context.md` — стек, решения, структура, точки интеграции.
3. Дальше только профильный документ:

| Задача | Документ |
|---|---|
| перенос сущностей, blueprints, модели, импорт | `docs/architecture.md`, `docs/db.md` |
| шапка, подвал, меню, крошки, формы, поиск, чат | `docs/october-integration.md` |
| установка, версия, `.env`, artisan | `docs/installation.md` |
| CSS, картинки, заголовки, Lighthouse | `docs/security-performance.md` |
| приёмка перед закрытием этапа | `docs/qa-checklist.md` |
| токены, типографика, компоненты | `docs/design-system.md` |
| какие страницы и какой контент | `docs/content.md` |
| внешняя оценка: принято/отложено/отклонено | `docs/roadmap.md` |

Не читать целиком: `preview/**` источника, `styles.css` (81 179 байт), `script.js`
(75 359 байт), `Что доделать.txt` источника (58 КБ). Открывать диапазоном строк.

## 3. Бюджет запроса

- ≤ 3 файла и ≤ 60 КБ входа на один шаг.
- Один шаг = один вопрос. Не пересказывать прочитанное и не дублировать код в ответе.
- Если ответ есть в `project-context.md` — не искать заново.

## 4. Главные правила

1. **Факт — только `file:line`** открытого и проверенного файла. «Готово» без проверки
   и без строки кода — не готово.
2. **Разметка и тексты 1:1.** 110 адресов отвечают 200 и совпадают по структуре с `preview/`.
   URL, тексты, классы, порядок блоков, число слайдов hero не меняются.
3. **Tailor — только контент**: `news`, `events`, `collections`, `books`, `services`,
   `printing`. Заявки — обычные Eloquent-модели + миграции (`requests`, `bookings`, `orders`):
   транзакции, `UNIQUE`, политики, статусные переходы и тесты — это код, а не YAML.
4. **Bootstrap 5.3.3 остаётся.** Урезанный CSS собирается `tools/purge-css.mjs`, встраивается
   в layout **инлайном** и минифицируется хелпером плагина с кешем по `mtime`. Ни одного
   внешнего CSS-запроса, никаких Google Fonts, Tailwind, React, Vite.
5. **Один плагин** `nlb/library` (vendor `nlb`). Никаких демо-плагинов, тем и
   RainLab.Blog. Сторонний плагин — только из списка `docs/plugins.md` (не больше
   трёх на проект), с проверкой строки «Platform Compatibility» 4.x, даты
   обновления и `composer audit` после установки.
6. **Экранирование.** Пользовательский текст в Blade — `{{ }}`; сырой HTML — только через
   `{!! !!}` и только для собственного, проверенного фрагмента (спрайт, инлайн-CSS).
7. **Доступность не ломать.** У иконки-кнопки есть `aria-label`, текущий пункт —
   `aria-current="page"`, модалки — `aria-labelledby`.
8. **Демо-заглушки помечать.** Контакты, реквизиты, соцсети, фото — заглушки, каждая
   обязана быть пунктом в `Что доделать.txt`.
9. **Секреты — только `.env`** (в git не попадает), в репозитории — `.env.example`.
10. **Источник не ломать.** Статическая сборка и Vercel-деплой `D:\nlbbootstrap-main`
    продолжают работать, пока October-сайт не сдан.

## 5. Гейты

Перед закрытием каждого этапа — exit 0 у всех:

```powershell
$php = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
$env:PATH = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;' + $env:PATH
& $php vendor/phpunit/phpunit/phpunit -c phpunit.nlb.xml  # тесты плагина: 231 тест, 0 упавших
& $php vendor/phpunit/phpunit/phpunit             # ядро October: 1171 тест, 0 упавших (artisan test в 4.x нет)
& $php artisan october:migrate           # схема поднимается, 0 ошибок
Get-ChildItem -Path app,config,database,plugins,themes,tests -Recurse -Filter *.php -File |
  ForEach-Object { & $php -l $_.FullName }  # 0 ошибок синтаксиса
node tools/purge-css.mjs --check          # CSS на месте после правок разметки
node tools/check-css-budget.mjs           # бюджет CSS
node tools/check-urls.mjs                 # 110 адресов на живом сервере (нужен serve)
node tools/check-dom-parity.mjs           # DOM и текст против preview/ источника
node tools/check-seo.mjs                  # robots.txt, sitemap.xml, 301, JSON-LD (нужен serve)
node tools/check-headers.mjs              # CSP, X-Frame-Options, nonce, админка без CSP (нужен serve)
node tools/check-pwa.mjs                  # manifest, sw.js с версией и SHELL, офлайн-страница (нужен serve)
node tools/check-health.mjs               # /health 200, no-store, без cookie и внутренних деталей (нужен serve)
node tools/check-orphans.mjs              # ни одной страницы без входящей ссылки (нужен serve)
node tools/check-api.mjs                  # контракт /api/v1 и страницы всех записей (нужен serve)
node tools/check-clean-tree.mjs           # дерево чистое, черновики владельца не в индексе
```

`check-urls`, `check-dom-parity`, `check-seo`, `check-headers`, `check-pwa`, `check-health`,
`check-orphans` и `check-api` работают по
запущенному `php artisan serve` (`--base=http://127.0.0.1:8080`); второй требует
эталон `D:\nlbbootstrap-main\preview` (`--source=`), без него падает — сверять не с
чем. Менять тексты можно только вместе с эталоном, иначе гейт красный и это
правильный сигнал.

Проверка версии — `php artisan october:about` (`october:version` в 4.x нет).

Бюджет: встроенный CSS после минификации ≤ 56,4 КБ (как в источнике), документ главной
≤ 190 412 знаков, initial JS — без роста относительно источника. Превышение — не «готово».

Lighthouse: Chrome + профиль PSI (simulated, mobile), **три прогона подряд**, все
100/100/100/100, замер через сервер с gzip. Перед замером прогреть страницу — иначе первый
прогон даёт ложные 88. «Ускорять» за счёт удаления слайдов, дизайна или контента запрещено.

Гейт не заменяет живую установку: PHPUnit на моке БД не доказывает, что колонка
существует. Схема проверяется `october:migrate` на чистой БД и живым запросом.

## 6. Конец этапа

Этап не закрыт, пока не сделаны пять шагов:

1. запись сверху `Что сделано.txt`: что сделано, где (`file:line`), чем проверено,
   **что не проверено**;
2. `Что доделать.txt`: пункт закрыт (удалён целиком) или уточнён; новый факт — новый пункт;
3. гейты из раздела 5 — exit 0, с числами (сколько тестов, страниц, какие замеры);
4. `git add` поимённо, `git commit` осмысленным сообщением;
5. `git push origin main` — если каталог связан с удалённым репозиторием.

«Сделано и проверено вот этим» или «не сделано, потому что…». Пересказ собственной оценки
без открытого файла — не факт.

## 7. Секреты

Не коммитить и не вставлять в документацию: `.env`, ключи VAPID/Stripe/SMTP, пароли,
токены, доступы к админке, дампы БД, персональные данные читателей. Контакты и реквизиты
в шаблонах — демонстрационные заглушки.

Единственное место для пароля админки — `.local/admin-credentials.md` (каталог
`/.local` в `.gitignore`). Логин и email писать в документации можно, пароль — нет:
в `docs/installation.md` §7 и `README.md` есть ссылка на файл, а не значение.
Проверять утечку — `git check-ignore -v .local/admin-credentials.md` и
`git log -p -- .local` (должно быть пусто).

Черновики владельца (`Архитектура*.html`, `Продажа шаблона*.txt`, `Оценка*.html`,
`Оценка*.txt`, `Что взять из WPShop*.html` в корне) не
коммитятся: в них чужие оценки и внутренние заметки. Они перечислены в
`.gitignore`, а `tools/check-clean-tree.mjs` падает, если такой файл всё же
попал в индекс.

## 8. Стиль ответа

- По-русски, коротко, по делу.
- Ссылки на файлы — от корня этого каталога, например `plugins/nlb/library/Plugin.php:18`.
- «Не найдено» — только со списком проверенных файлов.
- Не предлагать «переписать на React/Vue/Tailwind»: стек зафиксирован.

## 9. Карта кода

Чтобы не читать весь репозиторий: бюджет запроса (§3) — ≤ 3 файла и ≤ 60 КБ
на шаг. Ищите точку входа по таблице, а не «по всему проекту».

| Задача | Файл |
|---|---|
| выборка записей Tailor для темы | `plugins/nlb/library/classes/Content.php` |
| мета, canonical, JSON-LD, robots | `plugins/nlb/library/classes/Seo.php` |
| `sitemap.xml` | `plugins/nlb/library/classes/Sitemap.php` |
| картинки, `<picture>`, инлайн-CSS | `plugins/nlb/library/classes/Media.php` |
| формы (заявка, бронь, заказ, подписка) | `classes/Http/*Controller.php`, `classes/FormFields.php` |
| бронь и гонка слота | `classes/BookingService.php`, `models/Slot.php` |
| Eloquent-модели заявок | `plugins/nlb/library/models/*.php` |
| маршруты и регистрация middleware | `plugins/nlb/library/routes.php`, `Plugin.php` |
| контент Tailor (поля, blueprints) | `plugins/nlb/library/blueprints/*/item.yaml` |
| шапка, подвал, меню, карточки | `themes/nlb/partials/` |
| страницы 110 адресов | `themes/nlb/pages/*.htm` |
| `<head>`, инлайн CSS, JSON-LD в разметке | `themes/nlb/layouts/default.htm` |
| тесты плагина | `plugins/nlb/library/tests/` |
| гейты | `tools/*.mjs` |
| схема и индексы | `docs/db.md` |
| инвентарь адресов и контента | `docs/content.md` |

Запреты (negative prompts) не дублируются здесь: они в разделе 4, правила 4–7.
Новое правило пишется в один документ, а не в третий (`docs/README.md`, §2).
