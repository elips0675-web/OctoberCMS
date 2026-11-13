<?php namespace Nlb\Library\Classes;

/**
 * Sitemap — карта сайта для поисковых систем (`/sitemap.xml`).
 *
 * Список адресов собирается из того же источника, что и страницы: статические
 * разделы — фиксированный перечень страниц темы (`PAGES`), детальные — записи
 * Tailor через `Content::records()`, то есть ровно те, что тема умеет показать.
 * Поэтому в карту не попадают ни удалённые, ни отключённые записи, ни
 * шаблоны детальных страниц (`*-detail`), которые адресами не являются.
 *
 * Закрытые в robots.txt адреса (`/auth/`, `/personal/`, `/search/`) в карту не
 * кладутся: директива Disallow и запись в sitemap противоречат друг другу,
 * поисковик сообщает об этом как об ошибке.
 */
class Sitemap
{
    /**
     * SECTIONS — префикс адреса => handle Tailor-записей.
     */
    public const SECTIONS = [
        'books' => 'Nlb\Book',
        'printing' => 'Nlb\Printing',
        'news' => 'Nlb\News',
        'services' => 'Nlb\Service',
        'events' => 'Nlb\Event',
        'collections' => 'Nlb\Collection',
    ];

    /**
     * PAGES — статические страницы темы: адрес, файл, вес и частота обновления.
     *
     * Перечень сверяется с `themes/nlb/pages/*.htm` тестом
     * `tests/SeoTest.php`: новая страница без строки здесь роняет тест, а не
     * молча выпадает из индекса.
     */
    public const PAGES = [
        '/' => ['page' => 'index', 'priority' => '1.0', 'changefreq' => 'daily'],
        '/books/' => ['page' => 'books', 'priority' => '0.8', 'changefreq' => 'weekly'],
        '/printing/' => ['page' => 'printing', 'priority' => '0.8', 'changefreq' => 'weekly'],
        '/news/' => ['page' => 'news', 'priority' => '0.8', 'changefreq' => 'weekly'],
        '/services/' => ['page' => 'services', 'priority' => '0.8', 'changefreq' => 'weekly'],
        '/events/' => ['page' => 'events', 'priority' => '0.8', 'changefreq' => 'weekly'],
        '/collections/' => ['page' => 'collections', 'priority' => '0.7', 'changefreq' => 'weekly'],
        '/contacts/' => ['page' => 'contacts', 'priority' => '0.7', 'changefreq' => 'monthly'],
        '/about/' => ['page' => 'about', 'priority' => '0.6', 'changefreq' => 'monthly'],
        '/feedback/' => ['page' => 'feedback', 'priority' => '0.5', 'changefreq' => 'monthly'],
        '/subscribe/' => ['page' => 'subscribe', 'priority' => '0.4', 'changefreq' => 'monthly'],
        '/rules/' => ['page' => 'rules', 'priority' => '0.3', 'changefreq' => 'monthly'],
        '/privacy/' => ['page' => 'privacy', 'priority' => '0.3', 'changefreq' => 'monthly'],
        '/terms/' => ['page' => 'terms', 'priority' => '0.3', 'changefreq' => 'monthly'],
        '/sitemap/' => ['page' => 'sitemap', 'priority' => '0.3', 'changefreq' => 'monthly'],
    ];

    /**
     * CLOSED — адреса, закрытые в robots.txt: в карту не попадают.
     */
    public const CLOSED = ['/auth/', '/personal/', '/search/'];

    /**
     * DETAIL_PRIORITY — вес детальных страниц.
     */
    public const DETAIL_PRIORITY = '0.6';

    /**
     * urls отдаёт адреса с датой изменения: `[['loc' => '/news/x/', 'lastmod' => 'Y-m-d', ...]]`.
     *
     * @return array<int, array{loc: string, lastmod: string, changefreq: string, priority: string}>
     */
    public static function urls(): array
    {
        $urls = [];

        foreach (static::PAGES as $loc => $config) {
            $urls[] = [
                'loc' => (string) $loc,
                'lastmod' => static::pageLastmod($config['page'], $loc),
                'changefreq' => $config['changefreq'],
                'priority' => $config['priority'],
            ];
        }

        foreach (static::SECTIONS as $prefix => $handle) {
            foreach (Content::records($handle) as $record) {
                $slug = trim((string) $record->slug);
                if ($slug === '') {
                    continue;
                }

                $urls[] = [
                    'loc' => '/' . $prefix . '/' . $slug . '/',
                    'lastmod' => static::recordLastmod($record),
                    'changefreq' => 'weekly',
                    'priority' => static::DETAIL_PRIORITY,
                ];
            }
        }

        foreach (PageBuilder::pages() as $page) {
            $urls[] = [
                'loc' => '/' . $page['slug'] . '/',
                'lastmod' => $page['lastmod'],
                'changefreq' => 'monthly',
                'priority' => static::DETAIL_PRIORITY,
            ];
        }

        usort($urls, function (array $left, array $right) {
            return strcmp($left['loc'], $right['loc']);
        });

        return $urls;
    }

    /**
     * xml собирает готовый документ карты сайта.
     */
    public static function xml(string $baseUrl): string
    {
        $base = rtrim($baseUrl, '/');

        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach (static::urls() as $url) {
            $out .= "  <url>\n";
            $out .= '    <loc>' . static::escape($base . $url['loc']) . "</loc>\n";
            $out .= '    <lastmod>' . static::escape($url['lastmod']) . "</lastmod>\n";
            $out .= '    <changefreq>' . $url['changefreq'] . "</changefreq>\n";
            $out .= '    <priority>' . $url['priority'] . "</priority>\n";
            $out .= "  </url>\n";
        }

        $out .= '</urlset>' . "\n";

        return $out;
    }

    /**
     * knownPaths отдаёт статические адреса без записей — для проверок и 301.
     *
     * @return array<int, string>
     */
    public static function knownPaths(): array
    {
        return array_keys(static::PAGES);
    }

    /**
     * pageLastmod — дата изменения страницы темы.
     *
     * У списков берётся свежая запись (её правка меняет содержимое страницы),
     * у текстовых страниц — время правки файла страницы: другого источника
     * изменения у них нет.
     */
    protected static function pageLastmod(string $page, string $loc): string
    {
        $latest = '';

        foreach (static::SECTIONS as $prefix => $handle) {
            if ($loc === '/' . $prefix . '/' || $loc === '/') {
                $candidate = static::sectionLastmod($handle);
                if ($candidate > $latest) {
                    $latest = $candidate;
                }
            }
        }

        if ($latest !== '') {
            return $latest;
        }

        $file = static::themePath('pages/' . $page . '.htm');

        if ($file !== null && is_file($file)) {
            return date('Y-m-d', (int) filemtime($file));
        }

        return date('Y-m-d');
    }

    /**
     * sectionLastmod — дата самой свежей записи раздела.
     */
    protected static function sectionLastmod(string $handle): string
    {
        $latest = '';

        foreach (Content::records($handle) as $record) {
            $candidate = static::recordLastmod($record);
            if ($candidate > $latest) {
                $latest = $candidate;
            }
        }

        return $latest;
    }

    /**
     * recordLastmod — дата изменения записи.
     */
    protected static function recordLastmod($record): string
    {
        $updated = $record->updated_at ?: $record->created_at;

        return $updated ? $updated->format('Y-m-d') : date('Y-m-d');
    }

    /**
     * themePath — путь внутри активной темы.
     */
    protected static function themePath(string $relative): ?string
    {
        $theme = \Cms\Classes\Theme::getActiveTheme();

        return $theme ? rtrim($theme->getPath(), '/\\') . '/' . ltrim($relative, '/') : null;
    }

    /**
     * escape — экранирование для XML.
     */
    protected static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
