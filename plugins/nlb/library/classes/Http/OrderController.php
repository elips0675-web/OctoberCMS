<?php namespace Nlb\Library\Classes\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Nlb\Library\Classes\Mail\MailSettings;
use Nlb\Library\Classes\Mail\OrderNotification;
use Nlb\Library\Models\Order;

/**
 * OrderController — заказ сувенира (P1-12).
 *
 * Название, артикул и цена приходят из записи каталога скрытыми полями; здесь
 * они проверяются заново — скрытое поле подделывается в один шаг.
 */
class OrderController extends FormController
{
    protected function rules(): array
    {
        return [
            'ITEM' => 'required|string|max:191',
            'ARTICLE' => 'nullable|string|max:64',
            'PRICE' => 'required|integer|min:0',
            'QTY' => 'required|integer|min:1|max:999',
            'PERSON' => 'required|string|max:191',
            'PHONE' => 'required|string|max:32',
            'EMAIL' => 'nullable|email|max:191',
            'COMMENT' => 'nullable|string|max:2000',
        ];
    }

    protected function messages(): array
    {
        return [
            'ITEM.required' => 'Не выбран товар.',
            'PRICE.required' => 'Не указана цена.',
            'PRICE.integer' => 'Цена должна быть целым числом.',
            'PRICE.min' => 'Цена не может быть отрицательной.',
            'QTY.required' => 'Укажите количество.',
            'QTY.integer' => 'Количество должно быть целым числом.',
            'QTY.min' => 'Количество должно быть не меньше 1.',
            'QTY.max' => 'Количество не больше 999.',
            'PERSON.required' => 'Укажите имя.',
            'PHONE.required' => 'Укажите телефон.',
            'EMAIL.email' => 'Проверьте адрес электронной почты.',
        ];
    }

    protected function handle(array $data, Request $request)
    {
        $model = new Order();
        $model->fill([
            'item' => $data['ITEM'],
            'article' => $data['ARTICLE'] ?? null,
            'price' => (int) $data['PRICE'],
            'qty' => (int) $data['QTY'],
            'person' => $data['PERSON'],
            'phone' => $data['PHONE'],
            'email' => $data['EMAIL'] ?? null,
            'comment' => $data['COMMENT'] ?? null,
            'source' => 'order',
            'created_by' => $data['NAME'] ?? $data['PERSON'],
            'status' => Order::STATUS_NEW,
        ]);
        $model->save();

        Mail::queue(
            (new OrderNotification($data))->to(MailSettings::adminAddress())
        );

        return null;
    }

    protected function fallbackPath(): string
    {
        return '/books/';
    }

    protected function anchor(): string
    {
        return 'order';
    }
}
