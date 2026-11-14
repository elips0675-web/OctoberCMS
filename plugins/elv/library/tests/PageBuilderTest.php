<?php namespace Elv\Library\Tests;

use Artisan;
use Cms\Classes\Theme;
use Config;
use Elv\Library\Classes\Content;
use Elv\Library\Classes\PageBuilder;
use PluginTestCase;
use Tailor\Models\RepeaterItem;
use Tailor\Models\StructureRecord;
use Yaml;

/**
 * PageBuilderTest — страница, собранная из блоков в админке.
 *
 * Проверяется то, что нельзя увидеть на 110 сверенных адресах: блоки новых
 * страниц не с чем сравнивать (эталона для них нет), поэтому контракт держат
 * тесты — группы повторителя совпадают с партиалами, разделы «сетки» с
 * белым списком, отключённая страница не отдаётся, а подзаголовки получают
 * уникальные якоря.
 */
class PageBuilderTest extends PluginTestCase
{
    protected $useTransactions = true;

    protected $autoMigrateTailor = true;

    /**
     * @var string|null previous active theme to restore after the test
     */
    protected $previousTheme;

    public function setUp(): void
    {
        parent::setUp();

        $this->previousTheme = Config::get('cms.active_theme');
        Config::set('cms.active_theme', 'elv');
        Theme::resetCache();

        PageBuilder::flush();
        Content::flush();
    }

    public function tearDown(): void
    {
        Config::set('cms.active_theme', $this->previousTheme);
        Theme::resetCache();

        PageBuilder::flush();
        Content::flush();

        parent::tearDown();
    }

    public function testPageRendersTextBlock(): void
    {
        $page = $this->makePage(['title' => 'Демо-страница', 'slug' => 'demo']);
        $this->addBlock($page, 'text', [
            'title' => 'Первый блок',
            'body' => '<p>Текст первого блока.</p>',
        ]);

        $html = $this->get('/demo/')->getContent();

        $this->assertStringContainsString('<h1 class="page-title">Демо-страница</h1>', $html);
        $this->assertStringContainsString('Первый блок', $html);
        $this->assertStringContainsString('<p>Текст первого блока.</p>', $html);
    }

    public function testUnknownSlugGives404(): void
    {
        $response = $this->get('/net-takoy-stranicy/');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('Страница не найдена', $response->getContent());
    }

    public function testDisabledPageIsHidden(): void
    {
        $page = $this->makePage(['slug' => 'skrytaya', 'is_enabled' => false]);
        $this->addBlock($page, 'text', ['body' => '<p>Скрытый текст.</p>']);

        $response = $this->get('/skrytaya/');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringNotContainsString('Скрытый текст.', $response->getContent());
    }

    public function testHeadingsBecomeAnchorsAndToc(): void
    {
        $page = $this->makePage(['slug' => 'oglavlenie']);
        $this->addBlock($page, 'toc', ['title' => 'Содержание', 'include_h3' => true]);
        $this->addBlock($page, 'text', [
            'title' => 'Раздел',
            'body' => '<h2>Первый</h2><p>Текст.</p><h3>Второй</h3><p>Текст.</p><h2>Первый</h2>',
        ]);

        $html = $this->get('/oglavlenie/')->getContent();

        $this->assertStringContainsString('id="razdel"', $html);
        $this->assertStringContainsString('id="pervyi"', $html);
        $this->assertStringContainsString('id="vtoroi"', $html);
        $this->assertStringContainsString('id="pervyi-2"', $html);

        $this->assertStringContainsString('href="#razdel"', $html);
        $this->assertStringContainsString('href="#vtoroi"', $html);
        $this->assertStringContainsString('href="#pervyi-2"', $html);
    }

    public function testTocSkipsThirdLevelByDefault(): void
    {
        $page = $this->makePage(['slug' => 'bez-tretego']);
        $this->addBlock($page, 'toc', ['title' => 'Содержание']);
        $this->addBlock($page, 'text', ['body' => '<h2>Первый</h2><h3>Второй</h3>']);

        $html = $this->get('/bez-tretego/')->getContent();

        $this->assertStringContainsString('href="#pervyi"', $html);
        $this->assertStringNotContainsString('href="#vtoroi"', $html);
    }

    public function testUnknownGroupIsSkipped(): void
    {
        $page = $this->makePage(['slug' => 'neizvestnaya-gruppa']);
        $this->addBlock($page, 'text', ['body' => '<p>Обычный блок.</p>']);

        $unknown = $page->blocks()->getRelated()->newInstance();
        $unknown->content_group = 'evil';
        $page->blocks()->save($unknown);

        $html = $this->get('/neizvestnaya-gruppa/')->getContent();

        $this->assertStringContainsString('Обычный блок.', $html);
        $this->assertStringNotContainsString('blocks/evil', $html);
    }

    public function testRecordsBlockRendersCards(): void
    {
        $this->importFixture();

        $page = $this->makePage(['slug' => 's-novostyami']);
        $this->addBlock($page, 'records', ['title' => 'Новости', 'source' => 'news', 'limit' => 1]);

        $html = $this->get('/s-novostyami/')->getContent();

        $this->assertStringContainsString('elv-card', $html);
        $this->assertStringContainsString('href="/news/', $html);
        $this->assertStringContainsString('href="/news/"', $html);
    }

    public function testGridClampsLimitAndRejectsUnknownSource(): void
    {
        $this->importFixture();

        $this->assertLessThanOrEqual(PageBuilder::MAX_LIMIT, count(PageBuilder::grid('news', 999)));
        $this->assertSame([], PageBuilder::grid('nesushchestvuyushchiy', 3));
        $this->assertSame([], PageBuilder::grid('', 3));
    }

    public function testExternalVideoUrlIsRejected(): void
    {
        $this->assertSame('', PageBuilder::fileUrl('https://youtube.com/watch?v=1'));
        $this->assertSame('', PageBuilder::fileUrl('//cdn.example.com/a.mp4'));
        $this->assertSame('/themes/elv/assets/videos/demo.mp4', PageBuilder::fileUrl('assets/videos/demo.mp4'));
    }

    public function testQuoteBlockRendersNote(): void
    {
        $page = $this->makePage(['slug' => 's-vyzovom']);
        $this->addBlock($page, 'quote', [
            'kind' => 'warning',
            'text' => 'Записываться заранее.',
            'author' => 'Администрация',
        ]);

        $html = $this->get('/s-vyzovom/')->getContent();

        $this->assertStringContainsString('alert-warning', $html);
        $this->assertStringContainsString('Записываться заранее.', $html);
        $this->assertStringContainsString('Администрация', $html);
    }

    public function testBlockClassesExistInStylesheets(): void
    {
        $theme = Theme::getActiveTheme()->getPath();
        $css = '';

        foreach (['/assets/vendor/bootstrap.min.css', '/assets/css/styles.css'] as $file) {
            $this->assertFileExists($theme . $file);
            $css .= file_get_contents($theme . $file);
        }

        $missing = [];

        foreach (glob($theme . '/partials/blocks/*.htm') ?: [] as $partial) {
            $markup = file_get_contents($partial);
            preg_match_all('/class="([^"]*)"/', $markup, $matches);

            foreach ($matches[1] as $value) {
                $value = preg_replace('/\{%[\s\S]*?%\}/', ' ', $value);
                $value = preg_replace('/\{\{[\s\S]*?\}\}/', ' ', $value);

                foreach (preg_split('/\s+/', (string) $value) ?: [] as $class) {
                    if ($class === '' || !preg_match('/^[_a-zA-Z][_a-zA-Z0-9-]*$/', $class)) {
                        continue;
                    }

                    if (!str_contains($css, '.' . $class)) {
                        $missing[] = $class . ' (' . basename($partial) . ')';
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)), 'классы блоков без правил в CSS');
    }

    public function testBlueprintGroupsAndSourcesMatchPartials(): void
    {
        $theme = Theme::getActiveTheme()->getPath();
        $blueprint = Yaml::parse(file_get_contents(dirname(__DIR__) . '/blueprints/blocks/builder.yaml'));

        $groups = array_keys($blueprint['fields']['blocks']['groups']);

        $this->assertSame(PageBuilder::GROUPS, $groups);

        foreach ($groups as $group) {
            $this->assertFileExists($theme . '/partials/blocks/' . $group . '.htm');
        }

        $this->assertFileExists($theme . '/partials/blocks/list.htm');

        $options = array_keys($blueprint['fields']['blocks']['groups']['records']['fields']['source']['options']);

        $this->assertSame(array_keys(PageBuilder::SOURCES), $options);

        foreach (PageBuilder::SOURCES as $source) {
            $this->assertFileExists($theme . '/partials/' . $source['card'] . '.htm');
        }
    }

    /**
     * makePage создаёт опубликованную страницу конструктора.
     */
    protected function makePage(array $attributes = []): StructureRecord
    {
        $page = StructureRecord::inSection('Elv\\Page');
        $page->title = $attributes['title'] ?? 'Демо-страница';
        $page->slug = $attributes['slug'] ?? 'demo';
        $page->is_enabled = $attributes['is_enabled'] ?? true;
        $page->excerpt = $attributes['excerpt'] ?? '';
        $page->save();

        return $page;
    }

    /**
     * addBlock добавляет строку повторителя выбранной группы.
     */
    protected function addBlock(StructureRecord $page, string $group, array $data): RepeaterItem
    {
        $page->blocks;

        $item = $page->blocks()->getRelated()->newInstance();
        $item->content_group = $group;

        foreach ($data as $field => $value) {
            $item->{$field} = $value;
        }

        $page->blocks()->save($item);

        return $item;
    }

    /**
     * importFixture заливает урезанный экспорт источника в тестовую БД.
     */
    protected function importFixture(): void
    {
        $code = Artisan::call('elv:import', [
            '--source' => dirname(__DIR__) . '/tests/fixtures/db/config/data',
        ]);

        $this->assertSame(0, $code);
        Content::flush();
    }
}
