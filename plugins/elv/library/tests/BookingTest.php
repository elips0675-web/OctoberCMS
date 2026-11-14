<?php namespace Elv\Library\Tests;

use Illuminate\Database\QueryException;
use Elv\Library\Classes\BookingService;
use Elv\Library\Classes\SlotUnavailableException;
use Elv\Library\Models\Booking;
use Elv\Library\Models\Slot;
use October\Rain\Database\ModelException;
use PluginTestCase;

class BookingTest extends PluginTestCase
{
    protected $useTransactions = true;

    protected function date(): string
    {
        return now()->addDay()->toDateString();
    }

    /**
     * @return array<string, string>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'service' => 'Сауна',
            'service_code' => 'sauna',
            'tariff' => 'Стандарт',
            'price' => '500',
            'date' => $this->date(),
            'time' => '10:00',
            'person' => 'Иван Петров',
            'phone' => '+375291112233',
            'email' => 'ivan@example.com',
        ], $overrides);
    }

    protected function makeSlot(array $overrides = []): Slot
    {
        return Slot::create(array_merge([
            'service_code' => 'sauna',
            'date' => $this->date(),
            'time' => '10:00',
            'capacity' => 1,
            'taken' => 0,
        ], $overrides));
    }

    public function testBookingCreatesRecordAndTakesSlot()
    {
        $slot = $this->makeSlot();

        $booking = (new BookingService())->book($this->payload());

        $this->assertSame(Booking::STATUS_NEW, $booking->status);
        $this->assertDatabaseHas('elv_bookings', [
            'id' => $booking->id,
            'service_code' => 'sauna',
            'date' => $this->date(),
            'time' => '10:00',
        ]);

        $slot->refresh();
        $this->assertSame(1, (int) $slot->taken);
        $this->assertSame($booking->id, (int) $slot->booking_id);
        $this->assertSame([], (new BookingService())->availableTimes('sauna', $this->date()));
    }

    public function testSecondBookingOnSameSlotIsRejected()
    {
        $this->makeSlot();
        $service = new BookingService();
        $service->book($this->payload());

        try {
            $service->book($this->payload(['person' => 'Пётр Сидоров']));
            $this->fail('Вторая бронь на занятый слот не была отклонена.');
        }
        catch (SlotUnavailableException $e) {
            $this->assertSame('Выбранное время уже занято.', $e->getMessage());
        }

        $this->assertSame(1, Booking::query()->count());
        $this->assertSame(1, (int) Slot::query()->first()->taken);
    }

    public function testUniqueIndexBlocksDuplicateSlotRows()
    {
        $this->makeSlot();

        try {
            $this->makeSlot();
            $this->fail('UNIQUE(service_code, date, time) не сработал.');
        }
        catch (QueryException $e) {
            $this->assertSame('23000', (string) $e->getCode());
        }

        $this->assertSame(1, Slot::query()->count());
    }

    public function testMissingSlotIsCreatedOnDemand()
    {
        $booking = (new BookingService())->book($this->payload());

        $this->assertSame(1, Slot::query()->count());
        $this->assertSame(1, (int) Slot::query()->first()->taken);
        $this->assertSame($booking->id, (int) Slot::query()->first()->booking_id);
    }

    public function testSlotCapacityAllowsSeveralBookings()
    {
        $this->makeSlot(['capacity' => 2]);
        $service = new BookingService();

        $service->book($this->payload());
        $service->book($this->payload(['person' => 'Пётр Сидоров']));

        $this->assertSame(2, Booking::query()->count());
        $this->assertSame(2, (int) Slot::query()->first()->taken);

        $this->expectException(SlotUnavailableException::class);
        $service->book($this->payload(['person' => 'Третий гость']));
    }

    public function testCancelReleasesSlotAndSecondBookingSucceeds()
    {
        $this->makeSlot();
        $service = new BookingService();
        $booking = $service->book($this->payload());

        $service->cancel($booking);

        $slot = Slot::query()->first();
        $this->assertSame(0, (int) $slot->taken);
        $this->assertNull($slot->booking_id);
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
        $this->assertSame(['10:00'], $service->availableTimes('sauna', $this->date()));

        $second = $service->book($this->payload(['person' => 'Пётр Сидоров']));
        $this->assertSame(Booking::STATUS_NEW, $second->status);
        $this->assertSame(1, (int) $slot->fresh()->taken);
    }

    public function testCancelIsIdempotent()
    {
        $this->makeSlot();
        $service = new BookingService();
        $booking = $service->book($this->payload());

        $service->cancel($booking);
        $service->cancel($booking);

        $this->assertSame(0, (int) Slot::query()->first()->taken);
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
    }

    public function testConfirmChangesStatusButNotSlot()
    {
        $this->makeSlot();
        $service = new BookingService();
        $booking = $service->book($this->payload());

        $confirmed = $service->confirm($booking);

        $this->assertSame(Booking::STATUS_CONFIRMED, $confirmed->status);
        $this->assertSame(1, (int) Slot::query()->first()->taken);
    }

    public function testInvalidTimeIsRejectedBeforeWriting()
    {
        $this->makeSlot();

        try {
            (new BookingService())->book($this->payload(['time' => '25:00']));
            $this->fail('Неверное время прошло валидацию.');
        }
        catch (ModelException $e) {
            $this->assertArrayHasKey('time', $e->getErrors()->toArray());
        }

        $this->assertSame(0, Booking::query()->count());
        $this->assertSame(1, Slot::query()->count());
        $this->assertSame(0, (int) Slot::query()->first()->taken);
    }

    public function testNormalizesTimeWithoutLeadingZero()
    {
        $this->makeSlot(['time' => '09:00']);

        $booking = (new BookingService())->book($this->payload(['time' => '9:00']));

        $this->assertSame('09:00', $booking->time);
    }
}
