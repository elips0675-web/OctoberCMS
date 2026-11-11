<?php namespace Nlb\Library\Models;

use October\Rain\Database\Model;
use October\Rain\Database\Traits\Validation;

/**
 * Order — заказ сувенира из каталога.
 */
class Order extends Model
{
    use Validation;

    public $table = 'nlb_orders';

    protected $guarded = [];

    public $attributes = [
        'status' => 'new',
    ];

    public $rules = [
        'item' => 'required|string|max:191',
        'article' => 'nullable|string|max:64',
        'price' => 'required|integer|min:0',
        'qty' => 'required|integer|min:1|max:999',
        'person' => 'required|string|max:191',
        'phone' => 'required|string|max:32',
        'email' => 'nullable|email|max:191',
        'comment' => 'nullable|string|max:2000',
        'status' => 'in:new,processing,done,cancelled',
    ];

    public $customMessages = [
        'item.required' => 'Не выбран товар.',
        'price.required' => 'Не указана цена.',
        'price.min' => 'Цена не может быть отрицательной.',
        'qty.required' => 'Укажите количество.',
        'qty.min' => 'Количество должно быть не меньше 1.',
        'person.required' => 'Укажите имя.',
        'phone.required' => 'Укажите телефон.',
        'email.email' => 'Проверьте адрес электронной почты.',
    ];

    public const STATUS_NEW = 'new';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELLED = 'cancelled';

    protected static function boot()
    {
        parent::boot();

        static::saving(function (self $order) {
            if (in_array($order->status, [self::STATUS_DONE, self::STATUS_CANCELLED], true) && !$order->date_processed) {
                $order->date_processed = now();
            }
        });
    }

    public function total(): int
    {
        return (int) $this->price * (int) $this->qty;
    }
}
