# CHANGELOG

История версий сайта NLB на October CMS. Проект ведётся с 10.11.2026; до этого
сайт существовал как шаблон `nlb_bootstrap` на 1С-Битрикс (источник
`D:\nlbbootstrap-main`, его версии — в его собственном `CHANGELOG.md`).

Формат — [Keep a Changelog](https://keepachangelog.com/ru/1.1.0/),
версии — [SemVer](https://semver.org/lang/ru/).

## [Unreleased]

### Добавлено

- Каркас документации проекта: `AGENTS.md`, `project-context.md`, `README.md`,
  `Что сделано.txt`, `Что доделать.txt`, `docs/README.md` и 9 профильных
  документов (`architecture`, `db`, `october-integration`, `installation`,
  `security-performance`, `design-system`, `content`, `qa-checklist`).
- Таблица переноса «9 инфоблоков → blueprints/модели»: 74 свойства, 91 элемент,
  12 разделов — по факту разбора `db/config/iblocks.php` и
  `db/config/data/*.php`.
- Бэклог переезда: 33 открытых пункта, включая наследованные из источника
  (P0-4/P0-5/P0-6/P0-7, P1-4/P1-5/P1-9/P1-11/P1-12/P1-15/P1-16/P1-17/P1-20,
  P2-1…P2-13) и новые (M1–M15).

### Изменено

- Числа промта заменены замерами: `header.php` 269 строк (не 251),
  `footer.php` 323 (не 305), правил чата 11 (не 10).

### Решено (владелец, 10.11.2026)

- Ветка October CMS — **3.x LTS**, ограничение `3.7.*` (последняя 3.7.13).
- Почта — `MAIL_MAILER=log`; реальный SMTP подключается позже без правок кода.
- Публикация — push в `https://github.com/elips0675-web/OctoberCMS`, ветка `main`.

### Не добавлено (и почему)

- Ни одной строки кода October: CMS не установлена, версия ветки не
  зафиксирована (пункт M1), лицензия/аккаунт не оформлялись.
- `composer.lock`, миграции, blueprints, тема — появятся на этапах 1–2.

## [0.0.0] — 10.11.2026

Точка отсчёта: каталог `D:\October CMS` пуст, git не инициализирован, сайт
работает только в источнике (Битрикс и статическое превью).
