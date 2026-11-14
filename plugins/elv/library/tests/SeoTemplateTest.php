<?php namespace Elv\Library\Tests;

use Cms\Classes\Theme;
use Cms\Models\ThemeData;
use Config;
use DB;
use Elv\Library\Classes\Brand;
use Elv\Library\Classes\Seo;
use PluginTestCase;
use ReflectionProperty;

/**
 * SeoTemplateTest — SEO-настройки темы: шаблоны, коды подтверждения, robots.
 *
 * Проверяется то, что не видно на глаз: пустой `{title}` не оставляет
 * разделитель, неизвестная переменная не попадает в `<title>`, обрезка описания
 * идёт по границе слова, а выключенный флаг служебных страниц возвращает
 * `Disallow` в robots.txt.
 */
class SeoTemplateTest extends PluginTestCase
{
    protected $useTransactions = true;

    /**
     * @var string|null previous active theme to restore after the test
     */
    protected $previousTheme;

    public function setUp(): void
    {
        parent::setUp();

        $this->previousTheme = Config::get('cms.active_theme');
        Config::set('cms.active_theme', 'elv');
        $this->forgetThemeData();
        Brand::forget();
        Seo::reset();
    }

    public function tearDown(): void
    {
        Brand::forget();
        Seo::reset();
        Config::set('cms.active_theme', $this->previousTheme);
        $this->forgetThemeData();

        parent::tearDown();
    }

    /**
     * forgetThemeData сбрасывает кэш настроек темы.
     *
     * ThemeData держит экземпляр и строку в кэше статически: без сброса
     * значения одного теста протекают в следующий, хотя транзакция откатилась.
     */
    protected function forgetThemeData(): void
    {
        $theme = Theme::getActiveTheme();

        if ($theme) {
            $theme->getCustomData()->clearCache($theme->getDirName());
        }

        $instances = new ReflectionProperty(ThemeData::class, 'instances');
        $instances->setAccessible(true);
        $instances->setValue(null, []);

        Theme::resetCache();
    }

    /**
     * setThemeData сохраняет настройки темы так же, как админка.
     *
     * Колонка `data` пишется напрямую: после `flushEventListeners` в тестах
     * модель ThemeData не поднимает `beforeSave`, и динамические поля ушли бы
     * в SQL как несуществующие колонки.
     */
    protected function setThemeData(array $values): void
    {
        $theme = Theme::getActiveTheme();
        $custom = $theme->getCustomData();
        $data = array_merge((array) $custom->getAttribute('data'), $values);

        DB::table('cms_theme_data')->updateOrInsert(
            ['theme' => $theme->getDirName()],
            ['data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]
        );

        $this->forgetThemeData();
        Brand::forget();
        Seo::reset();
    }

    public function testTitleUsesDefaultTemplate()
    {
        $this->assertSame('Новости — ' . Seo::siteName(), Seo::title('Новости', 'Новости'));
    }

    public function testTitleUsesCustomSeparator()
    {
        $this->setThemeData(['seo_separator' => '|']);

        $this->assertSame('Новости | ' . Seo::siteName(), Seo::title('Новости'));
    }

    public function testTitleWithoutTitleFallsBackToSiteName()
    {
        $this->assertSame(Seo::siteName(), Seo::title('   '));
    }

    public function testTitleDropsUnknownPlaceholders()
    {
        $this->setThemeData(['seo_title_template' => '{title} {unknown} {brand}']);

        $this->assertSame('Новости ' . Seo::siteName(), Seo::title('Новости'));
        $this->assertStringNotContainsString('{', Seo::title('Новости'));
    }

    public function testTitleCarriesSectionAndYear()
    {
        $this->setThemeData(['seo_title_template' => '{section}: {title} ({year})']);

        $this->assertSame('Новости: Сегодня (' . date('Y') . ')', Seo::title('Сегодня', 'Новости'));
    }

    public function testTitleStripsSeparatorWhenTitleIsEmpty()
    {
        $this->setThemeData(['seo_title_template' => '{title} {separator} {brand}']);

        $this->assertSame(Seo::siteName(), Seo::title(''));
    }

    public function testDescriptionCollapsesMarkupAndSpaces()
    {
        $this->assertSame(
            'Первое слово второе',
            Seo::description("<p>Первое   слово</p>\n\n второе")
        );
    }

    public function testDescriptionTruncatesAtWordBoundary()
    {
        $text = trim(str_repeat('слово ', 60));
        $result = Seo::description($text);

        $this->assertLessThanOrEqual(Seo::DESCRIPTION_LIMIT, mb_strlen($result));
        $this->assertStringEndsWith('слово', $result);
        $this->assertStringNotContainsString('  ', $result);
    }

    public function testDescriptionTemplateAddsBrand()
    {
        $this->setThemeData(['seo_description_template' => '{description} — {brand}']);

        $this->assertSame('Коворкинг — ' . Seo::siteName(), Seo::description('Коворкинг'));
    }

    public function testTruncateKeepsShortText()
    {
        $this->assertSame('абв', Seo::truncate('абв', 10));
    }

    public function testTruncateCutsLongWordAtLimit()
    {
        $this->assertSame(160, mb_strlen(Seo::truncate(str_repeat('я', 400), 160)));
    }

    public function testVerificationIsEmptyByDefault()
    {
        $this->assertSame([], Seo::verification());
    }

    public function testVerificationMapsProvidersToMetaNames()
    {
        $this->setThemeData([
            'seo_verify_google' => 'g-123',
            'seo_verify_yandex' => 'y-456',
            'seo_verify_bing' => 'b-789',
        ]);

        $this->assertSame([
            'google-site-verification' => 'g-123',
            'yandex-verification' => 'y-456',
            'msvalidate.01' => 'b-789',
        ], Seo::verification());
    }

    public function testServicePagesAreDisallowedByDefault()
    {
        $text = Seo::robots('https://example.by/sitemap.xml');

        $this->assertStringContainsString('Disallow: /search/', $text);
        $this->assertStringContainsString('Disallow: /auth/', $text);
        $this->assertStringContainsString('Disallow: /personal/', $text);
    }

    public function testServicePagesAllowIndexingWhenEnabled()
    {
        $this->setThemeData(['seo_index_service_pages' => '1']);

        $text = Seo::robots('https://example.by/sitemap.xml');

        $this->assertStringNotContainsString('Disallow: /search/', $text);
        $this->assertStringNotContainsString('Disallow: /auth/', $text);
        $this->assertStringNotContainsString('Disallow: /personal/', $text);
        $this->assertStringContainsString('Disallow: /admin/', $text);
    }

    public function testDefaultImageKeepsAbsoluteUrl()
    {
        $this->setThemeData(['seo_default_image' => 'https://cdn.example.com/og.jpg']);

        $this->assertSame('https://cdn.example.com/og.jpg', Seo::defaultImage());
    }

    public function testDefaultImageMakesRelativePathAbsolute()
    {
        $this->setThemeData(['seo_default_image' => 'themes/elv/assets/images/hero-1.jpg']);

        $this->assertSame(url('themes/elv/assets/images/hero-1.jpg'), Seo::defaultImage());
    }

    public function testApplyRecordUsesSectionFromPageFile()
    {
        $page = new class
        {
            public function getBaseFileName()
            {
                return 'news-detail';
            }
        };

        $this->setThemeData(['seo_title_template' => '{title} — {section} — {brand}']);

        Seo::applyRecord($page, (object) [
            'title' => 'Сегодня',
            'seo_title' => '',
            'seo_description' => '',
            'excerpt' => 'Анонс',
        ]);

        $this->assertSame('Сегодня — Новости — ' . Seo::siteName(), $page->title);
    }
}
