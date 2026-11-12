<?php namespace Nlb\Library\Tests;

use Artisan;
use Nlb\Library\Classes\Content;
use Nlb\Library\Classes\Rss;
use Nlb\Library\Classes\Seo;
use PluginTestCase;
use Tailor\Models\EntryRecord;

/**
 * RssTest — лента `/rss.xml`.
 *
 * Лента — это контракт с читалкой, а не страница: ошибка в XML не видна
 * в браузере, зато видна подписчику. Поэтому проверяется разбор документа,
 * обязательные поля каждого `<item>`, порядок по дате, отсечение отключённой
 * записи и экранирование текста.
 */
class RssTest extends PluginTestCase
{
    protected $useTransactions = true;

    protected $autoMigrateTailor = true;

    public function setUp(): void
    {
        parent::setUp();

        Content::flush();
    }

    public function tearDown(): void
    {
        Content::flush();

        parent::tearDown();
    }

    public function testFeedIsWellFormedXml(): void
    {
        $this->importFixture();

        $xml = $this->parse(Rss::xml('http://localhost'));

        $this->assertSame('2.0', (string) $xml['version']);
        $this->assertSame('rss', $xml->getName());
        $this->assertSame('channel', $xml->children()->getName());
    }

    public function testChannelHasSiteMetadata(): void
    {
        $this->importFixture();

        $channel = $this->parse(Rss::xml('http://localhost'))->channel;

        $this->assertSame(Seo::SITE_NAME . ' — новости', (string) $channel->title);
        $this->assertSame('http://localhost/news/', (string) $channel->link);
        $this->assertSame('ru', (string) $channel->language);
        $this->assertSame('http://localhost/rss.xml', (string) $channel->children('http://www.w3.org/2005/Atom')->link->attributes()['href']);
    }

    public function testEveryItemHasTitleLinkGuidAndDate(): void
    {
        $this->importFixture();

        $channel = $this->parse(Rss::xml('http://localhost'))->channel;
        $this->assertGreaterThan(0, $channel->item->count());

        foreach ($channel->item as $item) {
            $link = (string) $item->link;

            $this->assertNotSame('', trim((string) $item->title));
            $this->assertStringStartsWith('http://localhost/news/', $link);
            $this->assertSame($link, (string) $item->guid);
            $this->assertNotFalse(strtotime((string) $item->pubDate));
        }
    }

    public function testItemsAreSortedByDateDescending(): void
    {
        $this->importFixture();

        $timestamps = [];
        foreach ($this->parse(Rss::xml('http://localhost'))->channel->item as $item) {
            $timestamps[] = (int) strtotime((string) $item->pubDate);
        }

        $sorted = $timestamps;
        rsort($sorted);

        $this->assertSame($sorted, $timestamps);
    }

    public function testDisabledRecordIsExcluded(): void
    {
        $this->importFixture();

        $news = EntryRecord::inSection('Nlb\News')->where('xml_id', 'nlb-news-001')->first();
        $this->assertNotNull($news);

        $slug = (string) $news->slug;
        $this->assertStringContainsString('/news/' . $slug . '/', Rss::xml('http://localhost'));

        $news->is_enabled = false;
        $news->save();
        Content::flush();

        $this->assertStringNotContainsString('/news/' . $slug . '/', Rss::xml('http://localhost'));
    }

    public function testExcerptIsEscapedAndParsedBack(): void
    {
        $this->importFixture();

        $news = EntryRecord::inSection('Nlb\News')->where('xml_id', 'nlb-news-001')->first();
        $news->excerpt = 'Скидка & <акция> "осень"';
        $news->save();
        Content::flush();

        $xml = Rss::xml('http://localhost');
        $this->assertStringContainsString('&amp;', $xml);
        $this->assertStringNotContainsString('<акция>', $xml);

        $channel = $this->parse($xml)->channel;
        $found = false;

        foreach ($channel->item as $item) {
            if ((string) $item->link === 'http://localhost/news/' . $news->slug . '/') {
                $this->assertSame('Скидка & <акция> "осень"', (string) $item->description);
                $found = true;
            }
        }

        $this->assertTrue($found, 'Запись с изменённым описанием не попала в ленту');
    }

    public function testContentIsUsedWhenExcerptIsEmpty(): void
    {
        $this->importFixture();

        $news = EntryRecord::inSection('Nlb\News')->where('xml_id', 'nlb-news-001')->first();
        $news->excerpt = '';
        $news->content = '<p>Первый абзац.</p><p>Второй абзац.</p>';
        $news->save();
        Content::flush();

        $channel = $this->parse(Rss::xml('http://localhost'))->channel;

        foreach ($channel->item as $item) {
            if ((string) $item->link === 'http://localhost/news/' . $news->slug . '/') {
                $this->assertSame('Первый абзац. Второй абзац.', (string) $item->description);

                return;
            }
        }

        $this->fail('Запись не попала в ленту');
    }

    public function testEmptyFeedIsStillValid(): void
    {
        $xml = $this->parse(Rss::xml('http://localhost'));

        $this->assertSame('channel', $xml->children()->getName());
        $this->assertSame(0, $xml->channel->item->count());
    }

    public function testRouteServesRss(): void
    {
        $this->importFixture();

        $response = $this->get('/rss.xml');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/rss+xml', (string) $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $response->getContent());
        $this->assertNotNull($this->parse($response->getContent()));
    }

    protected function parse(string $xml): \SimpleXMLElement
    {
        $parsed = simplexml_load_string($xml);

        $this->assertNotFalse($parsed, 'Лента не разбирается как XML');

        return $parsed;
    }

    protected function importFixture(): void
    {
        $code = Artisan::call('nlb:import', [
            '--source' => dirname(__DIR__) . '/tests/fixtures/db/config/data',
        ]);

        $this->assertSame(0, $code);
        Content::flush();
    }
}
