<?php namespace Nlb\Library\Tests;

use Artisan;
use PluginTestCase;
use Tailor\Models\EntryRecord;
use Tailor\Models\StructureRecord;

class ImportTest extends PluginTestCase
{
    protected $useTransactions = true;

    protected $autoMigrateTailor = true;

    /**
     * sourcePath points at the trimmed fixture export of 1C-Bitrix data.
     */
    protected function sourcePath(): string
    {
        return dirname(__DIR__) . '/tests/fixtures/db/config/data';
    }

    protected function import(): int
    {
        return Artisan::call('nlb:import', ['--source' => $this->sourcePath()]);
    }

    public function testImportCreatesItemsAndSections()
    {
        $this->assertSame(0, $this->import());

        $this->assertSame(2, EntryRecord::inSection('Nlb\News')->count());
        $this->assertSame(1, EntryRecord::inSection('Nlb\Event')->count());
        $this->assertSame(1, EntryRecord::inSection('Nlb\Book')->count());
        $this->assertSame(1, EntryRecord::inSection('Nlb\Collection')->count());
        $this->assertSame(1, EntryRecord::inSection('Nlb\Service')->count());
        $this->assertSame(1, EntryRecord::inSection('Nlb\Printing')->count());

        $this->assertSame(2, StructureRecord::inSection('Nlb\NewsSection')->count());
        $this->assertSame(2, StructureRecord::inSection('Nlb\EventSection')->count());
        $this->assertSame(1, StructureRecord::inSection('Nlb\BookSection')->count());
    }

    public function testImportKeepsSectionRelation()
    {
        $this->import();

        $news = EntryRecord::inSection('Nlb\News')->where('xml_id', 'nlb-news-001')->first();

        $this->assertNotNull($news);
        $this->assertSame('library', $news->section->code);
        $this->assertSame('Новости библиотеки', $news->section->title);
    }

    public function testImportKeepsRepeaterFields()
    {
        $this->import();

        $service = EntryRecord::inSection('Nlb\Service')->where('xml_id', 'nlb-srv-001')->first();

        $this->assertNotNull($service);
        $this->assertSame(2, $service->features()->count());
        $this->assertSame('Высота 40 метров', $service->features[0]->title);
        $this->assertSame('10-й этаж здания библиотеки', $service->features[0]->description);
        $this->assertSame(1, (int) $service->features[0]->sort_order);

        $this->assertSame(2, $service->tariffs()->count());
        $this->assertSame('Для читателей', $service->tariffs[0]->title);
        $this->assertSame("Свободный доступ\nWi-Fi\nЗона отдыха", $service->tariffs[0]->includes);
        $this->assertFalse((bool) $service->tariffs[0]->popular);
        $this->assertTrue((bool) $service->tariffs[1]->popular);

        $this->assertSame(
            "Понедельник – Пятница: 10:00 – 22:00\nСуббота – Воскресенье: 09:00 – 23:00",
            $service->schedule
        );
    }

    public function testImportKeepsDatesAndScalarFields()
    {
        $this->import();

        $news = EntryRecord::inSection('Nlb\News')->where('xml_id', 'nlb-news-001')->first();
        $this->assertSame('2026-11-02 10:00', $news->published_at->format('Y-m-d H:i'));
        $this->assertSame(['digital', 'library'], $news->tags);
        $this->assertSame('Пресс-служба', $news->author);

        $event = EntryRecord::inSection('Nlb\Event')->where('xml_id', 'nlb-evt-001')->first();
        $this->assertSame('2026-11-06 18:30', $event->event_date->format('Y-m-d H:i'));
        $this->assertSame(40, (int) $event->seats);
        $this->assertSame('hybrid', $event->format);

        $printing = EntryRecord::inSection('Nlb\Printing')->where('xml_id', 'nlb-prn-001')->first();
        $this->assertSame(150, (int) $printing->price_from);
        $this->assertSame('за страницу', $printing->unit);
    }

    public function testSecondRunUpdatesWithoutCreatingDuplicates()
    {
        $this->import();
        $this->assertSame(0, $this->import());

        $this->assertSame(2, EntryRecord::inSection('Nlb\News')->count());
        $this->assertSame(1, EntryRecord::inSection('Nlb\Service')->where('xml_id', 'nlb-srv-001')->count());

        $service = EntryRecord::inSection('Nlb\Service')->where('xml_id', 'nlb-srv-001')->first();
        $this->assertSame(2, $service->features()->count());
        $this->assertSame(2, $service->tariffs()->count());
        $this->assertSame(2, StructureRecord::inSection('Nlb\NewsSection')->count());
    }

    public function testImportDoesNotDeleteManualRecords()
    {
        $this->import();

        $manual = EntryRecord::inSection('Nlb\News');
        $manual->title = 'Ручная новость';
        $manual->slug = 'ruchnaya-novost';
        $manual->is_enabled = true;
        $manual->save();

        $this->import();

        $this->assertSame(3, EntryRecord::inSection('Nlb\News')->count());
        $this->assertSame('Ручная новость', EntryRecord::inSection('Nlb\News')->where('slug', 'ruchnaya-novost')->first()->title);
    }

    public function testImportRestoresEditedValues()
    {
        $this->import();

        $news = EntryRecord::inSection('Nlb\News')->where('xml_id', 'nlb-news-001')->first();
        $news->title = 'Изменено вручную';
        $news->save();

        $this->import();

        $this->assertSame(
            'Цифровой читальный зал',
            EntryRecord::inSection('Nlb\News')->where('xml_id', 'nlb-news-001')->first()->title
        );
    }

    public function testImportFailsWithoutSourceDirectory()
    {
        $this->assertSame(1, Artisan::call('nlb:import', ['--source' => dirname(__DIR__) . '/tests/fixtures/missing']));
    }
}
