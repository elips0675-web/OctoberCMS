<?php namespace Elv\Library\Tests;

use Elv\Library\Classes\Media;
use TestCase;

/**
 * MediaTest — адаптивные картинки, инлайн CSS и форматирование.
 *
 * Файлы берутся из рабочей темы `themes/elv`: тест проверяет не мок,
 * а реальные варианты 480/960 и настоящий `bootstrap.min.css`.
 */
class MediaTest extends TestCase
{
    public function testImagePathDecodesMediafinderValues()
    {
        $this->assertSame('assets/images/news-1.jpg', Media::imagePath('assets/images/news-1.jpg'));
        $this->assertSame('assets/images/news-1.jpg', Media::imagePath('"assets/images/news-1.jpg"'));
        $this->assertSame('assets/images/news-1.jpg', Media::imagePath(['assets/images/news-1.jpg']));
        $this->assertSame('assets/images/news-1.jpg', Media::imagePath('["assets/images/news-1.jpg"]'));
        $this->assertSame('', Media::imagePath(null));
        $this->assertSame('', Media::imagePath('   '));
        $this->assertSame('', Media::imagePath('[]'));
    }

    public function testPictureRendersVariantsAndAlt()
    {
        $html = Media::picture('assets/images/hero-1.jpg', 'Библиотека', [
            'class' => 'hero-img',
            'sizes' => '(max-width: 991px) 100vw, 50vw',
            'priority' => true,
        ]);

        $this->assertStringStartsWith('<picture class="elv-picture">', $html);
        $this->assertStringContainsString('type="image/avif"', $html);
        $this->assertStringContainsString('type="image/webp"', $html);
        $this->assertStringContainsString('hero-1-480.jpg 480w', $html);
        $this->assertStringContainsString('/themes/elv/assets/images/hero-1.jpg', $html);
        $this->assertStringContainsString('alt="Библиотека"', $html);
        $this->assertStringContainsString('class="hero-img"', $html);
        $this->assertStringContainsString('loading="eager"', $html);
        $this->assertStringContainsString('fetchpriority="high"', $html);
        $this->assertStringContainsString('sizes="(max-width: 991px) 100vw, 50vw"', $html);
    }

    public function testPictureWithoutSourceIsEmpty()
    {
        $this->assertSame('', Media::picture('', 'Подпись'));
        $this->assertSame('', Media::picture(null));
    }

    public function testPictureLazyByDefaultAndEscapesAlt()
    {
        $html = Media::picture('assets/images/news-1.jpg', 'Кавычки "и" <теги>');

        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringContainsString('fetchpriority="low"', $html);
        $this->assertStringContainsString('alt="Кавычки &quot;и&quot; &lt;теги&gt;"', $html);
    }

    public function testVariantsSkipNonJpegAndMissingFiles()
    {
        $this->assertSame([], Media::variants('assets/images/avatar-tatyana.svg'));
        $this->assertSame([], Media::variants('assets/images/does-not-exist.jpg'));
    }

    public function testVariantsReturnFullAndWidths()
    {
        $variants = Media::variants('assets/images/hero-1.jpg');

        $this->assertArrayHasKey('full', $variants);
        $this->assertArrayHasKey('widths', $variants);
        $this->assertNotEmpty($variants['widths']);
        $this->assertSame('assets/images/hero-1.jpg', $variants['full']['jpg']);
        $this->assertSame('assets/images/hero-1.webp', $variants['full']['webp']);
        $this->assertSame(480, $variants['widths'][0]['width']);
    }

    public function testSrcsetUsesThemeUrls()
    {
        $srcset = Media::srcset(Media::variants('assets/images/hero-1.jpg'), 'jpg');

        $this->assertStringContainsString('/themes/elv/assets/images/hero-1-480.jpg 480w', $srcset);
        $this->assertStringContainsString(', ', $srcset);
    }

    public function testMinifyCssKeepsStringsAndCalc()
    {
        $css = "/* комментарий */\n.a {\n  width: calc(100% - 1rem);\n  content: \"a  b\";\n}\n";

        $minified = Media::minifyCss($css);

        $this->assertStringNotContainsString('комментарий', $minified);
        $this->assertStringContainsString('calc(100% - 1rem)', $minified);
        $this->assertStringContainsString('"a  b"', $minified);
        $this->assertSame('.a{width: calc(100% - 1rem);content: "a  b";}', $minified);
    }

    public function testMinifyCssDropsSpacesAroundBraces()
    {
        $this->assertSame('.a{.b{margin: 0;}}', Media::minifyCss(".a {\n .b { margin: 0; }\n}"));
    }

    public function testInlineCssMinifiesThemeFile()
    {
        $css = Media::inlineCss('assets/vendor/bootstrap.min.css');

        $this->assertNotSame('', $css);
        $this->assertStringNotContainsString('/*', $css);
        $this->assertSame($css, Media::inlineCss('assets/vendor/bootstrap.min.css'));
    }

    public function testInlineCssMissingFileIsEmpty()
    {
        $this->assertSame('', Media::inlineCss('assets/css/does-not-exist.css'));
    }

    public function testNumberFormatting()
    {
        $this->assertSame('1 344', Media::num(1344));
        $this->assertSame('0', Media::num('0'));
        $this->assertSame('от 350 ₽', Media::price(350));
        $this->assertSame('', Media::price(0));
        $this->assertSame('350 ₽', Media::money(350));
        $this->assertSame('0 ₽', Media::money(null));
    }

    public function testCoverColorIsStable()
    {
        $color = Media::coverColor('Брелок «Сова»');

        $this->assertContains($color, ['primary', 'green', 'sky', 'pink', 'orange']);
        $this->assertSame($color, Media::coverColor('Брелок «Сова»'));
    }

    public function testCollectionColorMap()
    {
        $this->assertSame('blue', Media::collectionColor('primary'));
        $this->assertSame('violet', Media::collectionColor('pink'));
        $this->assertSame('orange', Media::collectionColor('orange'));
        $this->assertSame('blue', Media::collectionColor('unknown'));
    }

    public function testPriceLabel()
    {
        $this->assertSame('от 500 ₽', Media::priceLabel('', 500));
        $this->assertSame('По договору', Media::priceLabel('По договору', 500));
        $this->assertSame('Бесплатно для читателей', Media::priceLabel('', 0));
    }

    public function testScheduleSplitsDayAndTime()
    {
        $this->assertSame(
            [['day' => 'Пн–Пт', 'time' => '10:00–21:00'], ['day' => 'Сб', 'time' => '11:00–18:00']],
            Media::schedule("Пн–Пт: 10:00–21:00\n\nСб: 11:00–18:00\n")
        );
        $this->assertSame([['day' => 'Круглосуточно', 'time' => '']], Media::schedule('Круглосуточно'));
    }

    public function testPhoneLinkKeepsDigitsAndPlus()
    {
        $this->assertSame('+375291112233', Media::phoneLink('+375 (29) 111-22-33'));
        $this->assertSame('', Media::phoneLink(null));
    }

    public function testDateRu()
    {
        $this->assertSame('6 ноября 2026', Media::dateRu('2026-11-06'));
        $this->assertSame('06.11', Media::dateRu('2026-11-06', 'd.m'));
        $this->assertSame('', Media::dateRu(null));
        $this->assertSame('', Media::dateRu(''));
    }

    public function testValueAndLines()
    {
        $this->assertSame('a, b', Media::value(['a', 'b']));
        $this->assertSame('', Media::value(null));
        $this->assertSame(['первая', 'вторая'], Media::lines(" первая \n\n вторая\r\n"));
        $this->assertSame([], Media::lines(''));
    }
}
