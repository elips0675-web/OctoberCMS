# NLB on October CMS

Сайт библиотеки «Библиотека 2026» (Минск), перенесённый с **1С-Битрикс** на
**October CMS (Laravel + Tailor)**.

Источник переезда — `D:\nlbbootstrap-main` (только чтение): шаблон `nlb_bootstrap`,
110 статических страниц, 9 инфоблоков, 74 свойства, 91 элемент, BM25-поиск, чат, PWA.

## Статус

| Этап | Состояние |
|---|---|
| 0. Разведка | **сделана**: числа сверены, карта переноса собрана |
| 1. Установка October | **сделана 10.11.2026**: v4.4.13, Laravel 12.71.0, БД `nlb_october`, 1 171 тест зелёный |
| 2. Плагин `nlb/library` и импорт контента | не начат |
| 3. Заявки, слоты, гонка слота (P1-20) | не начат |
| 4. Вёрстка и 110 URL | не начата |
| 5. Формы | не начат |
| 6. Поиск | не начат |
| 7. API и слоты | не начат |
| 8. Производительность (Lighthouse 100) | не начата |
| 9. Документация и бэклог | ведётся постоянно |

Версия October: **4.4.13** (Packagist, релиз 01.11.2026), Laravel **12.71.0**,
PHP **8.3.30**. Ветка 4.x выбрана владельцем 10.11.2026 вместо 3.x LTS:
у 3.x поддержка только security-фиксы и Laravel 9, у 4.x — активная поддержка
и Laravel 12. Версия зафиксирована в `composer.lock`.
Хостинг ещё не выбран — пункт M1 в `Что доделать.txt`.

## Быстрый старт

```powershell
$php = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
$env:PATH = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;' + $env:PATH
Set-Location 'D:\October CMS'
php artisan october:migrate
php artisan nlb:import --source="D:/nlbbootstrap-main/db/config/data"   # этап 2
php artisan nlb:search-index                                           # этап 6
php artisan serve --host=127.0.0.1 --port=8080
```

Подробно — `docs/installation.md` (там же три грабли 4.x: PATH, `theme:seed`,
`october:about` вместо `october:version`).

## Админка

`http://127.0.0.1:8080/admin`, логин `admin`, email `admin@nlb.local`
(создан 10.11.2026, superuser). **Пароль в репозитории не хранится** — он
записан в `.local/admin-credentials.md`, каталог исключён в `.gitignore`.
Сброс: `php artisan october:passwd admin`. Подробности — `docs/installation.md` §7.

## Команды

| Команда | Что делает |
|---|---|
| `php vendor/phpunit/phpunit/phpunit` | PHPUnit на реальной БД (`artisan test` в 4.x нет) |
| `php artisan october:about` | версия October, Laravel, PHP (`october:version` нет) |
| `php artisan october:fresh` | убирает демо-тему и демо-плагин |
| `php artisan migrate:fresh` | схема с нуля |
| `php artisan nlb:import` | идемпотентный импорт 91 элемента и 12 разделов |
| `php artisan nlb:search-index` | переиндексация BM25 |
| `php artisan nlb:slots-generate` | генерация слотов услуг |
| `node tools/purge-css.mjs` | урезание `bootstrap.min.css` |
| `node tools/check-css-budget.mjs` | бюджет встроенного CSS |
| `php tools/build-image-variants.php` | WebP/AVIF, 480/960/1280 |
| `php tools/gen-vapid.php` | ключи Web Push |

## Документация

| Файл | Роль |
|---|---|
| `AGENTS.md` | правила работы агента: границы, гейты, конец этапа |
| `project-context.md` | стек, решения, структура, точки интеграции |
| `docs/README.md` | карта документации |
| `docs/architecture.md` | плагин, Tailor vs Eloquent, таблица переноса 9 инфоблоков |
| `docs/db.md` | схема, поля, перечисления, `UNIQUE` слотов, импорт |
| `docs/october-integration.md` | шапка, подвал, меню, формы, поиск, чат, push |
| `docs/installation.md` | установка, `.env`, artisan, сбои |
| `docs/security-performance.md` | CSP, бюджеты, картинки, протокол Lighthouse |
| `docs/design-system.md` | токены и запреты на изменение дизайна |
| `docs/content.md` | инвентарь 110 URL, разделы, где живёт контент |
| `docs/qa-checklist.md` | приёмка этапов |
| `Что сделать.txt` → `Что сделано.txt` | журнал: что, где, чем проверено, что не проверено |
| `Что доделать.txt` | только открытые пункты |

## Ключевые ограничения

- URL, тексты, разметка, классы — 1:1 с `preview/`. 110 адресов обязаны отвечать 200.
- Bootstrap 5.3.3, инлайн CSS ≤ 56,4 КБ, ноль внешних CSS-запросов, системные шрифты.
- Lighthouse 100/100/100/100 на мобильном профиле, три прогона подряд через gzip.
- Один плагин `nlb/library`; никаких демо-плагинов, RainLab.Blog, Tailwind, React, Vite.
- Секреты — только `.env`; в репозитории `.env.example`.
- Источник не меняется, пока переезд не сдан.

## Лицензия

October CMS — self-host. Ключ/аккаунт оформляет владелец; исполнитель не покупает
и не регистрирует ничего от его имени.
