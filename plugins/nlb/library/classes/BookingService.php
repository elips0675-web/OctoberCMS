<?php namespace Nlb\Library\Classes;

use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Nlb\Library\Models\Booking;
use Nlb\Library\Models\Slot;

/**
 * BookingService — создание, подтверждение и отмена брони.
 *
 * Гонка слота закрывается двумя механизмами сразу:
 *  - UNIQUE (service_code, date, time) в миграции nlb_slots;
 *  - DB::transaction + lockForUpdate + условный UPDATE taken < capacity.
 * Проверка «прочитал и записал» без них — дефект, а не реализация (P1-20).
 */
class BookingService
{
    /**
     * book создаёт бронь и занимает слот в одной транзакции.
     *
     * @throws SlotUnavailableException
     */
    public function book(array $attributes): Booking
    {
        $attributes = $this->normalize($attributes);

        return DB::transaction(function () use ($attributes) {
            $slot = $this->lockSlot(
                (string) $attributes['service_code'],
                (string) $attributes['date'],
                (string) $attributes['time']
            );

            if (!$slot->isAvailable()) {
                throw new SlotUnavailableException();
            }

            $booking = new Booking();
            $booking->fill($attributes);
            $booking->status = Booking::STATUS_NEW;
            $booking->save();

            $claimed = Slot::query()
                ->whereKey($slot->getKey())
                ->whereColumn('taken', '<', 'capacity')
                ->update([
                    'taken' => DB::raw('taken + 1'),
                    'booking_id' => $booking->getKey(),
                    'updated_at' => now(),
                ]);

            if ($claimed !== 1) {
                throw new SlotUnavailableException();
            }

            return $booking;
        });
    }

    /**
     * confirm переводит бронь в статус «подтверждена».
     */
    public function confirm(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $fresh = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            if ($fresh->isCancelled()) {
                throw new SlotUnavailableException('Отменённую бронь нельзя подтвердить.');
            }

            $fresh->status = Booking::STATUS_CONFIRMED;
            $fresh->save();

            return $fresh;
        });
    }

    /**
     * cancel отменяет бронь и освобождает слот. Повторный вызов безопасен.
     */
    public function cancel(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $fresh = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            if ($fresh->isCancelled()) {
                return $fresh;
            }

            $this->releaseSlot($fresh);

            $fresh->status = Booking::STATUS_CANCELLED;
            $fresh->date_processed = now();
            $fresh->saveQuietly();

            return $fresh;
        });
    }

    /**
     * releaseSlot освобождает слот брони.
     *
     * Вызывается и из BookingService::cancel, и из хука модели Booking, поэтому
     * done молча: слот ищется по услуге, дате и времени, taken не уходит ниже нуля.
     */
    public function releaseSlot(Booking $booking): void
    {
        $slot = Slot::query()
            ->where('service_code', (string) $booking->service_code)
            ->whereDate('date', $this->dateToString($booking->date))
            ->where('time', (string) $booking->time)
            ->lockForUpdate()
            ->first();

        if (!$slot) {
            return;
        }

        $slot->taken = max(0, (int) $slot->taken - 1);
        if ((int) $slot->booking_id === (int) $booking->getKey()) {
            $slot->booking_id = null;
        }
        $slot->save();
    }

    /**
     * availableTimes возвращает свободные часы услуги на дату.
     *
     * @return string[]
     */
    public function availableTimes(string $serviceCode, string $date): array
    {
        return Slot::query()
            ->where('service_code', $serviceCode)
            ->whereDate('date', $date)
            ->whereColumn('taken', '<', 'capacity')
            ->orderBy('time')
            ->pluck('time')
            ->all();
    }

    /**
     * lockSlot находит слот под блокировкой или создаёт его.
     *
     * @throws SlotUnavailableException
     */
    protected function lockSlot(string $serviceCode, string $date, string $time): Slot
    {
        $slot = $this->findForUpdate($serviceCode, $date, $time);
        if ($slot) {
            return $slot;
        }

        try {
            return Slot::create([
                'service_code' => $serviceCode,
                'date' => $date,
                'time' => $time,
                'capacity' => 1,
                'taken' => 0,
            ]);
        }
        catch (QueryException $e) {
            if (!$this->isDuplicate($e)) {
                throw $e;
            }

            $slot = $this->findForUpdate($serviceCode, $date, $time);
            if (!$slot) {
                throw new SlotUnavailableException();
            }

            return $slot;
        }
    }

    protected function findForUpdate(string $serviceCode, string $date, string $time): ?Slot
    {
        return Slot::query()
            ->where('service_code', $serviceCode)
            ->whereDate('date', $date)
            ->where('time', $time)
            ->lockForUpdate()
            ->first();
    }

    /**
     * isDuplicate отличает нарушение UNIQUE от прочих ошибок SQL.
     */
    protected function isDuplicate(QueryException $e): bool
    {
        if ((string) $e->getCode() !== '23000') {
            return false;
        }

        $message = $e->getMessage();

        return str_contains($message, '1062')
            || str_contains($message, 'UNIQUE constraint failed')
            || str_contains($message, 'Duplicate entry');
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalize(array $attributes): array
    {
        $attributes['service_code'] = trim((string) ($attributes['service_code'] ?? ''));
        $attributes['date'] = $this->dateToString($attributes['date'] ?? null);
        $attributes['time'] = $this->timeToString($attributes['time'] ?? null);

        foreach (['service', 'tariff', 'price', 'person', 'phone', 'email', 'comment', 'source', 'created_by'] as $field) {
            if (array_key_exists($field, $attributes) && $attributes[$field] !== null) {
                $attributes[$field] = trim((string) $attributes[$field]);
            }
        }

        return $attributes;
    }

    protected function dateToString($value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return trim((string) $value);
    }

    protected function timeToString($value): string
    {
        $value = trim((string) $value);
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $value, $matches)) {
            return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
        }

        return $value;
    }
}
