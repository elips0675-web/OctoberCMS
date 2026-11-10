<?php namespace Nlb\Library\Tests;

use Artisan;
use Nlb\Library\Models\Slot;
use PluginTestCase;

class SlotsGenerateTest extends PluginTestCase
{
    protected $useTransactions = true;

    protected $autoMigrateTailor = true;

    protected function importServices(): void
    {
        Artisan::call('nlb:import', [
            '--source' => dirname(__DIR__) . '/tests/fixtures/db/config/data',
        ]);
    }

    public function testGeneratesSlotsForEveryEnabledService()
    {
        $this->importServices();

        $this->assertSame(0, Artisan::call('nlb:slots-generate', [
            '--days' => 2,
            '--from' => '2026-12-01',
        ]));

        $this->assertSame(24, Slot::query()->count());
        $this->assertSame(12, Slot::query()->where('date', '2026-12-01')->count());
        $this->assertSame(12, Slot::query()->where('date', '2026-12-02')->count());
        $this->assertSame(2, Slot::query()->where('time', '10:00')->count());
        $this->assertSame(2, Slot::query()->where('time', '21:00')->count());
    }

    public function testSecondRunCreatesNoDuplicates()
    {
        $this->importServices();

        Artisan::call('nlb:slots-generate', ['--days' => 3, '--from' => '2026-12-01']);
        Artisan::call('nlb:slots-generate', ['--days' => 3, '--from' => '2026-12-01']);

        $this->assertSame(36, Slot::query()->count());
        $this->assertStringContainsString('0 создано', Artisan::output());
    }

    public function testCustomServiceAndTimes()
    {
        $this->importServices();

        $this->assertSame(0, Artisan::call('nlb:slots-generate', [
            '--days' => 1,
            '--from' => '2026-12-05',
            '--service' => ['sauna'],
            '--times' => '10:00,12:30,9:00',
        ]));

        $this->assertSame(3, Slot::query()->count());
        $this->assertSame(
            ['09:00', '10:00', '12:30'],
            Slot::query()->orderBy('time')->pluck('time')->all()
        );
        $this->assertSame('sauna', Slot::query()->first()->service_code);
    }

    public function testFailsWhenNoServicesExist()
    {
        $this->assertSame(1, Artisan::call('nlb:slots-generate', [
            '--days' => 1,
            '--from' => '2026-12-01',
        ]));
        $this->assertSame(0, Slot::query()->count());
    }

    public function testRejectsInvalidDate()
    {
        $this->expectException(\InvalidArgumentException::class);

        Artisan::call('nlb:slots-generate', [
            '--days' => 1,
            '--from' => '2026-13-40',
            '--service' => ['sauna'],
        ]);
    }

    public function testRejectsInvalidTimes()
    {
        $this->expectException(\InvalidArgumentException::class);

        Artisan::call('nlb:slots-generate', [
            '--days' => 1,
            '--from' => '2026-12-01',
            '--service' => ['sauna'],
            '--times' => '25:00,99:99',
        ]);
    }
}
