<?php namespace Nlb\Library\Classes;

/**
 * Seo — базовая микроразметка schema.org и robots.txt.
 *
 * Мета-теги страницы (title, description, canonical, OG, Twitter) отдаёт макет
 * (`themes/nlb/layouts/default.htm`), здесь — то, что одинаково для всего
 * сайта: организация «Библиотека» и сайт с поиском (JSON-LD), а также
 * директивы обхода.
 */
class Seo
{
    /**
     * SITE_NAME — название сайта в микроразметке и og:site_name макета.
     */
    public const SITE_NAME = 'Библиотека 2026';

    /**
     * CONTACTS — демо-контакты из подвала.
     *
     * Дублируют `themes/nlb/partials/footer.htm` и `pages/contacts.htm`
     * (в источнике было так же: `nlb_seo.php` и `footer.php`). При смене
     * реальных контактов (P0-6) правьте все три места сразу.
     */
    public const CONTACTS = [
        'telephone' => '+375-17-300-00-00',
        'email' => 'info@example.by',
        'postalCode' => '220114',
        'addressCountry' => 'BY',
        'addressLocality' => 'Минск',
        'streetAddress' => 'пр. Независимости, 116',
        'opens' => '10:00',
        'closes' => '22:00',
        'sameAs' => [
            'https://t.me/example',
            'https://vk.com/example',
            'https://youtube.com/@example',
        ],
    ];

    /**
     * @var array|null узел микроразметки текущей записи
     */
    protected static $recordGraph = null;

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
    }

    /**
     * jsonld отдаёт связку Library + WebSite для `<script type="application/ld+json">`.
     */
    public static function jsonld(): string
    {
        $base = rtrim(url('/'), '/');
        $contacts = static::CONTACTS;

        $data = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Library',
                    '@id' => $base . '/#library',
                    'name' => static::SITE_NAME,
                    'url' => $base . '/',
                    'logo' => $base . '/themes/nlb/assets/icons/icon-512.png',
                    'image' => $base . '/themes/nlb/assets/images/hero-1.jpg',
                    'description' => 'Библиотека 2026 — книги, события, услуги и пространства для работы и учёбы.',
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
                    'name' => static::SITE_NAME,
                    'inLanguage' => 'ru',
                    'publisher' => ['@id' => $base . '/#library'],
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
     * «Название — Библиотека 2026», описание берётся из анонса — ровно так
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
        $page->title = $record->title . ' — ' . static::SITE_NAME;

        $title = trim((string) ($record->seo_title ?? ''));

        if ($title !== '') {
            $page->seo_title = $title;
        }

        $description = static::recordDescription($record);

        if ($description !== '') {
            $page->seo_description = mb_substr($description, 0, 160);
        }

        $image = static::absoluteImage($record->image ?? '');

        if ($image !== '') {
            $page->seo_image = $image;
        }

        static::$recordGraph = static::recordNode($record, $schema);
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
                'name' => trim((string) $record->author) ?: static::SITE_NAME,
            ],
            'publisher' => ['@id' => static::siteBase() . '/#library'],
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
        $contacts = static::CONTACTS;

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
                'name' => trim((string) $record->place) ?: static::SITE_NAME,
                'address' => [
                    '@type' => 'PostalAddress',
                    'postalCode' => $contacts['postalCode'],
                    'addressCountry' => $contacts['addressCountry'],
                    'addressLocality' => $contacts['addressLocality'],
                    'streetAddress' => $contacts['streetAddress'],
                ],
            ],
            'organizer' => ['@id' => static::siteBase() . '/#library'],
            'offers' => static::offer($url, static::price($record->price)),
        ]);
    }

    /**
     * productNode — сувенир и печать как `Product` с ценой и наличием.
     *
     * `publisher` в blueprints сувениров — упаковка («Картонная коробка»),
     * а не бренд, поэтому `brand` всегда название библиотеки: чужое значение
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
                'name' => static::SITE_NAME,
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
            'provider' => ['@id' => static::siteBase() . '/#library'],
            'areaServed' => static::CONTACTS['addressLocality'],
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
            '# robots.txt — Библиотека 2026',
            '',
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin/',
            'Disallow: /search/',
            'Disallow: /auth/',
            'Disallow: /personal/',
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
        ];

        return implode("\n", $lines);
    }
}
