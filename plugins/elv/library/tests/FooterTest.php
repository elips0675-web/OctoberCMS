<?php namespace Elv\Library\Tests;

use Cms\Classes\Theme;
use Config;
use Elv\Library\Classes\Brand;
use PluginTestCase;

/**
 * FooterTest — год копирайта в подвале.
 *
 * Год в «© …» не должен требовать правки файла: жёсткий литерал соврёт
 * в январе следующего года, а исправление одной цифры — это деплой.
 * Источник проверяется отдельно от рендера: рендер сегодня совпадает у обоих
 * вариантов (год тот же), а литерал выдаёт себя в исходнике партиала.
 */
class FooterTest extends PluginTestCase
{
    protected $useTransactions = true;

    protected $autoMigrateTailor = true;

    protected $previousTheme;

    public function setUp(): void
    {
        parent::setUp();

        $this->previousTheme = Config::get('cms.active_theme');
        Config::set('cms.active_theme', 'elv');
        Theme::resetCache();
    }

    public function tearDown(): void
    {
        Config::set('cms.active_theme', $this->previousTheme);
        Theme::resetCache();

        parent::tearDown();
    }

    public function testFooterYearComesFromClockNotFromLiteral(): void
    {
        $path = Theme::getActiveTheme()->getPath() . '/partials/footer.htm';
        $source = file_get_contents($path);

        $this->assertSame(0, preg_match('/©\s*20\d\d/u', $source), 'в подвале жёсткий год вместо даты');
        $this->assertSame(1, preg_match('/©\s*\{\{\s*\'now\'\s*\|\s*date\(\'Y\'\)\s*\}\}/u', $source));
    }

    public function testFooterRendersCurrentYearAndKeepsBrandYear(): void
    {
        $html = $this->get('/')->getContent();

        $copyright = '© ' . date('Y') . ' ' . Brand::get('name') . '. Все права защищены.';
        $this->assertSame(1, substr_count($html, $copyright));

        $this->assertStringContainsString(Brand::get('name'), $html);
    }
}
