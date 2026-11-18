<?php namespace Elv\Site\Tests;

use Elv\Site\Classes\SeoAnalyzer;
use PluginTestCase;

/**
 * SeoAnalyzerTest — правила «светофора» SEO-панели.
 *
 * Проверяется то, что нельзя увидеть на глаз: ключ в адресе ищется по
 * транслиту (ж → zh или j), плотность считается в словах фразы, H1 внутри
 * текста запрещён, пропуск уровня заголовков ловится, а пустой черновик
 * не приговаривается за отсутствие текста.
 */
class SeoAnalyzerTest extends PluginTestCase
{
    /**
     * @var SeoAnalyzer
     */
    protected $analyzer;

    public function setUp(): void
    {
        parent::setUp();

        $this->analyzer = new SeoAnalyzer();
    }

    protected function analysis(array $overrides = []): array
    {
        return $this->analyzer->analyze(array_merge($this->goodRecord(), $overrides));
    }

    protected function statusOf(array $analysis, string $id): string
    {
        return $this->findCheck($analysis, $id)['status'];
    }

    protected function valueOf(array $analysis, string $id): string
    {
        return $this->findCheck($analysis, $id)['value'];
    }

    protected function findCheck(array $analysis, string $id): array
    {
        foreach ($analysis['checks'] as $check) {
            if ($check['id'] === $id) {
                return $check;
            }
        }

        $this->fail('Проверка ' . $id . ' не найдена в результате анализа.');
    }

    protected function goodRecord(): array
    {
        $content = implode('', [
            '<p>Как выбрать книгу для ребёнка — вопрос, с которым приходят в библиотеку.</p>',
            '<h2>Возраст и интересы</h2>',
            '<p>Например, малышам нужны крупные картинки и короткие истории.</p>',
            '<p>Однако школьнику важнее сюжет и герой, с которым хочется себя сравнить.</p>',
            '<h2>Проверка перед покупкой</h2>',
            '<p>Поэтому откройте первую страницу и прочитайте абзац вслух.</p>',
            '<p>Кроме того, посмотрите на <a href="/catalog/">каталог книг</a> и '
                . '<a href="https://example.org/reviews">обзоры</a>.</p>',
            '<p><img src="/images/shelf.jpg" alt="Полка с детскими книгами"></p>',
            '<p>Кроме того, библиотекарь подскажет, какие полки стоит обойти внимательно.</p>',
            '<p>При этом важно смотреть на шрифт, формат и качество бумаги.</p>',
            '<p>Например, крупный шрифт помогает начинающему читателю не уставать.</p>',
            '<p>В итоге ребёнок сам расскажет, что ему понравилось в книге.</p>',
            '<p>Более того, обсуждение сюжета закрепляет привычку читать каждый день.</p>',
            '<p>Затем спросите, какую историю хочется прочитать следующей.</p>',
            '<p>Наконец, запишите автора и название, чтобы вернуться к ним позже.</p>',
        ]);

        return [
            'title' => 'Как выбрать книгу для ребёнка: 7 критериев',
            'seo_title' => 'Как выбрать книгу для ребёнка: 7 критериев',
            'seo_description' => 'Как выбрать книгу для ребёнка: критерии возраста, шрифта и формата. '
                . 'Советы библиотекаря и короткий чек-лист перед покупкой.',
            'slug' => 'kak-vybrat-knigu-dlya-rebenka',
            'keyphrase' => 'как выбрать книгу',
            'content' => $content,
        ];
    }

    public function testGoodRecordPassesEveryCheck(): void
    {
        $analysis = $this->analysis();

        foreach ($analysis['checks'] as $check) {
            $this->assertNotSame(
                SeoAnalyzer::BAD,
                $check['status'],
                'Проверка «' . $check['title'] . '» не должна быть красной: ' . $check['value']
            );
        }

        $this->assertSame(SeoAnalyzer::GOOD, $analysis['status']);
        $this->assertSame('Хорошо', $analysis['label']);
        $this->assertGreaterThan(100, $analysis['stats']['words']);
        $this->assertGreaterThanOrEqual(SeoAnalyzer::DENSITY_MIN, $analysis['stats']['density']);
        $this->assertLessThanOrEqual(SeoAnalyzer::DENSITY_MAX, $analysis['stats']['density']);
    }

    public function testAnalysisHasEveryRule(): void
    {
        $ids = array_map(function ($check) {
            return $check['id'];
        }, $this->analysis()['checks']);

        $this->assertSame([
            'keyphrase_set',
            'keyphrase_in_title',
            'keyphrase_in_description',
            'keyphrase_in_slug',
            'keyphrase_in_intro',
            'keyphrase_density',
            'title_length',
            'description_length',
            'headings_h1',
            'headings_levels',
            'headings_frequency',
            'sentence_length',
            'passive_voice',
            'transition_words',
            'internal_links',
            'external_links',
            'image_alt',
            'paragraph_length',
        ], $ids);
    }

    public function testEmptyDraftIsNotPunishedForMissingText(): void
    {
        $analysis = $this->analyzer->analyze([]);

        $this->assertSame(0, $analysis['stats']['words']);
        $this->assertSame(SeoAnalyzer::OK, $this->statusOf($analysis, 'keyphrase_set'));
        $this->assertSame(SeoAnalyzer::OK, $this->statusOf($analysis, 'internal_links'));
        $this->assertSame(SeoAnalyzer::OK, $this->statusOf($analysis, 'headings_h1'));
        $this->assertSame('текста нет', $this->valueOf($analysis, 'keyphrase_in_intro'));
    }

    public function testMissingKeyphraseIsWarningNotError(): void
    {
        $analysis = $this->analysis(['keyphrase' => '']);

        $this->assertSame(SeoAnalyzer::OK, $this->statusOf($analysis, 'keyphrase_set'));
        $this->assertSame(SeoAnalyzer::OK, $this->statusOf($analysis, 'keyphrase_density'));
        $this->assertSame(SeoAnalyzer::BAD, $this->statusOf($analysis, 'keyphrase_in_title'));
    }

    public function testKeyphraseInTitleAcceptsCaseAndYo(): void
    {
        $analysis = $this->analysis([
            'keyphrase' => 'КАК ВЫБРАТЬ КНИГУ',
            'seo_title' => 'Как выбрать книгу для ребёнка: 7 критериев',
        ]);

        $this->assertSame(SeoAnalyzer::GOOD, $this->statusOf($analysis, 'keyphrase_in_title'));

        $yo = $this->analyzer->analyze([
            'keyphrase' => 'пять ёлок',
            'seo_title' => 'Пять елок в парке',
            'title' => 'Пять елок в парке',
        ]);

        $this->assertSame(SeoAnalyzer::GOOD, $this->statusOf($yo, 'keyphrase_in_title'));
    }

    public function testKeyphraseInSlugMatchesTransliterationVariants(): void
    {
        $variants = [
            'kak-vybrat-knigu-dlya-rebenka',
            'kak-vibrat-knigu',
            'kak-vybrat-knigu',
        ];

        foreach ($variants as $slug) {
            $analysis = $this->analysis(['slug' => $slug]);
            $this->assertSame(
                SeoAnalyzer::GOOD,
                $this->statusOf($analysis, 'keyphrase_in_slug'),
                'Адрес ' . $slug . ' должен считаться транслитом фразы.'
            );
        }

        foreach (['kak-vybrat', 'kniga-dlya-rebenka', ''] as $slug) {
            $analysis = $this->analysis(['slug' => $slug]);
            $this->assertSame(
                SeoAnalyzer::BAD,
                $this->statusOf($analysis, 'keyphrase_in_slug'),
                'Адрес «' . $slug . '» не содержит всей фразы.'
            );
        }
    }

    public function testSlugAcceptsZhAndKhTransliteration(): void
    {
        $analysis = $this->analyzer->analyze([
            'keyphrase' => 'журнал для детей',
            'seo_title' => 'Журнал для детей',
            'title' => 'Журнал для детей',
            'slug' => 'jurnal-dlya-detej',
        ]);

        $this->assertSame(SeoAnalyzer::GOOD, $this->statusOf($analysis, 'keyphrase_in_slug'));

        $analysis = $this->analyzer->analyze([
            'keyphrase' => 'художественная книга',
            'slug' => 'khudozhestvennaya-kniga',
        ]);

        $this->assertSame(SeoAnalyzer::GOOD, $this->statusOf($analysis, 'keyphrase_in_slug'));
    }

    public function testKeyphraseInFirstParagraphOnly(): void
    {
        $analysis = $this->analysis([
            'keyphrase' => 'семейное чтение',
            'content' => '<p>Короткое вступление без фразы.</p><p>Семейное чтение начинается с примера взрослого.</p>',
        ]);

        $this->assertSame(SeoAnalyzer::BAD, $this->statusOf($analysis, 'keyphrase_in_intro'));
    }

    public function testDensityCountsWordsOfPhrase(): void
    {
        $text = '<p>' . implode(' ', array_fill(0, 100, 'слово')) . '</p>';
        $analysis = $this->analyzer->analyze([
            'keyphrase' => 'детская книга',
            'content' => $text . '<p>детская книга</p>',
            'title' => 'Детская книга',
            'seo_title' => 'Детская книга',
        ]);

        $this->assertEqualsWithDelta(1.96, $analysis['stats']['density'], 0.01);
        $this->assertSame(SeoAnalyzer::GOOD, $this->statusOf($analysis, 'keyphrase_density'));
    }

    public function testDensityFlagsKeywordStuffing(): void
    {
        $stuffed = implode(' ', array_fill(0, 10, 'детская книга'));

        $analysis = $this->analyzer->analyze([
            'keyphrase' => 'детская книга',
            'content' => '<p>' . $stuffed . ' и ещё немного текста</p>',
            'title' => 'Детская книга',
            'seo_title' => 'Детская книга',
        ]);

        $this->assertSame(SeoAnalyzer::BAD, $this->statusOf($analysis, 'keyphrase_density'));
    }

    public function testTitleAndDescriptionLengths(): void
    {
        $long = $this->analysis(['seo_title' => str_repeat('Очень длинный заголовок ', 5)]);
        $this->assertSame(SeoAnalyzer::BAD, $this->statusOf($long, 'title_length'));

        $short = $this->analysis(['seo_description' => 'Коротко.']);
        $this->assertSame(SeoAnalyzer::BAD, $this->statusOf($short, 'description_length'));

        $near = $this->analysis(['seo_title' => str_repeat('Заголовок ', 3) . 'книги']);
        $this->assertSame(SeoAnalyzer::OK, $this->statusOf($near, 'title_length'));
    }

    public function testEmptySeoTitleFallsBackToRecordTitle(): void
    {
        $analysis = $this->analysis([
            'seo_title' => '',
            'title' => 'Как выбрать книгу для ребёнка: 7 критериев',
        ]);

        $this->assertSame(SeoAnalyzer::GOOD, $this->statusOf($analysis, 'keyphrase_in_title'));
        $this->assertSame(SeoAnalyzer::GOOD, $this->statusOf($analysis, 'title_length'));
    }

    public function testHeadingInsideContentIsError(): void
    {
        $analysis = $this->analysis([
            'content' => '<h1>Второй главный заголовок</h1><p>Текст.</p>',
        ]);

        $this->assertSame(SeoAnalyzer::BAD, $this->statusOf($analysis, 'headings_h1'));
    }

    public function testSkippedHeadingLevelIsError(): void
    {
        $analysis = $this->analysis([
            'content' => '<p>Текст.</p><h2>Раздел</h2><p>Ещё текст.</p><h4>Подраздел</h4>',
        ]);

        $this->assertSame(SeoAnalyzer::BAD, $this->statusOf($analysis, 'headings_levels'));

        $ordered = $this->analysis([
            'content' => '<p>Текст.</p><h2>Раздел</h2><h3>Подраздел</h3>',
        ]);

        $this->assertSame(SeoAnalyzer::GOOD, $this->statusOf($ordered, 'headings_levels'));
        $this->assertSame('H2, H3', $this->valueOf($ordered, 'headings_levels'));
    }

    public function testLongSentencesAreCounted(): void
    {
        $longSentence = implode(' ', array_fill(0, 30, 'слово')) . '.';
        $analysis = $this->analysis([
            'content' => '<p>' . str_repeat($longSentence . ' ', 4) . 'Коротко.</p>',
        ]);

        $this->assertSame(SeoAnalyzer::BAD, $this->statusOf($analysis, 'sentence_length'));
        $this->assertSame('4 из 5', $this->valueOf($analysis, 'sentence_length'));
    }

    public function testPassiveVoiceIsCounted(): void
    {
        $analysis = $this->analysis([
            'content' => '<p>Книга была прочитана. Полка была открыта. Выдача была оформлена.</p>',
        ]);

        $this->assertSame(SeoAnalyzer::BAD, $this->statusOf($analysis, 'passive_voice'));
    }

    public function testTransitionWordsAreCounted(): void
    {
        $analysis = $this->analysis([
            'content' => '<p>Однако книга нашлась. Поэтому мы её взяли. Например, вчера.</p>',
        ]);

        $this->assertSame(SeoAnalyzer::GOOD, $this->statusOf($analysis, 'transition_words'));

        $plain = $this->analysis(['content' => '<p>Книга нашлась. Мы её взяли. Вчера.</p>']);
        $this->assertSame(SeoAnalyzer::BAD, $this->statusOf($plain, 'transition_words'));
    }

    public function testLinksAndImagesAreCounted(): void
    {
        $analysis = $this->analysis([
            'content' => '<p><a href="/catalog/">Каталог</a> и <a href="https://example.org">Обзор</a></p>'
                . '<p><img src="/a.jpg" alt="Полка"><img src="/b.jpg"></p>',
        ]);

        $this->assertSame(SeoAnalyzer::GOOD, $this->statusOf($analysis, 'internal_links'));
        $this->assertSame(SeoAnalyzer::GOOD, $this->statusOf($analysis, 'external_links'));
        $this->assertSame(SeoAnalyzer::BAD, $this->statusOf($analysis, 'image_alt'));
        $this->assertSame('без alt: 1', $this->valueOf($analysis, 'image_alt'));
    }

    public function testImageAltIgnoresEmptyAndSpaces(): void
    {
        $analysis = $this->analysis([
            'content' => '<p><img src="/a.jpg" alt="  "><img src="/b.jpg" alt="Книга"></p>',
        ]);

        $this->assertSame(SeoAnalyzer::BAD, $this->statusOf($analysis, 'image_alt'));
    }

    public function testLongParagraphIsFlagged(): void
    {
        $long = '<p>' . implode(' ', array_fill(0, 150, 'слово')) . '</p>';

        $analysis = $this->analysis(['content' => $long . '<p>Короткий абзац.</p>']);

        $this->assertSame(SeoAnalyzer::OK, $this->statusOf($analysis, 'paragraph_length'));
        $this->assertSame('самый длинный: 150 слов', $this->valueOf($analysis, 'paragraph_length'));

        $huge = '<p>' . implode(' ', array_fill(0, 300, 'слово')) . '</p>';
        $this->assertSame(
            SeoAnalyzer::BAD,
            $this->statusOf($this->analysis(['content' => $huge]), 'paragraph_length')
        );
    }

    public function testScriptsAndStylesDoNotCountAsText(): void
    {
        $analysis = $this->analysis([
            'content' => '<p>Текст</p><script>var a = "много слов тут";</script><style>.a{color:red}</style>',
        ]);

        $this->assertSame(1, $analysis['stats']['words']);
    }

    public function testOverallScoreUsesBadAndWarningCounts(): void
    {
        $analysis = $this->analysis([
            'seo_description' => 'Слишком короткое описание.',
            'content' => '<h1>Лишний H1</h1><p><a href="/catalog/">Каталог</a></p>',
            'keyphrase' => 'совсем другая фраза',
        ]);

        $this->assertSame(SeoAnalyzer::BAD, $analysis['status']);
        $this->assertSame('Нужно доработать', $analysis['label']);
        $this->assertGreaterThan(2, $analysis['stats']['bad']);
    }
}
