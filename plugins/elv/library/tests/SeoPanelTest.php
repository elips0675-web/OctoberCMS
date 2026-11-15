<?php namespace Elv\Library\Tests;

use Backend\Classes\FormWidgetBase;
use Backend\Classes\WidgetManager;
use Elv\Library\Classes\ContentFields\SeoPanelField;
use Elv\Library\Classes\SeoAnalyzer;
use Elv\Library\FormWidgets\SeoPanel;
use PluginTestCase;
use Tailor\Classes\FieldManager;

/**
 * SeoPanelTest — панель анализа: шаблон, регистрация и поля blueprint'ов.
 *
 * Проверяется то, что не видно в редакторе: текст проверок экранируется,
 * зелёные пункты не сыплют подсказками, виджет и поле Tailor реально
 * зарегистрированы, а все записи с текстом получили панель.
 */
class SeoPanelTest extends PluginTestCase
{
    protected function goodAnalysis(): array
    {
        return (new SeoAnalyzer())->analyze([
            'title' => 'Как выбрать книгу для ребёнка: 7 критериев',
            'seo_title' => 'Как выбрать книгу для ребёнка: 7 критериев',
            'seo_description' => 'Как выбрать книгу для ребёнка: критерии возраста, шрифта и формата. '
                . 'Советы библиотекаря и короткий чек-лист перед покупкой.',
            'slug' => 'kak-vybrat-knigu-dlya-rebenka',
            'keyphrase' => 'как выбрать книгу',
            'content' => implode('', [
                '<p>Как выбрать книгу для ребёнка — вопрос, с которым приходят в библиотеку.</p>',
                '<h2>Возраст и интересы</h2>',
                '<p>Например, малышам нужны крупные картинки и короткие истории.</p>',
                '<p>Однако школьнику важнее сюжет и герой, с которым хочется себя сравнить.</p>',
                '<p>Поэтому откройте первую страницу и прочитайте абзац вслух.</p>',
                '<p>Кроме того, посмотрите на <a href="/catalog/">каталог книг</a> и '
                    . '<a href="https://example.org/reviews">обзоры</a>.</p>',
                '<p><img src="/images/shelf.jpg" alt="Полка с детскими книгами"></p>',
                '<p>При этом важно смотреть на шрифт, формат и качество бумаги.</p>',
                '<p>Например, крупный шрифт помогает начинающему читателю не уставать.</p>',
                '<p>В итоге ребёнок сам расскажет, что ему понравилось в книге.</p>',
                '<p>Более того, обсуждение сюжета закрепляет привычку читать каждый день.</p>',
                '<p>Затем спросите, какую историю хочется прочитать следующей.</p>',
                '<p>Наконец, запишите автора и название, чтобы вернуться к ним позже.</p>',
            ]),
        ]);
    }

    protected function renderAnalysis(array $analysis): string
    {
        $path = base_path('plugins/elv/library/formwidgets/seopanel/partials/_analysis.php');

        ob_start();
        extract(['analysis' => $analysis], EXTR_SKIP);
        include $path;

        return (string) ob_get_clean();
    }

    public function testPartialRendersSummaryAndEveryCheck(): void
    {
        $analysis = $this->goodAnalysis();
        $html = $this->renderAnalysis($analysis);

        $this->assertStringContainsString('seo-panel__summary--' . $analysis['status'], $html);
        $this->assertStringContainsString('Хорошо', $html);
        $this->assertStringContainsString('Ключ в заголовке', $html);
        $this->assertSame(count($analysis['checks']), substr_count($html, 'seo-panel__check '));
        $this->assertStringContainsString('слов', $html);
    }

    public function testPartialEscapesKeyphrase(): void
    {
        $analysis = (new SeoAnalyzer())->analyze([
            'keyphrase' => '<script>alert(1)</script>',
            'title' => 'Заголовок',
            'content' => '<p>Текст</p>',
        ]);

        $html = $this->renderAnalysis($analysis);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function testPartialHidesHintsForGreenChecks(): void
    {
        $analysis = $this->goodAnalysis();
        $html = $this->renderAnalysis($analysis);

        $this->assertStringNotContainsString('Впишите фразу в SEO-заголовок', $html);
        $this->assertStringNotContainsString('seo-panel__check-hint', $html);
    }

    public function testPartialShowsHintForBrokenCheck(): void
    {
        $analysis = (new SeoAnalyzer())->analyze([
            'title' => 'Коротко',
            'keyphrase' => 'книга',
            'content' => '<p>Книга</p>',
        ]);

        $html = $this->renderAnalysis($analysis);

        $this->assertStringContainsString('seo-panel__check-hint', $html);
        $this->assertStringContainsString('Уложите заголовок в', $html);
    }

    public function testWidgetAndFieldAreRegistered(): void
    {
        $this->assertSame(
            SeoPanel::class,
            WidgetManager::instance()->resolveFormWidget('elvseopanel')
        );

        $this->assertSame(
            SeoPanelField::class,
            FieldManager::instance()->resolveCustomField('seo_panel')
        );
    }

    public function testWidgetExtendsFormWidgetBase(): void
    {
        $reflection = new \ReflectionClass(SeoPanel::class);

        $this->assertTrue($reflection->isSubclassOf(FormWidgetBase::class));
        $this->assertTrue($reflection->hasMethod('onAnalyze'));
        $this->assertTrue($reflection->hasMethod('prepareVars'));
    }

    public function testWidgetRendersFieldAndPanel(): void
    {
        $widget = $this->makeWidget('как выбрать книгу');
        $html = $widget->render();

        $this->assertStringContainsString('data-seopanel', $html);
        $this->assertStringContainsString('name="seo_focus_keyphrase"', $html);
        $this->assertStringContainsString('value="как выбрать книгу"', $html);
        $this->assertStringContainsString('seo-panel__serp', $html);
        $this->assertStringContainsString('Ключевая фраза', $html);
        $this->assertStringContainsString('Предпросмотр сниппета', $html);
    }

    public function testOnAnalyzeRecalculatesFromPostedValues(): void
    {
        $widget = $this->makeWidget('');

        $request = \Request::create('/admin/elv/news/update/1', 'POST', [
            'title' => 'Коротко',
            'keyphrase' => 'книга',
            'content' => '<p>Книга</p>',
        ]);
        $this->app->instance('request', $request);

        $html = $widget->onAnalyze();

        $this->assertStringContainsString('Уложите заголовок в', $html);
        $this->assertStringContainsString('seo-panel__summary', $html);
    }

    protected function makeWidget(string $value): SeoPanel
    {
        $controller = new \Backend\Classes\Controller();
        $field = new \Backend\Classes\FormField('seo_focus_keyphrase', 'Ключевая фраза');
        $field->value = $value;

        return new SeoPanel($controller, $field);
    }

    public function testEveryContentBlueprintUsesPanel(): void
    {
        foreach (['books', 'collections', 'events', 'news', 'printing', 'services'] as $handle) {
            $path = base_path('plugins/elv/library/blueprints/' . $handle . '/item.yaml');
            $this->assertFileExists($path);

            $yaml = (string) file_get_contents($path);

            $this->assertStringContainsString(
                'seo_focus_keyphrase:',
                $yaml,
                'Раздел ' . $handle . ' должен иметь поле ключевой фразы.'
            );
            $this->assertStringContainsString(
                'type: seo_panel',
                $yaml,
                'Раздел ' . $handle . ' должен показывать панель анализа.'
            );
        }
    }

    public function testPageStructureKeepsPlainKeyphraseField(): void
    {
        $yaml = (string) file_get_contents(
            base_path('plugins/elv/library/blueprints/page/structure.yaml')
        );

        $this->assertStringContainsString('seo_focus_keyphrase:', $yaml);
        $this->assertStringNotContainsString('type: seo_panel', $yaml);
    }
}
