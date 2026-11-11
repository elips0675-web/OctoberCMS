<?php namespace Nlb\Library\Models;

use October\Rain\Database\Model;
use October\Rain\Database\Traits\Validation;

/**
 * Slot — доступное время услуги.
 *
 * Уникальный индекс (service_code, date, time) объявлен в миграции:
 * именно он, а не проверка «прочитал-записал», закрывает гонку брони.
 */
class Slot extends Model
{
    use Validation;

    public $table = 'nlb_slots';

    protected $guarded = [];

    public $rules = [
        'service_code' => 'required|string|max:64',
        'date' => 'required|date_format:Y-m-d',
        'time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
        'capacity' => 'required|integer|min:1',
        'taken' => 'required|integer|min:0',
    ];

    public $casts = [
        'capacity' => 'integer',
        'taken' => 'integer',
    ];

    public function isAvailable(): bool
    {
        return (int) $this->taken < (int) $this->capacity;
    }

    public function freeSeats(): int
    {
        return max(0, (int) $this->capacity - (int) $this->taken);
    }

    public function scopeForService($query, string $serviceCode)
    {
        return $query->where('service_code', $serviceCode);
    }

    public function scopeOnDate($query, string $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeAvailable($query)
    {
        return $query->whereColumn('taken', '<', 'capacity');
    }

    /**
     * occupiedMap возвращает занятые часы по датам для формы брони.
     *
     * Формат совпадает с данными формы: {"2026-11-11": ["10:00", "11:00"]}.
     * В карту попадают только часы без свободных мест, чтобы скрипт формы
     * блокировал их, а остальные оставлял доступными.
     *
     * @return array<string, array<int, string>>
     */
    public static function occupiedMap(string $serviceCode, ?string $from = null, int $days = 60): array
    {
        $start = $from ?: date('Y-m-d');
        $until = date('Y-m-d', strtotime($start . ' +' . max(1, $days) . ' days'));

        $rows = static::query()
            ->forService($serviceCode)
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $until)
            ->orderBy('date')
            ->orderBy('time')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            if ($row->isAvailable()) {
                continue;
            }

            $day = substr((string) $row->date, 0, 10);
            $time = substr((string) $row->time, 0, 5);
            if ($day === '' || $time === '') {
                continue;
            }

            $map[$day][] = $time;
        }

        return $map;
    }

    /**
     * jsonOccupiedMap отдаёт карту занятых часов строкой для атрибута формы.
     */
    public static function jsonOccupiedMap(string $serviceCode, int $days = 60): string
    {
        $map = static::occupiedMap($serviceCode, null, $days);
        if ($map === []) {
            return '{}';
        }

        return (string) json_encode($map, JSON_UNESCAPED_UNICODE);
    }
}
