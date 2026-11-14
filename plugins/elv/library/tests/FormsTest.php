<?php namespace Elv\Library\Tests;

use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Mail;
use Elv\Library\Classes\BookingService;
use Elv\Library\Classes\FormFields;
use Elv\Library\Classes\Http\BookingController;
use Elv\Library\Classes\Http\FeedbackController;
use Elv\Library\Classes\Http\OrderController;
use Elv\Library\Classes\Http\SubscriberController;
use Elv\Library\Classes\Mail\BookingConfirmation;
use Elv\Library\Classes\Mail\BookingNotification;
use Elv\Library\Classes\Mail\FeedbackNotification;
use Elv\Library\Classes\Mail\OrderNotification;
use Elv\Library\Classes\Mail\SubscriptionConfirmation;
use Elv\Library\Models\Booking;
use Elv\Library\Models\Order;
use Elv\Library\Models\Request as RequestModel;
use Elv\Library\Models\Slot;
use Elv\Library\Models\Subscriber;
use PluginTestCase;

/**
 * FormsTest — публичные формы: обратная связь, подписка, бронь, заказ.
 *
 * Проверяется то, что видит посетитель и что попадает в базу: код ответа,
 * строка записи, письмо в очереди. Шаблоны писем рендерятся отдельным тестом —
 * иначе ошибка в Twig всплывает только на живом письме.
 */
class FormsTest extends PluginTestCase
{
    protected $useTransactions = true;

    protected function date(): string
    {
        return now()->addDay()->toDateString();
    }

    /**
     * jsonRequest собирает запрос так, как его разбирает PHP из формы Битрикса.
     *
     * @param  array<string, mixed>  $data
     */
    protected function jsonRequest(string $url, array $data): HttpRequest
    {
        $request = HttpRequest::create($url, 'POST', $data);
        $request->headers->set('Accept', 'application/json');
        $request->headers->set('Referer', url('/feedback/'));

        return $request;
    }

    /**
     * htmlRequest — тот же POST без `Accept: application/json`.
     *
     * @param  array<string, mixed>  $data
     */
    protected function htmlRequest(string $url, array $data): HttpRequest
    {
        $request = HttpRequest::create($url, 'POST', $data);
        $request->headers->set('Referer', url('/feedback/'));

        return $request;
    }

    /**
     * feedbackPayload — обращение формы `/feedback/`.
     *
     * @return array<string, mixed>
     */
    protected function feedbackPayload(array $overrides = []): array
    {
        return array_merge([
            'CONSENT' => 'Y',
            'NAME' => 'Иван Петров',
            'PROPERTY' => [
                'EMAIL' => ['0' => 'ivan@example.by'],
                'PHONE' => ['0' => '+375291112233'],
                'SUBJECT' => ['0' => 'Про фонды'],
                'MESSAGE' => ['0' => 'Подскажите, есть ли книга в наличии.'],
            ],
        ], $overrides);
    }

    /**
     * bookingPayload — бронь услуги.
     *
     * @return array<string, mixed>
     */
    protected function bookingPayload(array $overrides = []): array
    {
        $properties = array_merge([
            'SERVICE' => ['0' => 'Сауна'],
            'SERVICE_CODE' => ['0' => 'sauna'],
            'TARIFF' => ['0' => 'Стандарт'],
            'PRICE' => ['0' => '500'],
            'DATE' => ['0' => $this->date()],
            'TIME' => ['0' => '10:00'],
            'PERSON' => ['0' => 'Иван Петров'],
            'PHONE' => ['0' => '+375291112233'],
            'EMAIL' => ['0' => 'ivan@example.by'],
        ], $overrides['PROPERTY'] ?? []);

        unset($overrides['PROPERTY']);

        return array_merge([
            'CONSENT' => 'Y',
            'PROPERTY' => $properties,
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    protected function orderPayload(array $overrides = []): array
    {
        $properties = array_merge([
            'ITEM' => ['0' => 'Шоппер «Библиотека»'],
            'ARTICLE' => ['0' => '978-985-00-0000-0'],
            'PRICE' => ['0' => '25'],
            'QTY' => ['0' => '2'],
            'PERSON' => ['0' => 'Иван Петров'],
            'PHONE' => ['0' => '+375291112233'],
            'EMAIL' => ['0' => 'ivan@example.by'],
        ], $overrides['PROPERTY'] ?? []);

        unset($overrides['PROPERTY']);

        return array_merge([
            'CONSENT' => 'Y',
            'PROPERTY' => $properties,
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

    public function testFormFieldsFlattensBitrixNames()
    {
        $fields = FormFields::normalize([
            'NAME' => 'Иван',
            'PROPERTY' => [
                'EMAIL' => ['0' => ' ivan@example.by '],
                'QTY' => ['0' => '2'],
                'SERVICE' => ['0' => ''],
            ],
        ]);

        $this->assertSame('Иван', $fields['NAME']);
        $this->assertSame('ivan@example.by', $fields['EMAIL']);
        $this->assertSame('2', $fields['QTY']);
        $this->assertArrayNotHasKey('SERVICE', $fields);
        $this->assertArrayNotHasKey('PROPERTY', $fields);
    }

    public function testConsentAcceptsOnlyTruthyValues()
    {
        $this->assertTrue(FormFields::consentGiven(['CONSENT' => 'Y']));
        $this->assertTrue(FormFields::consentGiven(['CONSENT' => 'on']));
        $this->assertTrue(FormFields::consentGiven(['CONSENT' => '1']));
        $this->assertFalse(FormFields::consentGiven(['CONSENT' => '']));
        $this->assertFalse(FormFields::consentGiven(['CONSENT' => 'N']));
        $this->assertFalse(FormFields::consentGiven([]));
    }

    public function testFeedbackStoresRequestAndQueuesStaffMail()
    {
        Mail::fake();

        $response = (new FeedbackController())->store(
            $this->jsonRequest('/feedback', $this->feedbackPayload())
        );

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(1, RequestModel::query()->count());

        $row = RequestModel::query()->first();
        $this->assertSame('ivan@example.by', $row->email);
        $this->assertSame('Про фонды', $row->subject);
        $this->assertSame('feedback', $row->source);
        $this->assertSame('Иван Петров', $row->created_by);
        $this->assertSame(RequestModel::STATUS_NEW, $row->status);

        Mail::assertQueued(FeedbackNotification::class);
    }

    public function testFeedbackFallsBackToMessageWhenSubjectIsEmpty()
    {
        Mail::fake();

        $payload = $this->feedbackPayload();
        $payload['PROPERTY']['SUBJECT'] = ['0' => ''];
        $payload['PROPERTY']['MESSAGE'] = ['0' => 'Короткий вопрос'];

        (new FeedbackController())->store($this->jsonRequest('/feedback', $payload));

        $this->assertSame('Короткий вопрос', RequestModel::query()->first()->subject);
    }

    public function testFeedbackRejectsSubmissionWithoutConsent()
    {
        Mail::fake();

        $payload = $this->feedbackPayload();
        unset($payload['CONSENT']);

        $response = (new FeedbackController())->store($this->jsonRequest('/feedback', $payload));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(0, RequestModel::query()->count());
        Mail::assertNothingQueued();
    }

    public function testFeedbackRejectsEmptyMessageWithHumanText()
    {
        Mail::fake();

        $payload = $this->feedbackPayload();
        $payload['PROPERTY']['MESSAGE'] = ['0' => ''];

        $response = (new FeedbackController())->store($this->jsonRequest('/feedback', $payload));
        $body = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('Напишите сообщение.', $body['errors']['MESSAGE'][0]);
        $this->assertSame(0, RequestModel::query()->count());
    }

    public function testFeedbackRequiresEmailOrPhone()
    {
        Mail::fake();

        $payload = $this->feedbackPayload();
        unset($payload['PROPERTY']['EMAIL'], $payload['PROPERTY']['PHONE']);

        $response = (new FeedbackController())->store($this->jsonRequest('/feedback', $payload));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(0, RequestModel::query()->count());
    }

    public function testFeedbackRedirectsBrowserBackToForm()
    {
        Mail::fake();

        $response = (new FeedbackController())->store(
            $this->htmlRequest('/feedback', $this->feedbackPayload())
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/feedback?sent=1', $response->headers->get('Location'));
        $this->assertStringEndsWith('#form', $response->headers->get('Location'));
    }

    public function testBookingRedirectsBackToTheServicePage()
    {
        Mail::fake();
        $this->makeSlot();

        $request = $this->htmlRequest('/booking', $this->bookingPayload());
        $request->headers->set('Referer', url('/services/sauna/'));

        $response = (new BookingController(new BookingService()))->store($request);
        $location = $response->headers->get('Location');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/services/sauna?sent=1', $location);
        $this->assertStringEndsWith('#booking', $location);
    }

    public function testForeignRefererFallsBackToOwnPage()
    {
        Mail::fake();

        $request = $this->htmlRequest('/feedback', $this->feedbackPayload());
        $request->headers->set('Referer', 'https://evil.example/steal');

        $response = (new FeedbackController())->store($request);
        $location = $response->headers->get('Location');

        $this->assertStringNotContainsString('evil.example', $location);
        $this->assertStringContainsString('/feedback/?sent=1', $location);
    }

    public function testSubscribeQueuesConfirmationAndKeepsAddressUnconfirmed()
    {
        Mail::fake();

        $response = (new SubscriberController())->store(
            $this->jsonRequest('/subscribe', ['EMAIL' => ' Sub@Example.BY '])
        );

        $this->assertSame(201, $response->getStatusCode());

        $subscriber = Subscriber::query()->first();
        $this->assertSame('sub@example.by', $subscriber->email);
        $this->assertNull($subscriber->confirmed_at);
        $this->assertSame(48, mb_strlen($subscriber->token));

        Mail::assertQueued(SubscriptionConfirmation::class);
    }

    public function testSubscribeDoesNotRenotifyConfirmedAddress()
    {
        Mail::fake();
        $controller = new SubscriberController();

        $controller->store($this->jsonRequest('/subscribe', ['EMAIL' => 'sub@example.by']));
        $subscriber = Subscriber::query()->first();
        $subscriber->confirm();

        $controller->store($this->jsonRequest('/subscribe', ['EMAIL' => 'sub@example.by']));

        $this->assertSame(1, Subscriber::query()->count());
        $this->assertNotNull($subscriber->fresh()->confirmed_at);
        Mail::assertQueued(SubscriptionConfirmation::class, 1);
    }

    public function testConfirmByTokenActivatesSubscription()
    {
        Mail::fake();
        $controller = new SubscriberController();
        $controller->store($this->jsonRequest('/subscribe', ['EMAIL' => 'sub@example.by']));

        $token = Subscriber::query()->first()->token;
        $response = $controller->confirm($token);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull(Subscriber::query()->first()->confirmed_at);
    }

    public function testConfirmWithUsedTokenReturnsGone()
    {
        Mail::fake();
        $controller = new SubscriberController();
        $controller->store($this->jsonRequest('/subscribe', ['EMAIL' => 'sub@example.by']));

        $token = Subscriber::query()->first()->token;
        $controller->confirm($token);
        $second = $controller->confirm($token);

        $this->assertSame(410, $second->getStatusCode());
    }

    public function testUnsubscribeByTokenIsRepeatable()
    {
        Mail::fake();
        $controller = new SubscriberController();
        $controller->store($this->jsonRequest('/subscribe', ['EMAIL' => 'sub@example.by']));

        $token = Subscriber::query()->first()->token;
        $first = $controller->unsubscribe($token);
        $second = $controller->unsubscribe($token);

        $this->assertSame(200, $first->getStatusCode());
        $this->assertSame(200, $second->getStatusCode());
        $this->assertNotNull(Subscriber::query()->first()->unsubscribed_at);
    }

    public function testBookingTakesSlotAndQueuesBothMails()
    {
        Mail::fake();
        $slot = $this->makeSlot();

        $response = (new BookingController(new BookingService()))->store(
            $this->jsonRequest('/booking', $this->bookingPayload())
        );

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(1, Booking::query()->count());

        $booking = Booking::query()->first();
        $this->assertSame('sauna', $booking->service_code);
        $this->assertSame($this->date(), (string) $booking->date);
        $this->assertSame(Booking::STATUS_NEW, $booking->status);

        $slot->refresh();
        $this->assertSame(1, (int) $slot->taken);
        $this->assertSame($booking->id, (int) $slot->booking_id);

        Mail::assertQueued(BookingNotification::class);
        Mail::assertQueued(BookingConfirmation::class);
    }

    public function testBookingWithoutEmailSkipsClientCopy()
    {
        Mail::fake();
        $this->makeSlot();

        (new BookingController(new BookingService()))->store(
            $this->jsonRequest('/booking', $this->bookingPayload([
                'PROPERTY' => ['EMAIL' => ['0' => '']],
            ]))
        );

        Mail::assertQueued(BookingNotification::class);
        Mail::assertNotQueued(BookingConfirmation::class);
    }

    public function testBookingForTimeOutsideGenerationWindowIsRejected()
    {
        Mail::fake();
        $this->makeSlot();

        $response = (new BookingController(new BookingService()))->store(
            $this->jsonRequest('/booking', $this->bookingPayload([
                'PROPERTY' => ['TIME' => ['0' => '21:30']],
            ]))
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(0, Booking::query()->count());
        $this->assertSame(0, (int) Slot::query()->first()->taken);
        Mail::assertNothingQueued();
    }

    public function testBookingOnTakenSlotReturnsConflict()
    {
        Mail::fake();
        $this->makeSlot(['taken' => 1]);
        $controller = new BookingController(new BookingService());

        $response = $controller->store($this->jsonRequest('/booking', $this->bookingPayload()));
        $body = json_decode($response->getContent(), true);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('SLOT_UNAVAILABLE', $body['code']);
        $this->assertSame(0, Booking::query()->count());
        Mail::assertNothingQueued();
    }

    public function testBookingRejectsPastDate()
    {
        Mail::fake();
        $this->makeSlot(['date' => now()->subDay()->toDateString()]);

        $response = (new BookingController(new BookingService()))->store(
            $this->jsonRequest('/booking', $this->bookingPayload([
                'PROPERTY' => ['DATE' => ['0' => now()->subDay()->toDateString()]],
            ]))
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(0, Booking::query()->count());
    }

    public function testOrderStoresRecordAndQueuesMail()
    {
        Mail::fake();

        $response = (new OrderController())->store(
            $this->jsonRequest('/order', $this->orderPayload())
        );

        $this->assertSame(201, $response->getStatusCode());

        $order = Order::query()->first();
        $this->assertSame('Шоппер «Библиотека»', $order->item);
        $this->assertSame(25, (int) $order->price);
        $this->assertSame(2, (int) $order->qty);
        $this->assertSame('order', $order->source);
        $this->assertSame(Order::STATUS_NEW, $order->status);

        Mail::assertQueued(OrderNotification::class);
    }

    public function testOrderRejectsZeroQuantity()
    {
        Mail::fake();

        $response = (new OrderController())->store(
            $this->jsonRequest('/order', $this->orderPayload([
                'PROPERTY' => ['QTY' => ['0' => '0']],
            ]))
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(0, Order::query()->count());
        Mail::assertNothingQueued();
    }

    public function testOrderRejectsNegativePrice()
    {
        Mail::fake();

        $response = (new OrderController())->store(
            $this->jsonRequest('/order', $this->orderPayload([
                'PROPERTY' => ['PRICE' => ['0' => '-10']],
            ]))
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(0, Order::query()->count());
    }

    public function testMailTemplatesRenderWithData()
    {
        $feedback = (new FeedbackNotification([
            'NAME' => 'Иван Петров',
            'EMAIL' => 'ivan@example.by',
            'MESSAGE' => 'Текст обращения',
        ]))->render();

        $this->assertStringContainsString('Иван Петров', $feedback);
        $this->assertStringContainsString('Текст обращения', $feedback);

        $client = (new BookingConfirmation([
            'PERSON' => 'Иван Петров',
            'SERVICE' => 'Сауна',
            'DATE' => $this->date(),
            'TIME' => '10:00',
            'PHONE' => '+375291112233',
        ]))->render();

        $this->assertStringContainsString('Сауна', $client);
        $this->assertStringContainsString('10:00', $client);

        $order = (new OrderNotification([
            'ITEM' => 'Шоппер',
            'PRICE' => '25',
            'QTY' => '2',
            'PERSON' => 'Иван Петров',
            'PHONE' => '+375291112233',
        ]))->render();

        $this->assertStringContainsString('Шоппер', $order);
        $this->assertStringContainsString('25', $order);

        $confirm = (new SubscriptionConfirmation(
            'ivan@example.by',
            'http://localhost/subscribe/confirm/token',
            'http://localhost/unsubscribe/token'
        ))->render();

        $this->assertStringContainsString('subscribe/confirm/token', $confirm);
        $this->assertStringContainsString('unsubscribe/token', $confirm);
    }

    public function testSubscriptionActionPageRendersFailureText()
    {
        $response = (new SubscriberController())->confirm('unknown-token');

        $this->assertSame(410, $response->getStatusCode());
        $this->assertStringContainsString('Ссылка не сработала', $response->getContent());
    }
}
