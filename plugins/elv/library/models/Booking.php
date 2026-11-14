<?php namespace Elv\Library\Models;

use October\Rain\Database\Model;
use October\Rain\Database\Traits\Validation;
use October\Rain\Exception\ApplicationException;
use Elv\Library\Classes\BookingService;

/**
 * Booking — бронь услуги на дату и время.
 */
class Booking extends Model
{
    use Validation;

    public $table = 'elv_bookings';

    protected $guarded = [];

    public $attributes = [
        'status' => 'new',
    ];

    public $rules = [
        'service' => 'required|string|max:191',
        'service_code' => 'required|string|max:64',
        'tariff' => 'nullable|string|max:191',
        'price' => 'nullable|string|max:64',
        'date' => 'required|date_format:Y-m-d',
        'time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
        'person' => 'required|string|max:191',
        'phone' => 'required|string|max:32',
        'email' => 'nullable|email|max:191',
        'comment' => 'nullable|string|max:2000',
        'status' => 'in:new,confirmed,cancelled',
    ];

    public $customMessages = [
        'service.required' => 'Выберите услугу.',
        'date.required' => 'Укажите дату.',
        'date.date_format' => 'Дата должна быть в формате ГГГГ-ММ-ДД.',
        'time.required' => 'Выберите время.',
        'time.regex' => 'Время должно быть в формате ЧЧ:ММ.',
        'person.required' => 'Укажите имя.',
        'phone.required' => 'Укажите телефон.',
        'email.email' => 'Проверьте адрес электронной почты.',
    ];

    public const STATUS_NEW = 'new';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';

    protected static function boot()
    {
        parent::boot();

        static::updating(function (self $booking) {
            if (!$booking->isDirty('status')) {
                return;
            }

            if ($booking->status === self::STATUS_CANCELLED) {
                app(BookingService::class)->releaseSlot($booking);
                $booking->date_processed = $booking->date_processed ?: now();

                return;
            }

            if ($booking->getOriginal('status') === self::STATUS_CANCELLED) {
                throw new ApplicationException('Отменённую бронь нельзя вернуть в работу — создайте новую.');
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('status', '!=', self::STATUS_CANCELLED);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isActive(): bool
    {
        return !$this->isCancelled();
    }
}
