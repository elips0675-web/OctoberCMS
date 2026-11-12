# Установка

Проверено 10.11.2026 на этой машине: October **v4.4.13**, Laravel **12.71.0**,
PHP **8.3.30**, MySQL **8.4.3**, БД `nlb_october` — 44 таблицы после установки,
95 после плагина `nlb/library` и `php artisan tailor:migrate`.

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
ACTIVE_THEME=nlb
```

`ACTIVE_THEME=nlb` — рабочая тема (этап 4). Демо-тема и демо-плагин удалены,
`theme:list` показывает только `nlb`. `.env` в git не попадает; шаблон —
`.env.example`.

## 5. Команды, которых нет в 4.x

| Ожидание из 3.x | В 4.4.13 |
|---|---|
| `php artisan october:version` | **нет**; версия — `php artisan october:about` |
| `php artisan test` | **нет**; тесты — `php vendor/phpunit/phpunit/phpunit` (PHPUnit 9.6.38) |
| `php artisan migrate:fresh --seed` | есть, но для October чище `october:fresh` + `theme:seed` |
| `october:install` без PATH | падает: вызывает `php` |

Демо-тема и демо-плагин ставились скелетом (`themes/demo`, `plugins/october/demo`).
На этапе 4 они удалены: каталоги убраны, таблицы `xc_*` демо-блюпринтов и запись
`October.Demo` в `system_plugin_versions` вычищены, `october:fresh` не требовался
(он снёс бы и контент сайта).

## 6. Проверка после установки

```powershell
php artisan october:about            # v4.4.13, Laravel 12.71.0, PHP 8.3.30
php vendor/phpunit/phpunit/phpunit   # 0 упавших
php artisan serve --host=127.0.0.1 --port=8080
```

Замеры 12.11.2026 (тема `nlb`): `GET /` → `200`, 193 527 знаков; `GET /admin` →
`200`; `GET /health` → `200`; `php -l` по `app,config,database,plugins,themes,tests` →
0 ошибок; PHPUnit ядра → 1 171 тест, 3 896 утверждений, 8 пропущено, 0 упавших;
тесты плагина — 193, 724 утверждения, 0 упавших.

Ежедневный запуск — двойной щелчок по `запуск.bat` в корне проекта: он находит
PHP 8.3 (в PATH или в `C:\laragon\bin\php`), проверяет порт 3306 и поднимает
`mysqld` из Laragon, если база не отвечает, запускает `php artisan serve` на
8080, ждёт ответа `/health` (до 40 с) и открывает браузер. Остановка — закрыть
свёрнутые окна «NLB October 8080» и «NLB MySQL». Ключ `/nobrowser` — без
открытия браузера (для скриптов).

Версия плагина **1.1.0** добавляет хелперы темы (`Media`, `Labels`, `Content`,
карта занятых слотов). Версия **1.0.2** добавила Eloquent-таблицы `nlb_requests`,
`nlb_bookings`, `nlb_orders`, `nlb_slots` (с `UNIQUE(service_code, date, time)`),
`nlb_subscribers`; ставится `php artisan october:migrate`. Слоты услуг
генерируются командой `php artisan nlb:slots-generate --days=14` (идемпотентно,
часы 10:00–21:00). Тесты плагина — `php vendor/phpunit/phpunit/phpunit -c phpunit.nlb.xml`.

## 7. Администратор

Создан 10.11.2026 командой `october:admin` (без интерактива):

```powershell
php artisan october:admin `
  --first-name="Администратор" --last-name="Библиотека" `
  --email="admin@nlb.local" --login="admin" `
  --password="<пароль>" --password-confirmation="<пароль>" `
  --default --no-interaction
```

| Параметр | Значение |
|---|---|
| URL | `http://127.0.0.1:8080/admin` |
| Логин | `admin` |
| Email | `admin@nlb.local` |
| Роль | superuser (`--default`) |

**Пароль в git не попадает.** Он записан открытым текстом только в
`.local/admin-credentials.md` — каталог исключён правилом `/.local` в
`.gitignore`. В документации, `README.md` и `CHANGELOG.md` его нет; при
передаче доступа файл передаётся отдельно.

Проверено не «на слово», а двумя способами:

- `Hash::check($password, $user->password)` → `true`, неверный пароль → `false`,
  `is_superuser` → `true`, `is_activated` → `true`, в `backend_users` одна запись;
- реальный HTTP-вход: `POST /admin/backend/auth/signin` с `_token` из
  `csrf-token` → редирект на `/admin/dashboard`, `200`, 144 155 байт, в ответе
  нет формы входа. До входа на `/admin` форма присутствует (12 628 байт).

Смена пароля:

```powershell
php artisan october:passwd admin
```

Восстановление забытого пароля — та же команда, старый пароль не нужен.

## 8. Типовые сбои

| Симптом | Причина | Что делать |
|---|---|---|
| `'php' is not recognized` при install | PHP нет в PATH | добавить каталог PHP в `PATH` сессии |
| `ERROR 2003 (HY000) ... 10061` | mysqld не запущен | `Start-Process mysqld.exe --defaults-file=…my.ini` |
| `500` на `/`, в логе `xc_…` not found | blueprints темы не засеяны | `php artisan theme:seed demo` |
| `could not find driver` | нет `pdo_mysql` | включить расширение в `php.ini` |
| композер падает на `ext-zip` | `;extension=zip` | раскомментировать, `php -m | findstr zip` |
| 404 на всех страницах | нет rewrite | `php artisan serve` либо vhost на корень проекта |

## 9. Мониторинг

`GET /health` — единственная точка, по которой видно, жив ли сайт
(`classes/Health.php`, `classes/Http/HealthController.php`):

- `200` — `{"status":"ok","checks":{"db":true,"cache":true,"queue":true}}`;
- `503` — тот же JSON со `status: "fail"` и `false` у отказавшего компонента;
  текст исключения уходит в лог, в ответе только имя компонента;
- `Cache-Control: no-store`, сессия не создаётся — частые пробы не плодят
  файлы сессий.

Внешняя проба (UptimeRobot, Healthchecks, Zabbix) настраивается на этот адрес:
интервал 60 с, тревога при `503` или таймауте. Проба живёт вне репозитория —
её включает владелец вместе с доменом (M17, P1-21).

Часовой отчёт пишет команда `php artisan nlb:health-report`: JSON-строку с
временем, ответом БД в миллисекундах, размером очереди, свободным местом на
диске и состоянием компонентов. По умолчанию — `storage/logs/health.log`,
другой файл задаётся `--path`. Строку ставит планировщик
(`Plugin::registerSchedule()`, `hourly()`), поэтому на сервере нужен cron:

```cron
* * * * * cd /path/to/site && php artisan schedule:run >> /dev/null 2>&1
```

`health.log` растёт по строке в час — его включает общий logrotate или
`storage/logs` чистится вместе с остальными логами. ПДн и секретов в строке нет:
только числа и «да/нет».

## 10. Удаление

Каталог — целиком, база — `DROP DATABASE nlb_october`. Источник
`D:\nlbbootstrap-main` не затрагивается.
