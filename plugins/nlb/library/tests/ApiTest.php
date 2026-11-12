<?php namespace Nlb\Library\Tests;

use Artisan;
use Nlb\Library\Classes\Content;
use Nlb\Library\Classes\Sitemap;
use PluginTestCase;
use Tailor\Models\EntryRecord;

/**
 * ApiTest — публичный JSON контента (`/api/v1/...`, P2-14).
 *
 * Проверяется контракт, а не «что-нибудь ответило»: набор полей и их типы,
 * абсолютный `url`, отсутствие отключённых записей, 404 в JSON для чужого
 * раздела и чужого slug, 422 для негодного `limit` и `Cache-Control` у списка.
 */
class ApiTest extends PluginTestCase
{
    protected $useTransactions = true;

    protected $autoMigrateTailor = true;

    public function setUp(): void
    {
        parent::setUp();

        $code = Artisan::call('nlb:import', [
            '--source' => dirname(__DIR__) . '/tests/fixtures/db/config/data',
        ]);

        $this->assertSame(0, $code);

        Content::flush();
    }

    public function testIndexAnswersJsonWithContractFields()
    {
        $response = $this->get('/api/v1/books');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/json');

        $json = $response->json();

        $this->assertSame('books', $json['meta']['section']);
        $this->assertSame(1, $json['meta']['count']);
        $this->assertCount(1, $json['data']);

        $item = $json['data'][0];

        $this->assertSame(\Nlb\Library\Classes\Http\ApiController::FIELDS, array_keys($item));
        $this->assertIsInt($item['id']);
        $this->assertSame('krzhka-biblioteka', $item['slug']);
        $this->assertSame('Кружка «Библиотека»', $item['title']);
        $this->assertSame('Анонс сувенира.', $item['excerpt']);
        $this->assertNull($item['image']);
        $this->assertSame('http://localhost:8080/books/krzhka-biblioteka/', $item['url']);
        $this->assertMatchesRegularExpression('~^\d{4}-\d{2}-\d{2}T~', (string) $item['updated_at']);
        $cacheControl = (string) $response->headers->get('Cache-Control');

        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=60', $cacheControl);    }

    public function testIndexExcludesDisabledRecord()
    {
        $book = EntryRecord::inSection('Nlb\Book')->where('slug', 'krzhka-biblioteka')->first();

        $this->assertNotNull($book);

        $book->is_enabled = false;
        $book->save();
        Content::flush();

        $json = $this->get('/api/v1/books')->json();

        $this->assertSame(0, $json['meta']['count']);
        $this->assertSame([], $json['data']);
    }

    public function testIndexLimitCutsListAndRejectsBadValue()
    {
        $this->assertSame(1, $this->get('/api/v1/books?limit=1')->json()['meta']['count']);

        $this->get('/api/v1/books?limit=0')->assertStatus(422);
        $this->get('/api/v1/books?limit=101')->assertStatus(422);
        $this->get('/api/v1/books?limit=abc')->assertStatus(422);
    }

    public function testUnknownHandleIsJson404()
    {
        $response = $this->get('/api/v1/unknown');

        $response->assertStatus(404);
        $response->assertHeader('content-type', 'application/json');

        $this->assertStringContainsString('Неизвестный раздел', $response->json()['message']);
    }

    public function testShowReturnsRecordBySlug()
    {
        $response = $this->get('/api/v1/books/krzhka-biblioteka');

        $response->assertStatus(200);

        $item = $response->json()['data'];

        $this->assertSame(\Nlb\Library\Classes\Http\ApiController::FIELDS, array_keys($item));
        $this->assertSame('krzhka-biblioteka', $item['slug']);
        $this->assertSame('http://localhost:8080/books/krzhka-biblioteka/', $item['url']);
    }

    public function testShowMissingOrDisabledSlugIs404()
    {
        $this->get('/api/v1/books/net-takogo-slug')->assertStatus(404);

        $book = EntryRecord::inSection('Nlb\Book')->where('slug', 'krzhka-biblioteka')->first();
        $book->is_enabled = false;
        $book->save();
        Content::flush();

        $this->get('/api/v1/books/krzhka-biblioteka')->assertStatus(404);
    }

    public function testIndexAcceptsEverySectionOfSitemap()
    {
        foreach (array_keys(Sitemap::SECTIONS) as $prefix) {
            $response = $this->get('/api/v1/' . $prefix);

            $response->assertStatus(200);

            $this->assertSame($prefix, $response->json()['meta']['section']);
        }
    }
}
