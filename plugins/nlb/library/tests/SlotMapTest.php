<?php namespace Nlb\Library\Tests;

use Nlb\Library\Models\Slot;
use PluginTestCase;

/**
 * SlotMapTest — карта занятых часов для формы брони.
 *
 * Форма получает JSON `{"YYYY-MM-DD": ["10:00"]}` и блокирует эти часы.
 * В карту попадают только слоты без свободных мест: частично занятый
 * слот (capacity 2, taken 1) остаётся доступным.
 */
class SlotMapTest extends PluginTestCase
{
    protected $useTransactions = true;

    protected function date(int $days = 1): string
    {
        return now()->addDays($days)->toDateString();
    }

    protected function makeSlot(array $overrides = []): Slot
    {
        return Slot::create(array_merge([
            'service_code' => 'cafe',
            'date' => $this->date(),
            'time' => '10:00',
            'capacity' => 1,
            'taken' => 0,
        ], $overrides));
    }

    public function testMapContainsOnlyFullSlots()
    {
        $this->makeSlot(['time' => '10:00', 'taken' => 0]);
        $this->makeSlot(['time' => '11:00', 'taken' => 1]);
        $this->makeSlot(['time' => '12:00', 'capacity' => 2, 'taken' => 1]);

        $map = Slot::occupiedMap('cafe');

        $this->assertSame([$this->date() => ['11:00']], $map);
    }

    public function testMapFiltersByService()
    {
        $this->makeSlot(['service_code' => 'cafe', 'taken' => 1]);
        $this->makeSlot(['service_code' => 'gym', 'time' => '11:00', 'taken' => 1]);

        $this->assertSame([$this->date() => ['10:00']], Slot::occupiedMap('cafe'));
    }

    public function testMapSkipsPastAndFarDates()
    {
        $this->makeSlot(['date' => now()->subDay()->toDateString(), 'taken' => 1]);
        $this->makeSlot(['date' => $this->date(90), 'taken' => 1]);

        $this->assertSame([], Slot::occupiedMap('cafe', null, 30));
    }

    public function testMapStartsFromGivenDate()
    {
        $this->makeSlot(['date' => $this->date(1), 'taken' => 1]);
        $this->makeSlot(['date' => $this->date(10), 'taken' => 1]);

        $map = Slot::occupiedMap('cafe', $this->date(5), 30);

        $this->assertSame([$this->date(10) => ['10:00']], $map);
    }

    public function testJsonMapIsValidJson()
    {
        $this->assertSame('{}', Slot::jsonOccupiedMap('cafe'));

        $this->makeSlot(['taken' => 1]);

        $json = Slot::jsonOccupiedMap('cafe');
        $decoded = json_decode($json, true);

        $this->assertSame([$this->date() => ['10:00']], $decoded);
        $this->assertStringNotContainsString('\u', $json);
    }

    public function testScopeAvailable()
    {
        $this->makeSlot(['time' => '10:00', 'taken' => 0]);
        $this->makeSlot(['time' => '11:00', 'taken' => 1]);

        $times = Slot::query()->forService('cafe')->available()->pluck('time')->all();

        $this->assertSame(['10:00'], $times);
    }
}
