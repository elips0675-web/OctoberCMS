# Контент и структура сайта

## 1. Инвентарь URL (110 адресов)

Проверено по каталогу `preview/` источника: 110 HTML-файлов. 91 детальная = 91 элементу
в БД — расхождений нет.

| Группа | Список | Детальные | Всего |
|---|---|---|---|
| Книги/сувениры | `/books/` | 24 | 25 |
| Печать | `/printing/` | 37 | 38 |
| Новости | `/news/` | 10 | 11 |
| Услуги | `/services/` | 8 | 9 |
| События | `/events/` | 6 | 7 |
| Подборки | `/collections/` | 6 | 7 |
| Корень | — | 13 страниц | 13 |

### 1.1. Корневые страницы (13)

`/` (главная), `/about/`, `/auth/`, `/contacts/`, `/feedback/`, `/personal/`, `/privacy/`,
`/rules/`, `/search/`, `/sitemap/`, `/subscribe/`, `/terms/`, 404.

### 1.2. Книги и сувениры (24)

`aromatizator-v-mashinu-kniga`, `bloknot-zametki`, `brelok-gerb`, `brelok-kniga`,
`brelok-sova`, `chaynaya-para-minsk`, `derzhatel-telefona-v-mashinu`, `ezhednevnik-plany`,
`farforovaya-statuetka-sova`, `farforovaya-tarelka-biblioteka`, `futbolka-chitayu`,
`hudi-biblioteka`, `kepka-chitayu`, `kruzhka-biblioteka`, `magnit-kniga-luchshiy-podarok`,
`magnit-minsk`, `magnit-ramka-dlya-foto`, `nabor-gelevyh-ruchek`, `nabor-karandashey`,
`nabor-otkrytok-minsk`, `ruchka-biblioteka`, `shopper-biblioteka`, `termokruzhka-chitay`,
`zont-chitalnyy-zal`.

### 1.3. Печать (37)

`beydzhi`, `blanki-i-firmyennye-formy`, `bloknoty-i-ezhednevniki`, `breloki`,
`broshyury-i-buklety`, `chertezhi-i-plakaty`, `flazhki-i-vympely`, `fleshki-s-logo`,
`foto-bolshogo-formata`, `foto-magnity`, `foto-na-akrile`, `fotoknigi`, `fotokollazhi`,
`fotopechat`, `futbolki`, `kalendari`, `konverty-s-logotipom`, `kruzhki`, `magnity`,
`nakleyki-i-stikery`, `otkrytki-i-priglasheniya`, `papki-firmyennye`, `pazly-s-foto`,
`pechat-dokumentov`, `pechat-kvadratnyh-foto`, `pechat-na-holste`, `pechat-na-paketah`,
`pechat-panoramnyh-foto`, `pechati-i-shtampy`, `pereplyot-i-laminirovanie`, `prezentatsii`,
`ruchki-s-logo`, `shirokiy-format`, `skanirovanie`, `tsenniki-i-yarlyki`, `vizitki`,
`znachki-i-piny`.

### 1.4. Новости (10)

`biblionoch-2026-itogi`, `bronirovanie-peregovornyh`, `cifrovoy-chitalnyy-zal`,
`detskaya-masterskaya-nabor`, `kak-zapisatsya-v-biblioteku`, `kovorking-novye-mesta`,
`lektoriy-chitaem-vmeste-dekabr`, `novye-postupleniya-noyabr`,
`podkast-mezhdu-strok-sezon-2`, `redkie-izdaniya-ocifrovany`.

### 1.5. Услуги (8)

`cafe`, `concert-hall`, `coworking`, `gym`, `kids-zone`, `lecture-hall`,
`observation-deck`, `sauna`.

### 1.6. События (6)

`detskiy-klub-sovenok`, `ekskursiya-fond-redkih-knig`, `igroteka-nastolnyh-igr`,
`lekciya-kak-chitat-sovremennuyu-prozu`, `master-klass-neyroseti`,
`poeticheskiy-vecher-osen`.

### 1.7. Подборки (6)

`nabor-chitayu`, `nabor-dlya-detey`, `nabor-dlya-doma`, `nabor-dlya-kolleg`,
`nabor-dlya-puteshestviy`, `nabor-na-pamyat-o-minske`.

## 2. Разделы (12)

| Инфоблок | Разделы |
|---|---|
| `news` | `library` (Новости библиотеки), `announcements` (Анонсы) |
| `events` | `literature`, `children`, `tech`, `lectures` |
| `books` | `fiction`, `history`, `science`, `children`, `business`, `art` |
| `collections`, `services`, `printing` | без разделов |

Названия разделов `books` в источнике — от сувениров («Брелоки», «Посуда и фарфор»,
«Одежда», «Канцелярия», «Магниты и открытки», «Аксессуары»). Переносятся как есть;
исправление — отдельный пункт бэклога (P2-13), не самодеятельность переезда.

## 3. Где живёт контент

| Что | Где |
|---|---|
| Элементы (91) | БД October: Tailor-записи + `elv:*` таблицы (см. `db.md`) |
| Тексты страниц (о библиотеке, правила, политика, условия) | CMS-страницы темы |
| Меню | компонент `Menu` (данные навигации) |
| Контакты, реквизиты, соцсети | конфиг сайта (заглушки, P0-6) |
| Тексты писем | шаблоны `Mail` (ru) |
| Правила чата и промо-подсказки | данные плагина (11 правил, 10 подсказок) |
| Мета/OG | `Seo` + поля записи |

Тексты в Blade не хардкодятся: то, что правит контент-менеджер, живёт в записи.
То, что является разметкой (заголовки секций главной), переносится в CMS-страницу
или partial, но дословно.

## 4. Главная страница

Порядок блоков неизменен (источник — `preview/index.html`, `demo/index.php`):

1. hero-карусель, 3 слайда (`#top`);
2. услуги, 6 карточек (`#services`);
3. печатные услуги, 8 карточек (`#print`);
4. события, 3 карточки + CTA (`#events`);
5. новости, 4 карточки (`#news`);
6. коллекции, 6 карточек (`#collections`);
7. партнёры, 5 логотипов;
8. подвал (`#contacts`).

Число слайдов и карточек не уменьшается «для скорости».

## 5. Изображения

- 20 исходников в `assets/images/`, варианты 480/960/1280 в JPG, WebP, AVIF.
- Есть у 32 из 91 элемента (services 8, events 6, news 10, printing 8).
- Нет у 59: books 24, printing 29, collections 6 — дефект источника (P1-11).
  Заглушка вместо фото — дефект, а не решение.

## 6. Тон и правки текстов

Переезд не «улучшает» тексты. Опечатки и фактические ошибки фиксируются в бэклоге
одним пунктом, правка — по согласованию с владельцем, не по вкусу исполнителя.
