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

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return '';
        }

        return str_replace('</', '<\/', $json);
    }

    /**
     * applyRecord проставляет мета детальной страницы из записи Tailor.
     *
     * Пустые поля не затирают поведение по умолчанию: заголовок остаётся
     * «Название — Библиотека 2026», описание берётся из анонса — ровно так
     * страница выглядела до появления полей. Обрезка до 160 знаков держит
     * description в разумных рамках: из текста записи он не собирается.
     *
     * @param  \Cms\Classes\Page  $page
     * @param  \Tailor\Models\EntryRecord  $record
     */
    public static function applyRecord($page, $record): void
    {
        $page->title = $record->title . ' — ' . static::SITE_NAME;

        $title = trim((string) $record->seo_title);

        if ($title !== '') {
            $page->seo_title = $title;
        }

        $description = trim((string) $record->seo_description);

        if ($description === '') {
            $description = trim(strip_tags((string) $record->excerpt));
        }

        if ($description !== '') {
            $page->seo_description = mb_substr($description, 0, 160);
        }
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
