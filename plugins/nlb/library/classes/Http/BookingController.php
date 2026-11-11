<?php namespace Nlb\Library\Classes\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Nlb\Library\Classes\BookingService;
use Nlb\Library\Classes\Mail\BookingConfirmation;
use Nlb\Library\Classes\Mail\BookingNotification;
use Nlb\Library\Classes\Mail\MailSettings;
use Nlb\Library\Classes\SlotUnavailableException;
use Nlb\Library\Models\Slot;

/**
 * BookingController — бронь услуги (P1-9, P1-20).
 *
 * Проверка «слот есть в окне генерации» — до записи: без неё `BookingService`
 * создаёт слот на любую дату, и бронь уходит в пустоту. Гонку закрывает
 * `UNIQUE (service_code, date, time)` в самой службе, здесь — только `409`.
 */
class BookingController extends FormController
{
    public function __construct(protected BookingService $bookings)
    {
    }

    protected function rules(): array
    {
        return [
            'SERVICE' => 'required|string|max:191',
            'SERVICE_CODE' => 'required|string|max:64',
            'DATE' => 'required|date_format:Y-m-d|after_or_equal:today',
            'TIME' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'PERSON' => 'required|string|max:191',
            'PHONE' => 'required|string|max:32',
            'EMAIL' => 'nullable|email|max:191',
            'COMMENT' => 'nullable|string|max:2000',
            'TARIFF' => 'nullable|string|max:191',
            'PRICE' => 'nullable|string|max:64',
        ];
    }

    protected function messages(): array
    {
        return [
            'SERVICE.required' => 'Выберите услугу.',
            'SERVICE_CODE.required' => 'Выберите услугу.',
            'DATE.required' => 'Выберите дату.',
            'DATE.date_format' => 'Дата должна быть в формате ГГГГ-ММ-ДД.',
            'DATE.after_or_equal' => 'Дата уже прошла — выберите другую.',
            'TIME.required' => 'Выберите время.',
            'TIME.regex' => 'Время должно быть в формате ЧЧ:ММ.',
            'PERSON.required' => 'Укажите имя.',
            'PHONE.required' => 'Укажите телефон.',
            'EMAIL.email' => 'Проверьте адрес электронной почты.',
        ];
    }

    protected function handle(array $data, Request $request)
    {
        $slot = Slot::query()
            ->where('service_code', (string) $data['SERVICE_CODE'])
            ->whereDate('date', (string) $data['DATE'])
            ->where('time', (string) $data['TIME'])
            ->first();

        if (!$slot) {
            return $this->invalid($request, [
                'TIME' => ['Выберите время из доступных на эту дату.'],
            ]);
        }

        try {
            $this->bookings->book([
                'service' => $data['SERVICE'],
                'service_code' => $data['SERVICE_CODE'],
                'tariff' => $data['TARIFF'] ?? null,
                'price' => $data['PRICE'] ?? null,
                'date' => $data['DATE'],
                'time' => $data['TIME'],
                'person' => $data['PERSON'],
                'phone' => $data['PHONE'],
                'email' => $data['EMAIL'] ?? null,
                'comment' => $data['COMMENT'] ?? null,
                'source' => 'booking',
                'created_by' => $data['NAME'] ?? $data['PERSON'],
            ]);
        }
        catch (SlotUnavailableException $e) {
            return $this->conflict($request, 'Это время уже занято — выберите другое.');
        }

        Mail::queue(
            (new BookingNotification($data))->to(MailSettings::adminAddress())
        );

        if (!empty($data['EMAIL'])) {
            Mail::queue(
                (new BookingConfirmation($data))->to($data['EMAIL'])
            );
        }

        return null;
    }

    protected function fallbackPath(): string
    {
        return '/services/';
    }

    protected function anchor(): string
    {
        return 'booking';
    }
}
