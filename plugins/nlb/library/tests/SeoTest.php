<?php namespace Nlb\Library\Tests;

use Artisan;
use Cms\Classes\Theme;
use Config;
use Illuminate\Http\Request;
use Nlb\Library\Classes\Content;
use Nlb\Library\Classes\Http\LegacyRedirect;
use Nlb\Library\Classes\Seo;
use Nlb\Library\Classes\Sitemap;
use PluginTestCase;
use Tailor\Models\EntryRecord;

/**
 * SeoTest — карта сайта, robots.txt, 301, JSON-LD и мета записи.
 *
 * Проверяется то, что легко протухает молча: перечень страниц в карте
 * (новая страница темы без строки в `Sitemap::PAGES` выпала бы из индекса),
 * формат XML с `lastmod`, отсутствие закрытых в robots адресов, редиректы,
 * валидность микроразметки (её парсит поисковик, а не человек) и мета
 * детальной страницы из полей записи.
 */
class SeoTest extends PluginTestCase
{
    protected $useTransactions = true;

    protected $autoMigrateTailor = true;

    /**
     * @var string|null previous active theme to restore after the test
     */
    protected $previousTheme;

    /**
     * setUp включает реальную тему сайта.
     *
     * `phpunit.nlb.xml` выставляет `ACTIVE_THEME=test` (значение из ядра
     * October), а темы `test` в проекте нет: без активной темы фронтенд
     * отвечает 500 на любую CMS-страницу, и проверять карту сайта, 404 и
     * JSON-LD было бы не на чем.
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->previousTheme = Config::get('cms.active_theme');
        Config::set('cms.active_theme', 'nlb');
        Theme::resetCache();
        Content::flush();
    }

    public function tearDown(): void
    {
        Content::flush();
        Config::set('cms.active_theme', $this->previousTheme);
        Theme::resetCache();

        parent::tearDown();
    }

    protected function themePages(): array
    {
        $dir = base_path('themes/nlb/pages');

        $pages = [];
        foreach (glob($dir . '/*.htm') ?: [] as $file) {
            $pages[] = basename($file, '.htm');
        }

        sort($pages);

        return $pages;
    }

    public function testSitemapCoversEveryStaticPageOfTheme()
    {
        $listed = array_map(function (array $page) {
            return $page['page'];
        }, Sitemap::PAGES);

        $expected = array_values(array_filter($this->themePages(), function (string $page) {
            if ($page === '404') {
                return false;
            }

            if (str_ends_with($page, '-detail')) {
                return false;
            }

            return !in_array('/' . $page . '/', Sitemap::CLOSED, true);
        }));

        sort($listed);

        $this->assertSame($expected, $listed);
    }

    public function testSitemapExcludesClosedPaths()
    {
        foreach (Sitemap::urls() as $url) {
            $this->assertNotContains($url['loc'], Sitemap::CLOSED);
        }
    }

    public function testSitemapXmlIsWellFormedWithLastmod()
    {
        $xml = Sitemap::xml('https://example.by');

        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);
        libxml_use_internal_errors($previous);

        $this->assertNotFalse($document, 'карта сайта не разбирается как XML');

        $urls = $document->url;
        $this->assertSame(count(Sitemap::PAGES), count($urls));

        foreach ($urls as $url) {
            $this->assertStringStartsWith('https://example.by/', (string) $url->loc);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (string) $url->lastmod);
        }
    }

    public function testSitemapLocStartsWithHostWithoutDoubledSlash()
    {
        $xml = Sitemap::xml('https://example.by/');

        $this->assertStringNotContainsString('example.by//', $xml);
        $this->assertStringContainsString('<loc>https://example.by/</loc>', $xml);
    }

    public function testRobotsPointsToGivenSitemap()
    {
        $text = Seo::robots('https://example.by/sitemap.xml');

        $this->assertStringContainsString('Sitemap: https://example.by/sitemap.xml', $text);
        $this->assertStringContainsString('Disallow: /admin/', $text);
        $this->assertStringContainsString('Disallow: /search/', $text);
        $this->assertStringContainsString('Clean-param:', $text);
    }

    public function testRobotsRouteAnswersPlainText()
    {
        $response = $this->get('/robots.txt');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Sitemap: ', $response->getContent());
    }

    public function testSitemapRouteAnswersXml()
    {
        $response = $this->get('/sitemap.xml');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/xml', (string) $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $response->getContent());
    }

    /**
     * @dataProvider legacyUrls
     */
    public function testLegacyUrlsRedirectPermanently(string $from, string $to)
    {
        $response = $this->runLegacyMiddleware($from);

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('http://localhost' . $to, (string) $response->headers->get('Location'));
    }

    public static function legacyUrls(): array
    {
        return [
            'главная' => ['/index.php', '/'],
            'карта сайта' => ['/sitemap.php', '/sitemap.xml'],
            'раздел' => ['/about/index.php', '/about/'],
            'список' => ['/news/index.php', '/news/'],
            'детальная без CODE' => ['/news/detail.php', '/news/'],
        ];
    }

    public function testLegacyDetailRedirectKeepsSlugAndDropsCode()
    {
        $response = $this->runLegacyMiddleware('/news/detail.php?CODE=cifrovoy-chitalnyy-zal&utm_source=test');

        $this->assertSame(301, $response->getStatusCode());

        $location = (string) $response->headers->get('Location');

        $this->assertSame('http://localhost/news/cifrovoy-chitalnyy-zal/?utm_source=test', $location);
        $this->assertStringNotContainsString('CODE=', $location);
    }

    public function testLegacyRedirectSkipsNonReadRequests()
    {
        $response = $this->runLegacyMiddleware('/index.php', 'POST');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('passthrough', $response->getContent());
    }

    /**
     * runLegacyMiddleware прогоняет запрос через LegacyRedirect без HTTP-стека.
     *
     * Плагин регистрирует middleware в `boot()` только вне консоли
     * (`Plugin.php:89`), а PHPUnit — это консоль, поэтому через `$this->get()`
     * до `LegacyRedirect` дело не доходит. Здесь проверяется сам маппинг
     * старых адресов, включая разбор сырого `REQUEST_URI`; живую связку
     * «запрос → 301» проверяет `tools/check-seo.mjs` на работающем сервере.
     */
    protected function runLegacyMiddleware(string $url, string $method = 'GET')
    {
        $middleware = new LegacyRedirect;

        return $middleware->handle(Request::create($url, $method), function () {
            return response('passthrough', 200);
        });
    }

    public function testUnknownLegacyPathIsNotRedirected()
    {
        $response = $this->get('/unknown/page.php');

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testJsonLdIsValidAndContainsLibraryAndWebsite()
    {
        $json = Seo::jsonld();

        $this->assertStringNotContainsString('</', $json, 'закрывающий тег порвал бы <script>');

        $data = json_decode($json, true);

        $this->assertIsArray($data, 'JSON-LD не разбирается');
        $this->assertSame('https://schema.org', $data['@context']);

        $types = array_map(function (array $node) {
            return $node['@type'];
        }, $data['@graph']);

        $this->assertContains('Library', $types);
        $this->assertContains('WebSite', $types);

        $library = $data['@graph'][0];
        $this->assertSame('+375-17-300-00-00', $library['telephone']);
        $this->assertSame('пр. Независимости, 116', $library['address']['streetAddress']);
        $this->assertNotEmpty($library['openingHoursSpecification']);

        $website = $data['@graph'][1];
        $this->assertSame('SearchAction', $website['potentialAction']['@type']);
        $this->assertStringContainsString('/search/?q={search_term_string}', $website['potentialAction']['target']['urlTemplate']);
    }

    public function testLayoutRendersJsonLd()
    {
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('<script type="application/ld+json">', $html);
        $this->assertStringContainsString('"@type":"Library"', $html);
    }

    /**
     * @dataProvider detailPages
     */
    public function testEveryDetailPageBuildsTitleFromRecord(string $url, string $name)
    {
        $this->importFixture();

        $html = $this->get($url)->getContent();

        $this->assertStringContainsString('<title>' . $name . ' — Библиотека 2026</title>', $html);
        $this->assertStringContainsString(
            '<link rel="canonical" href="http://localhost:8080' . $url . '">',
            $html
        );
    }

    public static function detailPages(): array
    {
        return [
            'новость' => ['/news/cifrovoy-chitalnyy-zal/', 'Цифровой читальный зал'],
            'событие' => ['/events/vecher-poezii/', 'Вечер поэзии'],
            'сувенир' => ['/books/krzhka-biblioteka/', 'Кружка «Библиотека»'],
            'услуга' => ['/services/obzornaya-ploshchadka/', 'Обзорная площадка'],
            'печать' => ['/printing/pechat-a4/', 'Печать A4'],
            'подборка' => ['/collections/nabor-chitatelya/', 'Набор читателя'],
        ];
    }

    public function testRecordPageUsesExcerptWhenSeoFieldsAreEmpty()
    {
        $this->importFixture();

        $html = $this->get('/news/cifrovoy-chitalnyy-zal/')->getContent();

        $this->assertStringContainsString('<title>Цифровой читальный зал — Библиотека 2026</title>', $html);
        $this->assertStringContainsString(
            '<meta name="description" content="Анонс первой новости.">',
            $html
        );
    }

    public function testRecordSeoFieldsOverridePageTitleAndDescription()
    {
        $this->importFixture();

        $news = EntryRecord::inSection('Nlb\News')
            ->where('xml_id', 'nlb-news-001')
            ->first();

        $this->assertNotNull($news);

        $news->seo_title = 'Читальный зал: доступ к цифровым фондам';
        $news->seo_description = 'Как записаться в цифровой читальный зал и что для этого нужно.';
        $news->save();

        Content::flush();

        $html = $this->get('/news/cifrovoy-chitalnyy-zal/')->getContent();

        $this->assertStringContainsString(
            '<title>Читальный зал: доступ к цифровым фондам</title>',
            $html
        );
        $this->assertStringContainsString(
            '<meta name="description" content="Как записаться в цифровой читальный зал и что для этого нужно.">',
            $html
        );
        $this->assertStringContainsString(
            '<link rel="canonical" href="http://localhost:8080/news/cifrovoy-chitalnyy-zal/">',
            $html
        );
        $this->assertStringContainsString(
            '<meta property="og:title" content="Читальный зал: доступ к цифровым фондам">',
            $html
        );
    }

    public function testApplyRecordTrimsDescriptionTo160Characters()
    {
        $page = new \stdClass;
        $record = (object) [
            'title' => 'Новость',
            'seo_title' => '  ',
            'seo_description' => '',
            'excerpt' => '<p>' . str_repeat('я', 400) . '</p>',
        ];

        Seo::applyRecord($page, $record);

        $this->assertSame('Новость — Библиотека 2026', $page->title);
        $this->assertFalse(property_exists($page, 'seo_title'));
        $this->assertSame(160, mb_strlen($page->seo_description));
        $this->assertStringNotContainsString('<p>', $page->seo_description);
    }

    public function testApplyRecordKeepsEmptyDescriptionEmpty()
    {
        $page = new \stdClass;
        $record = (object) [
            'title' => 'Услуга',
            'seo_title' => 'Коворкинг в библиотеке',
            'seo_description' => '',
            'excerpt' => '',
        ];

        Seo::applyRecord($page, $record);

        $this->assertSame('Коворкинг в библиотеке', $page->seo_title);
        $this->assertFalse(property_exists($page, 'seo_description'));
    }

    /**
     * importFixture заливает урезанный экспорт источника в тестовую БД.
     */
    protected function importFixture(): void
    {
        $code = Artisan::call('nlb:import', [
            '--source' => dirname(__DIR__) . '/tests/fixtures/db/config/data',
        ]);

        $this->assertSame(0, $code);
        Content::flush();
    }
}
