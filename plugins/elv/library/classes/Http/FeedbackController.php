<?php namespace Elv\Library\Classes\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Elv\Library\Classes\FormFields;
use Elv\Library\Classes\Mail\FeedbackNotification;
use Elv\Library\Classes\Mail\MailSettings;
use Elv\Library\Models\Request as RequestModel;

/**
 * FeedbackController — форма обратной связи (P0-4).
 *
 * Источник не проверял доставку письма: здесь строка в `elv_requests` и письмо
 * в очередь, а `MAIL_MAILER=log` показывает его в логе без SMTP.
 */
class FeedbackController extends FormController
{
    protected function rules(): array
    {
        return [
            'EMAIL' => 'required_without:PHONE|nullable|email|max:191',
            'PHONE' => 'required_without:EMAIL|nullable|string|max:32',
            'NAME' => 'nullable|string|max:191',
            'SUBJECT' => 'nullable|string|max:255',
            'MESSAGE' => 'required|string|max:5000',
        ];
    }

    protected function messages(): array
    {
        return [
            'EMAIL.required_without' => 'Укажите e-mail или телефон.',
            'EMAIL.email' => 'Проверьте адрес электронной почты.',
            'PHONE.required_without' => 'Укажите телефон или e-mail.',
            'MESSAGE.required' => 'Напишите сообщение.',
            'MESSAGE.max' => 'Сообщение длиннее 5000 знаков — сократите текст.',
        ];
    }

    protected function handle(array $data, Request $request): void
    {
        $message = (string) $data['MESSAGE'];

        $model = new RequestModel();
        $model->fill([
            'email' => $data['EMAIL'] ?? null,
            'phone' => $data['PHONE'] ?? null,
            'subject' => $data['SUBJECT'] ?? mb_substr($message, 0, 60),
            'message' => $message,
            'page' => FormFields::page($request),
            'source' => 'feedback',
            'created_by' => $data['NAME'] ?? null,
            'status' => RequestModel::STATUS_NEW,
        ]);
        $model->save();

        Mail::queue(
            (new FeedbackNotification($data))->to(MailSettings::adminAddress())
        );
    }

    protected function fallbackPath(): string
    {
        return '/feedback/';
    }

    protected function anchor(): string
    {
        return 'form';
    }
}
