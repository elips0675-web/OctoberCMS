<?php namespace Elv\Library\Classes;

use Cms\Classes\Theme;

/**
 * Seo — базовая микроразметка schema.org и robots.txt.
 *
 * Мета-теги страницы (title, description, canonical, OG, Twitter) отдаёт макет
 * (`themes/elv/layouts/default.htm`), здесь — то, что одинаково для всего
 * сайта: организация и сайт с поиском (JSON-LD), а также
 * директивы обхода.
 */
class Seo
{
    /**
     * siteName — название организации из настроек темы.
     */
    public static function siteName(): string
    {
        return Brand::get('name');
    }

    /**
     * contacts — контакты из настроек темы для schema.org.
     */
    public static function contacts(): array
    {
        $socials = array_values(array_filter([
            Brand::get('social_telegram'),
            Brand::get('social_vk'),
            Brand::get('social_youtube'),
        ]));

        return [
            'telephone' => str_replace(' ', '-', Brand::get('phone')),
            'email' => Brand::get('email'),
            'postalCode' => Brand::get('postal_code'),
            'addressCountry' => Brand::get('country'),
            'addressLocality' => Brand::get('city'),
            'streetAddress' => Brand::get('street'),
            'opens' => Brand::get('opens'),
            'closes' => Brand::get('closes'),
            'sameAs' => $socials,
        ];
    }

    /**
     * settings — SEO-настройки темы: запасные, затем default из `theme.yaml`,
     * затем сохранённые настройки.
     *
     * @return array<string, string>
     */
    public static function settings(): array
    {
        if (static::$settings !== null) {
            return static::$settings;
        }

        $values = static::SETTINGS_FALLBACK;

        try {
            $theme = Theme::getActiveTheme();
        }
        catch (\Throwable $ex) {
            return static::$settings = $values;
        }

        if (!$theme) {
            return static::$settings = $values;
        }

        foreach ((array) ($theme->getFormConfig()['fields'] ?? []) as $key => $field) {
            $key = (string) $key;
            $default = $field['default'] ?? null;

            if (array_key_exists($key, $values) && is_scalar($default)) {
                $values[$key] = is_bool($default) ? ($default ? '1' : '0') : (string) $default;
            }
        }

        $data = $theme->getCustomData()->getAttribute('data');

        if (is_string($data)) {
            $data = json_decode($data, true) ?: [];
        }

        foreach ((array) $data as $key => $value) {
            $key = (string) $key;

            if (array_key_exists($key, $values) && is_scalar($value) && trim((string) $value) !== '') {
                $values[$key] = trim((string) $value);
            }
        }

        return static::$settings = $values;
    }

    /**
     * setting — одно значение SEO-настроек.
     */
    public static function setting(string $key, string $default = ''): string
    {
        $values = static::settings();

        return $values[$key] ?? $default;
    }

    /**
     * forgetSettings — сбросить собранные настройки (тесты и смена настроек).
     */
    public static function forgetSettings(): void
    {
        static::$settings = null;
    }

    /**
     * title собирает `<title>` записи по шаблону из настроек темы.
     *
     * Переменные шаблона: `{title}`, `{section}`, `{brand}`, `{separator}`,
     * `{year}`. Пустой `{title}` не оставляет висящего разделителя.
     */
    public static function title(string $title, string $section = ''): string
    {
        $template = trim(static::setting('seo_title_template'));

        if ($template === '') {
            $template = (string) static::SETTINGS_FALLBACK['seo_title_template'];
        }

        $result = static::applyTemplate($template, [
            'title' => trim($title),
            'section' => trim($section),
            'brand' => static::siteName(),
            'separator' => trim(static::setting('seo_separator')),
            'year' => date('Y'),
        ]);

        return $result !== '' ? $result : static::siteName();
    }

    /**
     * description собирает описание записи по шаблону и обрезает по словам.
     */
    public static function description(string $text, string $section = ''): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));

        $template = trim(static::setting('seo_description_template'));

        if ($template !== '') {
            $applied = static::applyTemplate($template, [
                'description' => $text,
                'section' => trim($section),
                'brand' => static::siteName(),
                'separator' => trim(static::setting('seo_separator')),
                'year' => date('Y'),
            ]);

            if ($applied !== '') {
                $text = $applied;
            }
        }

        return static::truncate($text, static::DESCRIPTION_LIMIT);
    }

    /**
     * truncate обрезает текст по границе слова, не длиннее лимита.
     *
     * Поисковик режет сниппет сам, но обрывок «…предоставл» в мета-описании
     * выглядит как ошибка вёрстки, а не как экономия места.
     */
    public static function truncate(string $text, int $limit): string
    {
        $text = trim($text);

        if ($limit < 1 || mb_strlen($text) <= $limit) {
            return $text;
        }

        $cut = mb_substr($text, 0, $limit + 1);
        $space = mb_strrpos($cut, ' ');

        if ($space !== false && $space > (int) ($limit / 2)) {
            $cut = mb_substr($cut, 0, $space);
        }
        else {
            $cut = mb_substr($text, 0, $limit);
        }

        return (string) preg_replace('/[\s.,;:—–-]+$/u', '', $cut);
    }

    /**
     * defaultImage — картинка для соцсетей по умолчанию, если у страницы своей нет.
     */
    public static function defaultImage(): string
    {
        $value = trim(static::setting('seo_default_image'));

        if ($value === '') {
            return '';
        }

        return preg_match('#^https?://#i', $value) ? $value : url($value);
    }

    /**
     * verification — коды подтверждения прав на сайт: meta-имя => значение.
     *
     * @return array<string, string>
     */
    public static function verification(): array
    {
        $map = [
            'google-site-verification' => static::setting('seo_verify_google'),
            'yandex-verification' => static::setting('seo_verify_yandex'),
            'msvalidate.01' => static::setting('seo_verify_bing'),
        ];

        $result = [];

        foreach ($map as $name => $code) {
            $code = trim($code);

            if ($code !== '') {
                $result[$name] = $code;
            }
        }

        return $result;
    }

    /**
     * servicePagesIndexable — открыты ли служебные страницы для поиска.
     */
    public static function servicePagesIndexable(): bool
    {
        $value = strtolower(trim(static::setting('seo_index_service_pages')));

        return in_array($value, ['1', 'true', 'on', 'yes'], true);
    }

    /**
     * applyTemplate подставляет переменные и убирает следы пустых.
     *
     * @param  array<string, string>  $values
     */
    protected static function applyTemplate(string $template, array $values): string
    {
        $result = $template;

        foreach ($values as $key => $value) {
            $result = str_replace('{' . $key . '}', (string) $value, $result);
        }

        $result = (string) preg_replace('/\{[a-z_]+\}/u', '', $result);
        $result = (string) preg_replace('/\s+/u', ' ', $result);

        $separator = trim((string) ($values['separator'] ?? ''));

        if ($separator !== '') {
            $quoted = preg_quote($separator, '/');
            $result = (string) preg_replace('/(?:' . $quoted . '\s*){2,}/u', $separator . ' ', $result);
            $result = (string) preg_replace('/^(?:\s*' . $quoted . '\s*)+/u', '', $result);
            $result = (string) preg_replace('/(?:\s*' . $quoted . ')+$/u', '', $result);
        }

        return trim((string) preg_replace('/\s+/u', ' ', $result));
    }

    /**
     * sectionFromPage — подпись раздела для шаблона по файлу страницы.
     */
    protected static function sectionFromPage($page): string
    {
        if (!is_object($page) || !method_exists($page, 'getBaseFileName')) {
            return '';
        }

        $handle = (string) preg_replace('/-detail$/', '', (string) $page->getBaseFileName());

        return static::SECTIONS[$handle] ?? '';
    }

    /**
     * SECTIONS — подписи разделов для хлебных крошек (`BreadcrumbList`).
     *
     * Тексты совпадают с `<h1>` разделов в `themes/elv/pages/*.htm`; сверку
     * держит `tests/SeoTest.php` (меняется h1 — краснеет тест, а не молча
     * расходится микроразметка). Раздел без подписи крошек не получает.
     */
    public const SECTIONS = [
        'about' => 'О центре',
        'auth' => 'Вход в личный кабинет',
        'books' => 'Сувениры и подарки',
        'collections' => 'Подарочные наборы',
        'contacts' => 'Контакты',
        'events' => 'События',
        'feedback' => 'Обратная связь',
        'news' => 'Новости',
        'personal' => 'Личный кабинет',
        'printing' => 'Каталог печатных услуг',
        'privacy' => 'Политика конфиденциальности',
        'rules' => 'Правила пользования',
        'search' => 'Поиск по сайту',
        'services' => 'Наши услуги',
        'sitemap' => 'Карта сайта',
        'subscribe' => 'Подписка на рассылку',
        'terms' => 'Условия использования',
    ];

    /**
     * SERVICE_PAGES — служебные адреса: по умолчанию закрыты в robots.txt.
     */
    public const SERVICE_PAGES = ['/search/', '/auth/', '/personal/'];

    /**
     * SETTINGS_FALLBACK — SEO-настройки темы, значения по умолчанию.
     *
     * Ключи совпадают с полями `seo_*` в `themes/elv/theme.yaml`; значение
     * берётся оттуда же (default), а сохранённые настройки перекрывают его.
     */
    public const SETTINGS_FALLBACK = [
        'seo_separator' => '—',
        'seo_title_template' => '{title} {separator} {brand}',
        'seo_description_template' => '',
        'seo_default_image' => '',
        'seo_verify_google' => '',
        'seo_verify_yandex' => '',
        'seo_verify_bing' => '',
        'seo_index_service_pages' => '0',
    ];

    /**
     * TITLE_LIMIT, DESCRIPTION_LIMIT — ориентиры длины сниппета в поиске.
     */
    public const TITLE_LIMIT = 60;
    public const DESCRIPTION_LIMIT = 160;

    /**
     * @var array|null SEO-настройки, собранные за текущий запрос
     */
    protected static $settings = null;

    /**
     * @var array|null узел микроразметки текущей записи
     */
    protected static $recordGraph = null;
    /**
     * @var string|null заголовок текущей записи для последней крошки
     */
    protected static $recordTitle = null;

    /**
     * canonical отдаёт адрес текущей страницы с хвостовым слэшем.
     *
     * `url('/x/')` срезает слэш, поэтому адрес собирается из хоста и пути
     * запроса: canonical обязан совпадать с `<loc>` карты сайта побайтово.
     */
    public static function canonical(): string
    {
        $path = '/' . ltrim((string) request()->path(), '/');

        if ($path !== '/') {
            $path .= '/';
        }

        return request()->getSchemeAndHttpHost() . $path;
    }

    /**
     * reset сбрасывает узел записи между запросами в одном процессе (тесты).
     */
    public static function reset(): void
    {
        static::$recordGraph = null;
        static::$recordTitle = null;
        static::$settings = null;
    }

    /**
     * breadcrumbs собирает цепочку «Главная → раздел → страница» для крошек.
     *
     * Видимой навигационной цепочки в разметке нет (1:1 с эталоном), поэтому
     * список строится из адреса запроса: первый сегмент ищется в SECTIONS,
     * последняя крошка берёт заголовок записи (`applyRecord`) или заголовок
     * страницы без суффикса сайта. Неизвестный раздел даёт пустой список:
     * лучше без `BreadcrumbList`, чем с выдуманным названием. Страница ошибки
     * крошек не получает — иначе у несуществующего адреса появился бы раздел
     * и подпись «Страница не найдена».
     *
     * @param  \Cms\Classes\Page|null  $page
     * @return array<int, array{name: string, url: string}>
     */
    public static function breadcrumbs($page = null): array
    {
        if ($page !== null && static::isNotFoundPage($page)) {
            return [];
        }

        $controller = \Cms\Classes\Controller::getController();

        if ($controller !== null && $controller->getStatusCode() === 404) {
            return [];
        }

        $path = trim((string) request()->path(), '/');

        if ($page !== null && !static::pageMatchesPath($page, $path)) {
            return [];
        }

        if ($path === '') {
            return [];
        }

        $segments = array_values(array_filter(explode('/', $path), function ($part) {
            return $part !== '';
        }));

        if ($segments === [] || !isset(static::SECTIONS[$segments[0]])) {
            return [];
        }

        $base = rtrim(url('/'), '/');
        $section = $segments[0];

        $crumbs = [
            ['name' => 'Главная', 'url' => $base . '/'],
            ['name' => static::SECTIONS[$section], 'url' => $base . '/' . $section . '/'],
        ];

        if (count($segments) > 1) {
            $name = static::currentName($page);

            if ($name !== '') {
                $crumbs[] = ['name' => $name, 'url' => static::canonical()];
            }
        }

        return $crumbs;
    }

    /**
     * isNotFoundPage — страница ошибки 404 крошек не получает.
     */
    protected static function isNotFoundPage($page): bool
    {
        if (method_exists($page, 'getBaseFileName') && $page->getBaseFileName() === '404') {
            return true;
        }

        return (string) ($page->url ?? '') === '/404';
    }

    /**
     * pageMatchesPath сверяет шаблон адреса страницы (`/books/:slug`) с путём запроса.
     */
    protected static function pageMatchesPath($page, string $path): bool
    {
        $pattern = trim((string) ($page->url ?? ''), '/');

        if ($pattern === '') {
            return true;
        }

        $pattern = preg_replace('~:[A-Za-z_]+~', '[^/]+', $pattern);

        return (bool) preg_match('~^' . $pattern . '$~u', trim($path, '/'));
    }

    /**
     * currentName — подпись последней крошки детальной страницы.
     */
    protected static function currentName($page = null): string
    {
        if (static::$recordTitle !== null && static::$recordTitle !== '') {
            return static::$recordTitle;
        }

        if ($page === null) {
            return '';
        }

        $seo = trim((string) ($page->seo_title ?? ''));

        if ($seo !== '') {
            return $seo;
        }

        $title = trim((string) ($page->title ?? ''));

        if ($title === '') {
            return '';
        }

        $suffix = ' — ' . static::siteName();

        return str_ends_with($title, $suffix) ? substr($title, 0, -strlen($suffix)) : $title;
    }

    /**
     * breadcrumbNode — узел `BreadcrumbList` из готовой цепочки.
     *
     * @param  array<int, array{name: string, url: string}>  $crumbs
     */
    protected static function breadcrumbNode(array $crumbs): array
    {
        $items = [];

        foreach ($crumbs as $index => $crumb) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ];
        }

        return [
            '@type' => 'BreadcrumbList',
            '@id' => static::canonical() . '#breadcrumb',
            'itemListElement' => $items,
        ];
    }

    /**
     * jsonld отдаёт связку Library + WebSite (+ крошки и запись) для `<script type="application/ld+json">`.
     *
     * @param  \Cms\Classes\Page|null  $page
     */
    public static function jsonld($page = null): string
    {
        $base = rtrim(url('/'), '/');
        $contacts = static::contacts();

        $data = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => Brand::get('schema_type'),
                    '@id' => $base . '/#organization',
                    'name' => static::siteName(),
                    'url' => $base . '/',
                    'logo' => $base . '/themes/elv/assets/icons/icon-512.png',
                    'image' => $base . '/themes/elv/assets/images/hero-1.jpg',
                    'description' => Brand::get('description'),
                    'telephone' => $contacts['telephone'],
                    'email' => $contacts['email'],
                    'address' => [
                        '@type' => 'PostalAddress',
                        'postalCode' => $contacts['postalCode'],
                        'addressCountry' => $contacts['addressCountry'],
                        'addressLocality' => $contacts['addressLocality'],
                        'streetAddress' => $contacts['streetAddress'],
                    ],
                    'openingHoursSpecification' => [
                        [
                            '@type' => 'OpeningHoursSpecification',
                            'dayOfWeek' => [
                                'Monday',
                                'Tuesday',
                                'Wednesday',
                                'Thursday',
                                'Friday',
                                'Saturday',
                                'Sunday',
                            ],
                            'opens' => $contacts['opens'],
                            'closes' => $contacts['closes'],
                        ],
                    ],
                    'sameAs' => $contacts['sameAs'],
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => $base . '/#website',
                    'url' => $base . '/',
                    'name' => static::siteName(),
                    'inLanguage' => 'ru',
                    'publisher' => ['@id' => $base . '/#organization'],
                    'potentialAction' => [
                        '@type' => 'SearchAction',
                        'target' => [
                            '@type' => 'EntryPoint',
                            'urlTemplate' => $base . '/search/?q={search_term_string}',
                        ],
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
            ],
        ];

        if (static::$recordGraph !== null) {
            $data['@graph'][] = static::$recordGraph;
        }

        $crumbs = static::breadcrumbs($page);

        if (count($crumbs) > 1) {
            $data['@graph'][] = static::breadcrumbNode($crumbs);
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return '';
        }

        return str_replace('</', '<\/', $json);
    }

    /**
     * applyRecord проставляет мету и микроразметку детальной страницы.
     *
     * Пустые поля не затирают поведение по умолчанию: заголовок остаётся
     * «Название — {brand}», описание берётся из анонса — ровно так
     * страница выглядела до появления полей. Обрезка до 160 знаков держит
     * description в разумных рамках: из текста записи он не собирается.
     *
     * Третий аргумент — вид schema.org (`article`, `event`, `product`,
     * `service`): узел дописывается в `@graph` рядом с `Library` и `WebSite`.
     * Без него страница отдаёт только общую разметку — так работают подборки,
     * у которых нет ни цены, ни даты, ни автора.
     *
     * Картинка записи уходит в `seo_image` макета (`og:image` и
     * `twitter:image`); без неё остаётся общая hero-картинка сайта.
     *
     * @param  \Cms\Classes\Page  $page
     * @param  \Tailor\Models\EntryRecord  $record
     * @param  string  $schema
     */
    public static function applyRecord($page, $record, string $schema = ''): void
    {
        $section = static::sectionFromPage($page);

        $page->title = static::title((string) $record->title, $section);

        $title = trim((string) ($record->seo_title ?? ''));

        if ($title !== '') {
            $page->seo_title = $title;
        }

        $description = static::description(static::recordDescription($record), $section);

        if ($description !== '') {
            $page->seo_description = $description;
        }

        $image = static::absoluteImage($record->image ?? '');

        if ($image !== '') {
            $page->seo_image = $image;
        }

        static::$recordGraph = static::recordNode($record, $schema);
        static::$recordTitle = trim((string) $record->title);
    }

    /**
     * recordDescription берёт описание из мета-поля, иначе из анонса.
     */
    protected static function recordDescription($record): string
    {
        $description = trim((string) ($record->seo_description ?? ''));

        if ($description === '') {
            $description = trim(strip_tags((string) $record->excerpt));
        }

        return $description;
    }

    /**
     * recordNode собирает узел schema.org по виду записи.
     */
    protected static function recordNode($record, string $schema): ?array
    {
        return match (strtolower(trim($schema))) {
            'article' => static::articleNode($record),
            'event' => static::eventNode($record),
            'product' => static::productNode($record),
            'service' => static::serviceNode($record),
            default => null,
        };
    }

    /**
     * articleNode — новость как `Article`: дата, автор, издатель.
     */
    protected static function articleNode($record): array
    {
        $url = static::canonical();

        return static::withoutEmpty([
            '@type' => 'Article',
            '@id' => $url . '#article',
            'headline' => (string) $record->title,
            'description' => static::recordDescription($record),
            'image' => static::absoluteImage($record->image),
            'datePublished' => static::isoDate($record->published_at),
            'dateModified' => static::isoDate($record->updated_at),
            'author' => [
                '@type' => 'Person',
                'name' => trim((string) $record->author) ?: static::siteName(),
            ],
            'publisher' => ['@id' => static::siteBase() . '/#organization'],
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
            'url' => $url,
        ]);
    }

    /**
     * eventNode — афиша как `Event`: начало, место, формат и цена.
     */
    protected static function eventNode($record): array
    {
        $url = static::canonical();
        $contacts = static::contacts();

        return static::withoutEmpty([
            '@type' => 'Event',
            '@id' => $url . '#event',
            'name' => (string) $record->title,
            'description' => static::recordDescription($record),
            'url' => $url,
            'image' => static::absoluteImage($record->image),
            'startDate' => static::isoDate($record->event_date),
            'eventAttendanceMode' => static::attendanceMode($record->format),
            'eventStatus' => 'https://schema.org/EventScheduled',
            'location' => [
                '@type' => 'Place',
                'name' => trim((string) $record->place) ?: static::siteName(),
                'address' => [
                    '@type' => 'PostalAddress',
                    'postalCode' => $contacts['postalCode'],
                    'addressCountry' => $contacts['addressCountry'],
                    'addressLocality' => $contacts['addressLocality'],
                    'streetAddress' => $contacts['streetAddress'],
                ],
            ],
            'organizer' => ['@id' => static::siteBase() . '/#organization'],
            'offers' => static::offer($url, static::price($record->price)),
        ]);
    }

    /**
     * productNode — сувенир и печать как `Product` с ценой и наличием.
     *
     * `publisher` в blueprints сувениров — упаковка («Картонная коробка»),
     * а не бренд, поэтому `brand` всегда название организации: чужое значение
     * попадало в сниппет как «Бренд: Прозрачный пакет».
     */
    protected static function productNode($record): array
    {
        $url = static::canonical();
        $price = static::price($record->price);
        $availability = static::availability($record->available);

        if ($price === '') {
            $price = static::price($record->price_from);
            $availability = 'https://schema.org/InStock';
        }

        return static::withoutEmpty([
            '@type' => 'Product',
            '@id' => $url . '#product',
            'name' => (string) $record->title,
            'description' => static::recordDescription($record),
            'image' => static::absoluteImage($record->image),
            'sku' => trim((string) $record->isbn) ?: (string) $record->xml_id,
            'brand' => [
                '@type' => 'Brand',
                'name' => static::siteName(),
            ],
            'offers' => static::offer($url, $price, $availability),
        ]);
    }

    /**
     * serviceNode — услуга как `Service` с ценой «от».
     */
    protected static function serviceNode($record): array
    {
        $url = static::canonical();

        return static::withoutEmpty([
            '@type' => 'Service',
            '@id' => $url . '#service',
            'name' => (string) $record->title,
            'description' => static::recordDescription($record),
            'image' => static::absoluteImage($record->image),
            'serviceType' => trim((string) $record->subtitle) ?: (string) $record->title,
            'provider' => ['@id' => static::siteBase() . '/#organization'],
            'areaServed' => static::contacts()['addressLocality'],
            'offers' => static::offer($url, static::price($record->price_from)),
        ]);
    }

    /**
     * offer описывает цену; без цены предложение не выводится целиком.
     */
    protected static function offer(string $url, string $price, string $availability = 'https://schema.org/InStock'): array
    {
        if ($price === '') {
            return [];
        }

        return [
            '@type' => 'Offer',
            'url' => $url,
            'price' => $price,
            'priceCurrency' => 'RUB',
            'availability' => $availability,
        ];
    }

    /**
     * price приводит цену к числу: «Бесплатно» — 0, «1 490 ₽» — 1490.
     *
     * В blueprints цена хранится и числом, и текстом афиши, поэтому разбор
     * один на все виды записей. Пустая строка означает «цены нет».
     */
    protected static function price($value): string
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return '';
        }

        if (preg_match('/бесплат/iu', $raw)) {
            return '0';
        }

        if (!preg_match('/\d[\d\s\x{00A0}]*(?:[.,]\d+)?/u', $raw, $match)) {
            return '';
        }

        return str_replace(',', '.', str_replace([' ', "\u{00A0}"], '', $match[0]));
    }

    /**
     * availability переводит вариант наличия в словарь schema.org.
     */
    protected static function availability($value): string
    {
        return match ((string) $value) {
            'reserved' => 'https://schema.org/PreOrder',
            'unavailable' => 'https://schema.org/OutOfStock',
            default => 'https://schema.org/InStock',
        };
    }

    /**
     * attendanceMode переводит формат события в словарь schema.org.
     */
    protected static function attendanceMode($value): string
    {
        return match ((string) $value) {
            'online' => 'https://schema.org/OnlineEventAttendanceMode',
            'hybrid' => 'https://schema.org/MixedEventAttendanceMode',
            default => 'https://schema.org/OfflineEventAttendanceMode',
        };
    }

    /**
     * isoDate приводит дату записи к ISO 8601, пустую — к пустой строке.
     */
    protected static function isoDate($value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('c');
        }

        $raw = trim((string) $value);

        if ($raw === '') {
            return '';
        }

        $time = strtotime($raw);

        return $time ? date('c', $time) : '';
    }

    /**
     * absoluteImage превращает значение mediafinder в абсолютный адрес картинки.
     */
    protected static function absoluteImage($value): string
    {
        $path = Media::imagePath($value);

        if ($path === '') {
            return '';
        }

        return static::siteBase() . Media::themeUrl($path);
    }

    /**
     * siteBase — корень сайта без хвостового слэша.
     */
    protected static function siteBase(): string
    {
        return rtrim(url('/'), '/');
    }

    /**
     * withoutEmpty убирает пустые значения: пустой ключ в JSON-LD — ошибка.
     */
    protected static function withoutEmpty(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = static::withoutEmpty($value);
            }

            if ($value === '' || $value === null || $value === []) {
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * robots отдаёт robots.txt с абсолютным адресом карты сайта.
     *
     * Файл динамический, а не статический в `public/`: домен появится только
     * на M17, а директива Sitemap с чужим адресом уводит поисковики на чужой
     * сайт. Адрес берётся из запроса — как в источнике (`sitemap.php`).
     */
    public static function robots(string $sitemapUrl): string
    {
        $lines = [
            '# robots.txt — ' . static::siteName(),
            '',
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin/',
        ];

        if (!static::servicePagesIndexable()) {
            foreach (static::SERVICE_PAGES as $path) {
                $lines[] = 'Disallow: ' . $path;
            }
        }

        $lines = array_merge($lines, [
            'Disallow: /bitrix/',
            'Disallow: /db/',
            'Disallow: /tools/',
            'Disallow: /*?section=',
            'Disallow: /*?past=',
            'Disallow: /*?PAGEN',
            '',
            '# Яндекс: параметры фильтров не создают новых страниц',
            'Clean-param: section&past&PAGEN_1 /',
            '',
            'Sitemap: ' . $sitemapUrl,
            '',
        ]);

        return implode("\n", $lines);
    }
}
