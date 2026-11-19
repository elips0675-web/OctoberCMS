import { execFileSync } from "node:child_process";
import { readFileSync, writeFileSync, statSync, mkdirSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const outDir = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(outDir, "../..");
mkdirSync(outDir, { recursive: true });

const commit = execFileSync("git", ["rev-parse", "--short", "HEAD"], { cwd: root, encoding: "utf8" }).trim();
const date = execFileSync("git", ["log", "-1", "--format=%ad", "--date=format:%d.%m.%Y"], { cwd: root, encoding: "utf8" }).trim();

const read = (rel) => readFileSync(path.join(root, rel), "utf8").replace(/\r\n/g, "\n");
const block = (rel) => `\n===== FILE: ${rel} =====\n\n${read(rel).replace(/\s+$/, "")}\n`;

function slice(name, title, files) {
  const body = files.map(block).join("");
  const text = `# ${title}\n\nСрез: ${date}, коммит \`${commit}\`.\n\nПути — от корня репозитория; содержимое файлов не переписано.\n${body}`;
  writeFileSync(path.join(outDir, name), text, "utf8");
  return { name, bytes: Buffer.byteLength(text, "utf8") };
}

const context = `# October CMS: шаблон \`elv\` и плагин \`Elv\\Site\` — срез для AI-анализа

Дата среза: ${date}. Коммит: \`${commit}\`.
Репозиторий: https://github.com/elips0675-web/OctoberCMS

## Что это за проект

\`D:\\October CMS\` — универсальный шаблон сайта на **October CMS 4.4.13**
(Laravel 12.71.0, PHP 8.3.30, MySQL 8.4): тема \`themes/elv\` (\`code: elv\`,
\`version.yaml\` 1.0.0) и плагин \`plugins/elv/site\` (namespace \`Elv\\Site\`,
версия 2.2.0). Продукт готовится к продаже как «тема + плагин»: покупатель
ставит на свой October и получает контентные разделы, формы, брони, SEO, PWA и
админку.

Демо-наполнение — библиотека (книги, печать, коллекции, события), это
**демо-разделы**, а не суть продукта: бренд и контакты вынесены в настройки темы
(\`themes/elv/theme.yaml\`, поля \`brand_*\`), тексты берутся через Twig-функцию
\`elv_brand()\` (\`plugins/elv/site/classes/Brand.php\`). Каталога \`lang/\` нет ни в
теме, ни в плагине: подписи бэкенда — русские строки прямо в YAML.

Второй продукт набора — витрина \`D:\\Elv Landing\` (тема \`elv-studio\` + плагин
\`Elv.Kit\`), отдельный репозиторий: общих файлов с этим кодом нет. Стенд
\`D:\\Elv test\` — для проверки установки и апгрейда.

## Что анализировать в этом срезе

1. **Брони и слоты** — \`classes/BookingService.php\`, \`models/Slot.php\`,
   \`models/Booking.php\`, \`classes/Http/BookingController.php\`,
   \`updates/create_elv_slots_table.php\`, \`console/SlotsGenerate.php\`.
2. **Формы и валидация** — \`classes/Http/FormController.php\` (общая база),
   \`FeedbackController.php\`, \`OrderController.php\`, \`SubscriberController.php\`,
   \`classes/FormFields.php\`, \`classes/Http/RequestPath.php\`.
3. **Почта** — \`classes/Mail/*.php\` и шаблоны \`views/mail/*.htm\`: ставится ли
   письмо в очередь, что при недоступном SMTP, нет ли PII в логах.
4. **SEO и микроразметка** — \`classes/Seo.php\`, \`classes/SeoAnalyzer.php\`,
   \`classes/Sitemap.php\`, \`classes/Rss.php\`, \`classes/Http/SeoController.php\`.
5. **Периметр** — \`classes/Http/SecurityHeaders.php\` (CSP с nonce на каждый
   ответ), \`classes/Http/CompressResponse.php\`, редиректы
   \`LegacyRedirect.php\` и \`ManagedRedirect.php\`, \`nginx.conf\`.
6. **PWA** — \`classes/Pwa.php\`, \`classes/Http/PwaController.php\`,
   \`themes/elv/assets/pwa/sw.js\`, \`manifest.webmanifest\`, \`offline.html\`.
7. **Контент и конструктор страниц** — \`classes/Content.php\`,
   \`classes/PageBuilder.php\`, \`blueprints/*\`, страницы темы \`pages/builder.htm\`,
   \`pages/blog.htm\`, \`pages/blog-detail.htm\`.
8. **Медиа и бюджет** — \`classes/Media.php\` (\`<picture>\` с avif/webp/jpg,
   инлайн CSS), \`tools/check-css-budget.mjs\`.
9. **Админка и права** — \`Plugin.php\` (пять прав \`elv.site.*\`, меню «Контент»),
   \`controllers/*.php\`, \`formwidgets/SeoPanel.php\`.
10. **Гейты** — \`tools/check-*.mjs\`: что каждый реально доказывает, а что
    остаётся на словах.

## Маршруты (что реально отдаётся)

Из \`plugins/elv/site/routes.php\`:

- \`GET /health\` — health-check (\`HealthController\`), без cookie и внутренних
  деталей, \`no-store\`.
- \`GET /api/v1/{handle}\` и \`GET /api/v1/{handle}/{slug}\` — публичный JSON
  контента, \`throttle:60,1\`.
- \`POST /feedback\`, \`/booking\`, \`/order\`, \`/subscribe\` — формы,
  \`throttle:30,1\`; ответ JSON при \`Accept: application/json\` (201/422/409),
  иначе редирект назад с \`?sent=1#якорь\` или ошибками.
- \`GET /subscribe/confirm/{token}\`, \`GET /unsubscribe/{token}\` — двойное
  подтверждение подписки.
- \`GET /sitemap.xml\`, \`/rss.xml\`, \`/blog/rss.xml\`, \`/robots.txt\`.
- \`GET /manifest.webmanifest\`, \`/sw.js\`, \`/offline.html\`.

Middleware подключается в \`Plugin::boot()\` в порядке:
\`CompressResponse\` → \`LegacyRedirect\` → \`SecurityHeaders\` → \`ManagedRedirect\`
(\`Plugin.php:105-126\`).

## Текущее поведение (факты)

- **Страницы.** 28 \`.htm\` в \`themes/elv/pages\` + записи Tailor/Eloquent дают
  **110 адресов**, все отвечают 200 (\`tools/check-urls.mjs\`); \`/non-existent/\` —
  404 со страницей. Главная — 188 662 знака, инлайн \`styles.css\` — 57 795 байт.
- **Контент.** 9 каталогов blueprints (\`books\`, \`collections\`, \`events\`, \`news\`,
  \`posts\`, \`printing\`, \`services\`, \`page\`, \`blocks\`), 13 YAML. Записи читаются
  Twig-функциями \`elv_records*\`, \`elv_record\`, \`elv_sections\`
  (\`classes/Content.php\`). Заявки, брони, заказы, слоты, подписчики и редиректы —
  Eloquent-модели \`models/*.php\`.
- **Брони.** Гонка слота закрыта двумя механизмами сразу: \`UNIQUE\`
  \`(service_code, date, time)\` (\`updates/create_elv_slots_table.php:30\`) и
  транзакция с \`lockForUpdate\` + условный \`UPDATE … WHERE taken < capacity\`
  (\`classes/BookingService.php\`). Нет слота — \`409 SLOT_UNAVAILABLE\`.
- **Формы.** Общая база \`FormController\`: согласие на обработку ПД (\`CONSENT\`),
  нормализация полей Bitrix (\`FormFields::normalize\`), правила и тексты ошибок в
  каждом контроллере. Возврат — только на свой хост (Referer проверяется), иначе
  \`fallbackPath()\`. Капчи и honeypot нет; ограничение — \`throttle:30,1\`.
- **Подписка.** Двойное подтверждение ссылкой (\`confirm/{token}\`), письмо
  ставится в очередь (\`QUEUE_CONNECTION=database\`, воркер запускается отдельно);
  отписка — \`unsubscribe/{token}\`.
- **SEO.** \`classes/Seo.php\`: canonical, meta, \`og:*\`, JSON-LD \`@graph\` (узел
  организации, \`WebSite\` + \`SearchAction\`, узел записи \`Article\`/\`Event\`/
  \`Product\`/\`Service\`); \`Sitemap.php\` — 107 адресов; \`robots.txt\` с
  \`Clean-param\`; \`/rss.xml\` — 10 записей. Панель SEO в записи —
  \`classes/SeoAnalyzer.php\` + \`formwidgets/SeoPanel.php\`.
- **API.** 6 разделов, 91 запись (\`tools/check-api.mjs\`): контракт полей,
  страницы записей 200, ошибки — 404/422.
- **Периметр.** CSP с уникальным nonce на каждый ответ, \`X-Frame-Options\`
  зависит от пути (фронт \`DENY\`, админка \`SAMEORIGIN\`); заголовки ставятся и
  middleware, и \`nginx.conf\` (в \`location\` с \`add_header\` набор повторяется,
  иначе nginx обнуляет верхнеуровневые).
- **PWA.** \`manifest.webmanifest\` (4 иконки, 3 shortcut), \`sw.js\` версии
  \`7a9ac0cb\`, офлайн-страница, регистрация на главной.
- **Редиректы.** Сначала карта старых адресов 1С-Битрикс (\`LegacyRedirect\`),
  затем правила редактора (\`ManagedRedirect\`): 301/302/307/308, перенос
  параметров, кэш 600 с, инвалидация на \`saved\`/\`deleted\`, счётчик переходов.
- **Конструктор страниц.** Blueprint \`blocks\` + \`PageBuilder.php\` собирают
  страницу из блоков (\`partials/blocks/*\`: текст, текст+картинка, цитата, список,
  оглавление, видео, записи); инструкция — \`docs/theme-universality.md\`.
- **Медиа.** \`Media::picture()\` отдаёт \`<picture>\` с avif/webp/jpg и размерами,
  \`Media::inlineCss()\` вкладывает CSS в HTML, поэтому внешнего CSS нет.

## Чем это проверено (на ${date})

- PHPUnit: **305 тестов, 1086 проверок** (\`phpunit.elv.xml\`, 21 файл в
  \`plugins/elv/site/tests\`), реальная БД.
- \`php -l\` по \`app, config, database, plugins, themes, tests\` — 109 файлов, 0 ошибок.
- Живые гейты на \`artisan serve:8080\`: \`check-urls\` (110×200),
  \`check-seo\`, \`check-headers\`, \`check-pwa\`, \`check-health\`,
  \`check-orphans\` (106 адресов, у каждого входящая ссылка),
  \`check-api\`, \`check-package\`, \`check-clean-tree\`.
- Есть, но в срез не вложены: \`check-css-budget.mjs\`, \`check-dom-parity.mjs\`,
  \`check-admin-brand.mjs\`, \`purge-css.mjs\`, \`import-preview-pages.mjs\`.
- Что гейты **не** доказывают: Lighthouse-оценки и Core Web Vitals не снимались;
  нагрузка (k6, много одновременных админов) не проверялась; ограничение частоты
  опирается на файловый кэш (\`CACHE_STORE=file\`) и для нескольких серверов не
  годится; почта проверялась без реального SMTP.

## Ограничения и чего здесь нет

- Онлайн-оплаты нет (ни Stripe, ни ЮKassa); заказы и брони — заявка + письмо.
- Выдачи лицензионных ключей и счётчика установок нет; команда сборки ZIP
  (\`elv:site:package\`) и страница установки — в бэклоге, не в коде.
- Маркетплейс October и установка плагинов из админки не настроены (нет ключа).
- Один язык — русский; \`lang/\` отсутствует, подписи в YAML.
- Демо-контент (книги/печать/коллекции) остаётся в поставке — это решение
  владельца, а не мусор.
- \`.env\`, ключи, дампы БД, \`vendor/\` и ядро \`modules/\` в срез не входят.

## Вопросы аудитору

1. Брони: остаётся ли гонка при \`capacity > 1\` и при отмене брони
   (\`BookingService::cancel\`) — сходится ли \`taken\` с числом активных броней
   при повторных вызовах и при истёкшем слоте?
2. Формы: достаточно ли \`throttle:30,1\` без капчи/honeypot; что мешает
   залить \`elv_requests\` мусором с одного IP и не роняют ли это лимиты
   остальные формы?
3. Почта: \`Mail::queue\` без воркера — письмо теряется молча; есть ли
   fallback и видно ли это в health?
4. SEO: JSON-LD собирается из настроек — нет ли расхождения между
   \`@type\` организации, \`brand_kind\` и типом записи; корректны ли даты
   (\`datePublished\`/\`dateModified\`) для Tailor-записей?
5. CSP: nonce на каждый ответ — что ломается при кэшировании HTML на периметре
   (nginx/CDN) и нет ли \`unsafe-inline\` в реальных страницах?
6. PWA: версия \`sw.js\` — хэш чего; что будет при обновлении темы и не отдаст ли
   браузер старый офлайн-контент после смены бренда?
7. Медиа: инлайн CSS в HTML — как это ведёт себя при 200 КБ CSS и на медленном
   соединении; есть ли бюджет и он реально проверяется?
8. Права: пять ключей \`elv.site.*\` — не открывает ли \`requests\` доступ к
   персональным данным при выдаче роли «контент-менеджер»?
9. Апгрейд: переименование \`Elv\\Library\` → \`Elv\\Site\` (2.2.0) — достаточно ли
   замены каталога и \`october:migrate\`, что будет с правами групп и со старой
   строкой в \`system_plugin_versions\` (\`docs/installation.md\` §11)?
10. Гейты: какие из них зелёные по построению (проверяют константу, а не код) и
    где нужен отрицательный прогон?

## Формат файлов

Каждый срез — обычный текст: перед содержимым файла строка
\`===== FILE: путь =====\`, пути от корня репозитория. Код не переписан.
`;

writeFileSync(path.join(outDir, "01-context.txt"), context, "utf8");

// 02-structure
const skip = [
  /^vendor\//, /^modules\//, /^storage\//, /^docs\/html\//, /^test\/ai-chat\//,
  /^\.local\//, /^composer\.lock$/, /^package-lock\.json$/, /^\.phpunit\.result\.cache$/,
];
const tracked = execFileSync("git", ["ls-files", "-z"], { cwd: root, maxBuffer: 64 * 1024 * 1024 })
  .toString("utf8").split("\0").filter(Boolean)
  .filter((f) => !skip.some((re) => re.test(f)));

const isBinaryAsset = (f) => /\.(png|jpe?g|webp|avif|ico|gif|woff2?|ttf|eot)$/i.test(f);
const kb = (f) => {
  try { return (statSync(path.join(root, f)).size / 1024).toFixed(1) + " КБ"; } catch { return "?"; }
};

const byDir = new Map();
for (const f of tracked) {
  const dir = path.posix.dirname(f);
  if (!byDir.has(dir)) byDir.set(dir, []);
  byDir.get(dir).push(f);
}

const assetSummary = [];
const structLines = [];
const dirs = [...byDir.keys()].sort((a, b) => {
  if (a === ".") return -1;
  if (b === ".") return 1;
  return a.localeCompare(b);
});

for (const dir of dirs) {
  const files = byDir.get(dir).sort();
  const textFiles = files.filter((f) => !isBinaryAsset(f));
  const assets = files.filter(isBinaryAsset);
  if (textFiles.length === 0 && assets.length > 0) {
    const total = assets.reduce((sum, f) => sum + statSync(path.join(root, f)).size, 0);
    assetSummary.push(`- \`${dir}\` — ${assets.length} файлов, ${(total / 1024 / 1024).toFixed(1)} МБ (изображения/иконки, не развёрнуты)`);
    continue;
  }
  structLines.push(`## ${dir === "." ? "Корень" : dir}`);
  structLines.push("");
  for (const f of textFiles) {
    structLines.push(`- \`${path.posix.basename(f)}\` (${kb(f)})`);
  }
  if (assets.length) {
    const total = assets.reduce((sum, f) => sum + statSync(path.join(root, f)).size, 0);
    structLines.push(`- … ${assets.length} ассетов, ${(total / 1024 / 1024).toFixed(1)} МБ`);
  }
  structLines.push("");
}

const assetSection = assetSummary.length
  ? `## Ассеты (сводно)\n\n${assetSummary.join("\n")}\n`
  : "";

const structure = `# Структура репозитория October CMS (шаблон \`elv\` + плагин \`Elv\\Site\`)

Срез: ${date}, коммит \`${commit}\`.

Отслеживаемые файлы. Не включены: \`vendor/\`, ядро \`modules/\`, \`storage/\`,
\`docs/html/\` (черновики владельца), \`.env\`, дампы БД, \`composer.lock\`.
Изображения сгруппированы — они не текстовые.

${structLines.join("\n")}
${assetSection}`;
writeFileSync(path.join(outDir, "02-structure.txt"), structure, "utf8");

const sizes = [];
sizes.push({ name: "01-context.txt", bytes: Buffer.byteLength(context, "utf8") });
sizes.push({ name: "02-structure.txt", bytes: Buffer.byteLength(structure, "utf8") });

// 03-config
sizes.push(slice("03-config.txt", "Конфигурация: пакеты, тема, плагин, окружение", [
  "composer.json",
  "package.json",
  "themes/elv/composer.json",
  "themes/elv/theme.yaml",
  "themes/elv/version.yaml",
  "plugins/elv/site/composer.json",
  "plugins/elv/site/updates/version.yaml",
  ".env.example",
  "phpunit.elv.xml",
  "nginx.conf",
]));

// 04-plugin-core
sizes.push(slice("04-plugin-core.txt", "Плагин: точка входа, маршруты, периметр, health", [
  "plugins/elv/site/Plugin.php",
  "plugins/elv/site/routes.php",
  "plugins/elv/site/classes/Http/SecurityHeaders.php",
  "plugins/elv/site/classes/Http/CompressResponse.php",
  "plugins/elv/site/classes/Health.php",
  "plugins/elv/site/classes/Http/HealthController.php",
]));

// 05-content
sizes.push(slice("05-content.txt", "Контент: Tailor-записи, конструктор страниц, метки, медиа, блог", [
  "plugins/elv/site/classes/Content.php",
  "plugins/elv/site/classes/PageBuilder.php",
  "plugins/elv/site/classes/Labels.php",
  "plugins/elv/site/classes/FormFields.php",
  "plugins/elv/site/classes/Media.php",
  "plugins/elv/site/classes/Rss.php",
  "plugins/elv/site/blueprints/posts/item.yaml",
  "plugins/elv/site/blueprints/posts/section.yaml",
  "plugins/elv/site/blueprints/page/structure.yaml",
  "themes/elv/pages/blog.htm",
  "themes/elv/pages/blog-detail.htm",
  "themes/elv/pages/builder.htm",
]));

// 06-forms
sizes.push(slice("06-forms.txt", "Формы, брони, заказы, подписка, модели и письма", [
  "plugins/elv/site/classes/BookingService.php",
  "plugins/elv/site/classes/SlotUnavailableException.php",
  "plugins/elv/site/classes/Http/FormController.php",
  "plugins/elv/site/classes/Http/RequestPath.php",
  "plugins/elv/site/classes/Http/FeedbackController.php",
  "plugins/elv/site/classes/Http/BookingController.php",
  "plugins/elv/site/classes/Http/OrderController.php",
  "plugins/elv/site/classes/Http/SubscriberController.php",
  "plugins/elv/site/models/Request.php",
  "plugins/elv/site/models/Booking.php",
  "plugins/elv/site/models/Order.php",
  "plugins/elv/site/models/Slot.php",
  "plugins/elv/site/models/Subscriber.php",
  "plugins/elv/site/classes/Mail/MailSettings.php",
  "plugins/elv/site/classes/Mail/FeedbackNotification.php",
  "plugins/elv/site/classes/Mail/BookingConfirmation.php",
  "plugins/elv/site/classes/Mail/BookingNotification.php",
  "plugins/elv/site/classes/Mail/OrderNotification.php",
  "plugins/elv/site/classes/Mail/SubscriptionConfirmation.php",
  "plugins/elv/site/views/action.htm",
  "plugins/elv/site/views/mail/feedback.htm",
  "plugins/elv/site/views/mail/booking-client.htm",
  "plugins/elv/site/views/mail/booking-staff.htm",
  "plugins/elv/site/views/mail/order-staff.htm",
  "plugins/elv/site/views/mail/subscribe-confirm.htm",
]));

// 07-seo-pwa
sizes.push(slice("07-seo-pwa.txt", "SEO, микроразметка, карта сайта, редиректы и PWA", [
  "plugins/elv/site/classes/Seo.php",
  "plugins/elv/site/classes/SeoAnalyzer.php",
  "plugins/elv/site/classes/Sitemap.php",
  "plugins/elv/site/classes/Pwa.php",
  "plugins/elv/site/classes/Http/SeoController.php",
  "plugins/elv/site/classes/Http/PwaController.php",
  "plugins/elv/site/classes/Http/LegacyRedirect.php",
  "plugins/elv/site/classes/Http/ManagedRedirect.php",
  "plugins/elv/site/models/Redirect.php",
  "themes/elv/layouts/default.htm",
  "themes/elv/assets/pwa/sw.js",
  "themes/elv/assets/pwa/manifest.webmanifest",
  "themes/elv/assets/pwa/offline.html",
]));

// 08-checks
sizes.push(slice("08-checks-docs.txt", "Гейты, статическая демо-копия и архитектура: что проверяется и как устроено", [
  "tools/check-urls.mjs",
  "tools/check-seo.mjs",
  "tools/check-headers.mjs",
  "tools/check-pwa.mjs",
  "tools/check-health.mjs",
  "tools/check-orphans.mjs",
  "tools/check-api.mjs",
  "tools/check-package.mjs",
  "tools/check-static-demo.mjs",
  "tools/export-static.php",
  "vercel.json",
  "docs/architecture.md",
]));

// ALL-IN-ONE
const sliceNames = sizes.map((s) => s.name);
const parts = [];
for (const name of sliceNames) {
  parts.push("==============================================================================");
  parts.push(name);
  parts.push("==============================================================================");
  parts.push("");
  parts.push(readFileSync(path.join(outDir, name), "utf8").replace(/\s+$/, ""));
  parts.push("");
}
const all = `# October CMS — ALL-IN-ONE для AI-анализа

Дата: ${date}. Коммит: \`${commit}\`. Репозиторий: https://github.com/elips0675-web/OctoberCMS

Внутри: контекст, структура, конфигурация, ядро плагина и периметр, контент и конструктор страниц, формы/брони/подписка, SEO и PWA, гейты и архитектура. Файлы разделены заголовками, чтобы можно было цитировать.

Содержание:
${sliceNames.map((n) => "- " + n).join("\n")}

${parts.join("\n")}
`;
writeFileSync(path.join(outDir, "ALL-IN-ONE.txt"), all, "utf8");
sizes.push({ name: "ALL-IN-ONE.txt", bytes: Buffer.byteLength(all, "utf8") });

const manifest = `# \`test/ai-chat\` — снимок кода October CMS для AI-анализа

Дата среза: ${date}. Коммит: \`${commit}\`.
Репозиторий: https://github.com/elips0675-web/OctoberCMS

Набор закрывает пробел «модель не читала код»: раньше внешний аудит просил
прислать \`composer.json\`, \`Plugin.php\`, конфигурацию темы и структуру папок.
Здесь они лежат обычными \`.txt\`, которые можно загрузить в чат. Срез сделан по
образцу \`D:\\Elv Landing\\test\\ai-chat\` (витрина).

В папке **11 файлов**: 8 тематических срезов, \`ALL-IN-ONE.txt\`, этот
\`README.md\` и \`build.mjs\` для пересборки.

## Что внутри

| Файл | Что внутри |
|---|---|
| \`01-context.txt\` | что за продукт, что анализировать, маршруты, текущее поведение, чем проверено, ограничения, 10 вопросов аудитору |
| \`02-structure.txt\` | отслеживаемая структура репозитория с размерами (без \`vendor/\`, \`modules/\`, картинок целиком) |
| \`03-config.txt\` | \`composer.json\`, \`package.json\`, \`theme.yaml\`, \`version.yaml\` темы и плагина, \`.env.example\`, \`phpunit.elv.xml\`, \`nginx.conf\` |
| \`04-plugin-core.txt\` | полный \`Plugin.php\`, \`routes.php\`, CSP/заголовки, сжатие, health-check |
| \`05-content.txt\` | \`Content.php\`, \`PageBuilder.php\`, \`Labels.php\`, \`FormFields.php\`, \`Media.php\`, \`Rss.php\`, blueprints блога и \`page\`, страницы блога и конструктора |
| \`06-forms.txt\` | \`BookingService\`, общий \`FormController\`, четыре контроллера форм, шесть моделей, письма и их шаблоны |
| \`07-seo-pwa.txt\` | \`Seo.php\`, \`SeoAnalyzer.php\`, \`Sitemap.php\`, \`Pwa.php\`, контроллеры SEO/PWA, редиректы, layout, \`sw.js\`, манифест, офлайн-страница |
| \`08-checks-docs.txt\` | девять гейтов \`tools/check-*.mjs\`, статический экспорт \`tools/export-static.php\` + \`vercel.json\` для демо на Vercel, \`docs/architecture.md\` |
| \`ALL-IN-ONE.txt\` | всё перечисленное одним файлом |
| \`README.md\` | этот манифест |
| \`build.mjs\` | сборка срезов из рабочего дерева (\`node test/ai-chat/build.mjs\`) |

## Как загружать

1. Чат принимает один файл — загрузить \`ALL-IN-ONE.txt\`.
2. Чат принимает несколько файлов — \`01-context.txt\`, \`02-structure.txt\`,
   \`03-config.txt\`, \`04-plugin-core.txt\`, \`06-forms.txt\`.
3. Маленький контекст — начать с \`01-context.txt\` и \`06-forms.txt\` (брони и
   формы — самая рискованная часть), затем \`04-plugin-core.txt\`.
4. Вопрос про SEO/PWA — добавить \`07-seo-pwa.txt\`; про контент и конструктор —
   \`05-content.txt\`; про доказательства — \`08-checks-docs.txt\`.

Пример запроса про брони:

> Ниже снимок October CMS (${date}). Проверь брони и слоты:
> \`BookingService\`, \`models/Slot.php\`, \`BookingController\`. Найди, где
> \`taken\` может разойтись с числом активных броней, что будет при
> \`capacity > 1\`, при отмене и при истёкшем слоте. Дай находки с \`file:line\`,
> без выдуманных метрик.

Второй пример — про продажу шаблона:

> Проверь, что мешает продавать набор как продукт: упаковка (2.2.0),
> апгрейд с 2.1.x, права \`elv.site.*\`, отсутствие \`lang/\`, демо-контент в
> поставке. Что из этого — дефект кода, а что — решение владельца? Ссылки —
> \`file:line\`.

## Чего здесь нет

- \`.env\`, пароли, ключи, дампы БД.
- \`vendor/\`, ядро \`modules/\` и \`docs/html/\` (черновики владельца).
- Полный бэклог и журнал — в корне: \`Что доделать.txt\`, \`Что сделано.txt\`;
  краткий срез для аудита — \`test/Для анализа.txt\`.

## Пересборка

Срез собирается из рабочего дерева, а не правится руками:

\`\`\`powershell
node test/ai-chat/build.mjs
\`\`\`

Скрипт берёт дату и короткий хеш у HEAD (\`git log -1\`, \`git rev-parse\`),
перечитывает перечисленные файлы и перезаписывает все срезы, \`ALL-IN-ONE.txt\`
и этот \`README.md\`. Состав срезов — массивы путей в \`build.mjs\`: добавили файл
в продукт и хотите его в срезе — допишите путь туда. Скрытые от среза каталоги
(\`vendor/\`, \`modules/\`, \`docs/html/\`, сам \`test/ai-chat\`) перечислены в
переменной \`skip\`.

## Важно

- Это **снимок**, а не живой код: после правок PHP/JS/YAML его нужно пересобрать;
  дата и коммит — в шапке каждого файла.
- Код не переписан: пути и содержимое совпадают с рабочим деревом на момент среза.
- Объём \`ALL-IN-ONE.txt\` — ${(Buffer.byteLength(all, "utf8") / 1024).toFixed(0)} КБ.
`;
writeFileSync(path.join(outDir, "README.md"), manifest, "utf8");
sizes.push({ name: "README.md", bytes: Buffer.byteLength(manifest, "utf8") });

console.log(sizes.map((s) => `${s.name}: ${(s.bytes / 1024).toFixed(1)} КБ`).join("\n"));
console.log("total: " + (sizes.reduce((a, b) => a + b.bytes, 0) / 1024).toFixed(1) + " КБ");
