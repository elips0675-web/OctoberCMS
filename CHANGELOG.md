# CHANGELOG

История версий сайта NLB на October CMS. Проект ведётся с 10.11.2026; до этого
сайт существовал как шаблон `nlb_bootstrap` на 1С-Битрикс (источник
`D:\nlbbootstrap-main`, его версии — в его собственном `CHANGELOG.md`).

Формат — [Keep a Changelog](https://keepachangelog.com/ru/1.1.0/),
версии — [SemVer](https://semver.org/lang/ru/).

## [Unreleased]

### Планируется

- Этап 2: плагин `nlb/library`, Tailor-blueprints, импорт 91 элемента и 12 разделов.
- Этап 3: заявки, слоты, `UNIQUE(service_code, date, time)` + тест гонки.
- Этап 4: тема `nlb`, 110 URL, удаление демо-темы и демо-плагина.

## [0.1.0] — 10.11.2026

Платформа установлена и проверена. Своего кода ещё нет: работает демо-тема.

### Добавлено

- October CMS **4.4.13** (Laravel **12.71.0**) на PHP **8.3.30**, MySQL 8.4.3,
  база `nlb_october`, 44 таблицы.
- `composer.json` + `composer.lock` — версия зафиксирована.
- `.env.example` (проектные значения: `APP_NAME=NLB`, `DB_DATABASE=nlb_october`,
  `QUEUE_CONNECTION=database`, `MAIL_MAILER=log`) и `.gitignore`, объединяющий
  правила October и проекта.
- Документация переписана под 4.x: реальные команды (`october:about`,
  `vendor/phpunit/phpunit/phpunit`), три грабли установки (PATH, `theme:seed`,
  `ext-zip`), требования хостинга.
- Каркас документации: `AGENTS.md`, `project-context.md`, `README.md`,
  `Что сделано.txt`, `Что доделать.txt` (41 открытый пункт), `docs/` — 9 документов.

### Изменено

- Ветка October: 3.x LTS → **4.x**. Причина: у 3.x только security-фиксы и
  Laravel 9, у 4.x активная поддержка и Laravel 12; документация 4.x живёт на
  `docs.octobercms.com/4.x/`, а не на `octobercms.com/docs/4.x/` (там 404).
- Числа промта заменены замерами: `header.php` 269 строк (не 251),
  `footer.php` 323 (не 305), правил чата 11 (не 10).

### Решено (владелец, 10.11.2026)

- Почта — `MAIL_MAILER=log`; реальный SMTP подключается позже без правок кода.
- Публикация — push в `https://github.com/elips0675-web/OctoberCMS`, ветка `main`.
- PHP — 8.3.30 локально (Laravel 12 официально тестируется на 8.2–8.4).

### Проверено

- PHPUnit 9.6.38: 1 171 тест, 3 896 утверждений, 8 пропущено, 0 упавших, 2:46.
- `GET /` → 200 (27 830 байт, демо-тема), `GET /admin` → 200 (10 914 байт).
- `php -l` по 28 PHP-файлам вне `vendor/` и `storage/` — 0 ошибок.

## [0.0.0] — 10.11.2026

Точка отсчёта: каталог `D:\October CMS` пуст, сайт работает только в источнике
(Битрикс и статическое превью).
