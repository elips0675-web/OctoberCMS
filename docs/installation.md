# Установка

Проверено 10.11.2026 на этой машине: October **v4.4.13**, Laravel **12.71.0**,
PHP **8.3.30**, MySQL **8.4.3**, БД `nlb_october`, 44 таблицы.

## 1. Что нужно

| Требование | Факт на машине |
|---|---|
| PHP ≥ 8.2 | `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe` (8.3.30) |
| Composer 2 | `C:\laragon\bin\composer\composer.phar` (2.9.4) |
| MySQL | Laragon, `127.0.0.1:3306`, `root`, пароль пустой |
| `ext-zip` | **включён вручную**: `php.ini:832`, было `;extension=zip` |
| Прочие расширения | pdo_mysql, mysqli, mbstring, openssl, curl, gd, exif, intl, fileinfo, simplexml |
| Git | remote `https://github.com/elips0675-web/OctoberCMS` |

Документация 4.x живёт на **`docs.octobercms.com/4.x/`**, а не на
`octobercms.com/docs/4.x/` — второй адрес отдаёт 404 (проверено 10.11.2026).

PHP не в PATH. `october:install` внутри вызывает `php` дочерним процессом,
поэтому **без PATH установка падает** с `'php' is not recognized`. В начале
сессии:

```powershell
$env:PATH = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;' + $env:PATH
```

## 2. MySQL

Служба Laragon может быть не запущена (`ERROR 2003 (HY000) ... 10061`).
Поднимается напрямую, без GUI:

```powershell
Start-Process 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqld.exe' `
  -ArgumentList '--defaults-file=C:\laragon\bin\mysql\mysql-8.4.3-winx64\my.ini' `
  -WindowStyle Hidden
```

```sql
CREATE DATABASE nlb_october CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## 3. Порядок установки

Каталог `D:\October CMS` **не пустой** (в нём `docs/`, `AGENTS.md`, `.git`),
а `create-project` требует пустой каталог. Поэтому — установка во временный
подкаталог и перенос:

```powershell
$php = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
$composer = 'C:\laragon\bin\composer\composer.phar'
Set-Location 'D:\October CMS'

& $php $composer create-project october/october ._october "4.4.*" --no-interaction --prefer-dist
# перенести всё из ._october в корень, кроме README/CHANGELOG/CONTRIBUTING/.gitignore/.env.example —
# они остаются проектными; затем Remove-Item ._october -Recurse -Force

php artisan october:install --no-interaction
php artisan october:migrate          # второй прогон, если плагин ставился после миграций
php artisan theme:seed demo          # создать Tailor-таблицы темы (иначе 500 на /)
php artisan october:about            # версия
```

`october:install` без PATH падает **после** «Installing October CMS», уже
на шаге `october:migrate`; база при этом остаётся пустой. Лечение — PATH и
повторный `october:migrate`.

`theme:seed demo` нужен потому, что blueprints темы создают Tailor-таблицы
(`xc_<md5>`) только при сидинге. Без него главная отдаёт `500`:
`Base table or view not found: 1146 Table 'nlb_october.xc_…' doesn't exist`.

## 4. `.env`

`create-project` генерирует `.env` и `APP_KEY` сам. Правки под проект:

```
APP_NAME="NLB"
APP_URL=http://localhost:8080
APP_LOCALE=ru
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nlb_october
DB_USERNAME=root
DB_PASSWORD=
QUEUE_CONNECTION=database
MAIL_MAILER=log
ACTIVE_THEME=demo
```

`ACTIVE_THEME=demo` — временно, до появления `themes/nlb` (этап 4). `.env` в git
не попадает; шаблон — `.env.example`.

## 5. Команды, которых нет в 4.x

| Ожидание из 3.x | В 4.4.13 |
|---|---|
| `php artisan october:version` | **нет**; версия — `php artisan october:about` |
| `php artisan test` | **нет**; тесты — `php vendor/phpunit/phpunit/phpunit` (PHPUnit 9.6.38) |
| `php artisan migrate:fresh --seed` | есть, но для October чище `october:fresh` + `theme:seed` |
| `october:install` без PATH | падает: вызывает `php` |

Демо-тема и демо-плагин ставятся скелетом (`themes/demo`, `plugins/october/demo`).
Удаляются командой `october:fresh` (её точное поведение проверяется на этапе 4).

## 6. Проверка после установки

```powershell
php artisan october:about            # v4.4.13, Laravel 12.71.0, PHP 8.3.30
php vendor/phpunit/phpunit/phpunit   # 0 упавших
php artisan serve --host=127.0.0.1 --port=8080
```

Замеры 10.11.2026: `GET /` → `200`, 27 830 байт (демо-тема); `GET /admin` →
`200`, 10 914 байт (страница входа); `php -l` по 28 PHP-файлам вне `vendor/` и
`storage/` → 0 ошибок; PHPUnit → 1 171 тест, 3 896 утверждений, 8 пропущено,
0 упавших, 2:46.

## 7. Типовые сбои

| Симптом | Причина | Что делать |
|---|---|---|
| `'php' is not recognized` при install | PHP нет в PATH | добавить каталог PHP в `PATH` сессии |
| `ERROR 2003 (HY000) ... 10061` | mysqld не запущен | `Start-Process mysqld.exe --defaults-file=…my.ini` |
| `500` на `/`, в логе `xc_…` not found | blueprints темы не засеяны | `php artisan theme:seed demo` |
| `could not find driver` | нет `pdo_mysql` | включить расширение в `php.ini` |
| композер падает на `ext-zip` | `;extension=zip` | раскомментировать, `php -m | findstr zip` |
| 404 на всех страницах | нет rewrite | `php artisan serve` либо vhost на корень проекта |

## 8. Удаление

Каталог — целиком, база — `DROP DATABASE nlb_october`. Источник
`D:\nlbbootstrap-main` не затрагивается.
