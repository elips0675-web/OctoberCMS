<?php namespace Nlb\Library\Models;

use October\Rain\Database\Model;
use October\Rain\Database\Traits\Validation;

/**
 * Request — обращение через форму обратной связи.
 */
class Request extends Model
{
    use Validation;

    public $table = 'nlb_requests';

    protected $guarded = [];

    public $attributes = [
        'status' => 'new',
    ];

    public $rules = [
        'subject' => 'required|string|max:255',
        'message' => 'required|string|max:5000',
        'email' => 'required_without:phone|nullable|email|max:191',
        'phone' => 'required_without:email|nullable|string|max:32',
        'page' => 'nullable|string|max:255',
        'source' => 'nullable|string|max:64',
        'status' => 'in:new,in_progress,done',
    ];

    public $customMessages = [
        'subject.required' => 'Укажите тему обращения.',
        'message.required' => 'Напишите сообщение.',
        'email.required_without' => 'Укажите e-mail или телефон.',
        'phone.required_without' => 'Укажите телефон или e-mail.',
        'email.email' => 'Проверьте адрес электронной почты.',
    ];

    public const STATUS_NEW = 'new';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE = 'done';

    public function markProcessed(): void
    {
        $this->status = self::STATUS_DONE;
        $this->date_processed = now();
        $this->save();
    }
}
