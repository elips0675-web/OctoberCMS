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
}
