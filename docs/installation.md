# Установка

## 1. Что нужно до начала

| Требование | Факт на машине |
|---|---|
| PHP 8.3 (не 8.5) | `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe` |
| Composer 2 | `C:\laragon\bin\composer\composer.phar` |
| MySQL 8.4.3 | Laragon, `127.0.0.1:3306`, `root`, пароль пустой |
| Расширения PHP | pdo_mysql, mbstring, openssl, curl, gd, zip, simplexml, fileinfo |
| Git | есть, remote `https://github.com/elips0675-web/OctoberCMS` |

PHP **не в PATH**: во всех командах ниже используется переменная `$php`.

## 2. Порядок

```powershell
$php = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
$composer = 'C:\laragon\bin\composer\composer.phar'

Set-Location 'D:\October CMS'
& $php $composer create-project october/october . "^<ветка>"
& $php artisan october:install
& $php artisan october:migrate
& $php artisan october:version
```

1. Ветка фиксируется **до** установки по официальной документации именно этой ветки:
   синтаксис Tailor и команды `artisan` между 3.x и 4.x отличаются. Версия записывается
   в `README.md`, `composer.lock` — в git.
2. Если установщик требует ключ/аккаунт — **остановиться и спросить владельца**.
   Лицензии не покупать, аккаунты от его имени не регистрировать.
3. Если установка падает — показать вывод, не обходить костылями.

## 3. База

```sql
CREATE DATABASE nlb_october CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

`.env` (не коммитится):

```
APP_URL=http://localhost:8080
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nlb_october
DB_USERNAME=root
DB_PASSWORD=
QUEUE_CONNECTION=database
MAIL_MAILER=log
```

`.env.example` в репозитории повторяет ключи с пустыми значениями. Секреты VAPID,
Stripe, SMTP, API-токен бота добавляются на этапах 5–7, не раньше.

## 4. Проверка после установки

```powershell
& $php artisan october:version          # версия зафиксирована
& $php artisan test                     # пусто, но зелёно
& $php artisan migrate:fresh --seed     # схема поднимается с нуля
Get-ChildItem -Recurse -Filter *.php -File |
  Where-Object { $_.FullName -notmatch '\\(vendor|storage)\\' } |
  ForEach-Object { & $php -l $_.FullName }
```

Сайт открывается, админка входит, `git status` чистый (кроме сгенерированных
`storage/`, `vendor/`, `.env`).

## 5. Локальный запуск

```powershell
& $php artisan serve --host=127.0.0.1 --port=8080
```

Продакшн-профиль страницы проверяется через сервер с gzip (Apache/Nginx Laragon или
middleware): без gzip замеры Lighthouse занижены, а «100» на `php -S` не воспроизводится.

## 6. Очереди и планировщик

```powershell
& $php artisan queue:work --tries=3      # либо cron: queue:work --once
& $php artisan schedule:run              # каждую минуту, как в cron
```

Без `queue:work` письма и уведомления остаются в таблице `jobs` — «письмо отправлено»
без обработчика не считается проверенным.

## 7. Типовые сбои

| Симптом | Причина | Что делать |
|---|---|---|
| `could not find driver` | PHP без `pdo_mysql` | включить расширение в `php.ini` Laragon |
| 404 на всех страницах | не включён rewrite | включить mod_rewrite / проверить `public/` |
| 500 на `/backend` | нет таблиц | `artisan october:migrate` |
| пустой CSS | не собран purge/minify | `node tools/purge-css.mjs`, проверить `mtime`-кеш |
| письма не уходят | `MAIL_MAILER=log` | смотреть `storage/logs/laravel.log` |

## 8. Удаление

Каталог удаляется целиком; база — `DROP DATABASE nlb_october`. Источник
`D:\nlbbootstrap-main` при этом не затрагивается.
