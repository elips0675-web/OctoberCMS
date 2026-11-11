<?php namespace Nlb\Library\Tests;

use Backend\Models\User as BackendUser;
use Backend\Models\UserRole;
use Nlb\Library\Classes\BookingService;
use Nlb\Library\Models\Booking;
use Nlb\Library\Models\Order;
use Nlb\Library\Models\Request as RequestModel;
use Nlb\Library\Models\Slot;
use Nlb\Library\Models\Subscriber;
use October\Rain\Exception\ApplicationException;
use October\Rain\Exception\ForbiddenException;
use PluginTestCase;
use Session;

/**
 * AdminTest — админ-списки заявок, броней, заказов и подписчиков.
 *
 * Проверяется то, ради чего админка существует: страница открывается
 * с правом и не открывается без него, а смена статуса доходит до базы
 * (для брони — ещё и освобождает слот).
 */
class AdminTest extends PluginTestCase
{
    protected $useTransactions = true;

    protected function makeSuperUser(): BackendUser
    {
        $user = new BackendUser();
        $user->login = 'qa-admin';
        $user->email = 'qa-admin@example.by';
        $user->first_name = 'QA';
        $user->last_name = 'Admin';
        $user->password = 'secret12345';
        $user->password_confirmation = 'secret12345';
        $user->is_superuser = true;
        $user->save();

        return $user;
    }

    protected function makeOperator(array $permissions): BackendUser
    {
        $role = new UserRole();
        $role->name = 'QA operator ' . count($permissions);
        $role->code = 'qa-operator-' . count($permissions);
        $role->permissions = ['general.backend' => 1] + $permissions;
        $role->save();

        $user = new BackendUser();
        $user->login = 'qa-operator-' . count($permissions);
        $user->email = 'qa-operator@example.by';
        $user->first_name = 'QA';
        $user->last_name = 'Operator';
        $user->password = 'secret12345';
        $user->password_confirmation = 'secret12345';
        $user->role_id = $role->id;
        $user->save();

        return $user;
    }

    protected function makeRequest(): RequestModel
    {
        $request = new RequestModel();
        $request->subject = 'Проверка админки';
        $request->message = 'Текст обращения для списка.';
        $request->email = 'list@example.by';
        $request->source = 'feedback';
        $request->save();

        return $request;
    }

    protected function makeOrder(): Order
    {
        $order = new Order();
        $order->item = 'Шоппер «Библиотека»';
        $order->price = 25;
        $order->qty = 2;
        $order->person = 'Иван Петров';
        $order->phone = '+375291112233';
        $order->save();

        return $order;
    }

    protected function makeBooking(): array
    {
        $date = now()->addDay()->toDateString();

        $slot = Slot::create([
            'service_code' => 'sauna',
            'date' => $date,
            'time' => '11:00',
            'capacity' => 1,
            'taken' => 0,
        ]);

        $booking = (new BookingService())->book([
            'service' => 'Сауна',
            'service_code' => 'sauna',
            'date' => $date,
            'time' => '11:00',
            'person' => 'Иван Петров',
            'phone' => '+375291112233',
        ]);

        return [$booking, $slot];
    }

    public function testRequestsPageListsRecordsForSuperUser()
    {
        $this->be($this->makeSuperUser());
        $this->makeRequest();

        $response = $this->get('/admin/nlb/library/requests');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Проверка админки', $response->getContent());
    }

    public function testBookingsPageOpensForOperatorWithPermission()
    {
        $this->be($this->makeOperator(['nlb.library.bookings' => 1]));
        $this->makeBooking();

        $response = $this->get('/admin/nlb/library/bookings');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Иван Петров', $response->getContent());
    }

    public function testBookingsPageIsForbiddenWithoutPermission()
    {
        $this->be($this->makeOperator(['nlb.library.requests' => 1]));

        $this->expectException(ForbiddenException::class);

        $this->withoutExceptionHandling()->get('/admin/nlb/library/bookings');
    }

    public function testNavigationHidesBookingsItemWithoutPermission()
    {
        $this->be($this->makeOperator(['nlb.library.requests' => 1]));

        $content = $this->get('/admin/nlb/library/requests')->getContent();

        $this->assertStringNotContainsString('/admin/nlb/library/bookings', $content);
    }

    public function testBookingUpdateFormRendersStatusDropdown()
    {
        [$booking] = $this->makeBooking();
        $this->be($this->makeSuperUser());

        $response = $this->get('/admin/nlb/library/bookings/update/' . $booking->getKey());

        $this->assertSame(200, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertStringContainsString('Отменена', $content);
        $this->assertStringContainsString('Сауна', $content);
    }

    public function testBookingStatusSavedThroughAdminForm()
    {
        [$booking, $slot] = $this->makeBooking();
        $this->be($this->makeSuperUser());

        $token = Session::token();

        $response = $this->post(
            '/admin/nlb/library/bookings/update/' . $booking->getKey(),
            ['Booking' => ['status' => Booking::STATUS_CANCELLED], '_token' => $token],
            [
                'X-AJAX-HANDLER' => 'onSave',
                'X-CSRF-TOKEN' => $token,
                'X-Requested-With' => 'XMLHttpRequest',
            ]
        );

        $this->assertSame(200, $response->getStatusCode(), substr((string) $response->getContent(), 0, 300));
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
        $this->assertSame(0, (int) $slot->fresh()->taken);
    }

    public function testOrdersPageListsRecords()
    {
        $this->be($this->makeSuperUser());
        $this->makeOrder();

        $response = $this->get('/admin/nlb/library/orders');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Шоппер', $response->getContent());
    }

    public function testSubscribersPageListsAddresses()
    {
        $this->be($this->makeSuperUser());

        $subscriber = Subscriber::subscribe('reader@example.by');

        $response = $this->get('/admin/nlb/library/subscribers');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('reader@example.by', $response->getContent());
    }

    public function testRequestStatusDoneStampsProcessedDate()
    {
        $request = $this->makeRequest();

        $this->assertNull($request->date_processed);

        $request->status = RequestModel::STATUS_DONE;
        $request->save();

        $this->assertNotNull($request->fresh()->date_processed);
    }

    public function testOrderCancellationStampsProcessedDate()
    {
        $order = $this->makeOrder();

        $order->status = Order::STATUS_CANCELLED;
        $order->save();

        $fresh = $order->fresh();
        $this->assertSame(Order::STATUS_CANCELLED, $fresh->status);
        $this->assertNotNull($fresh->date_processed);
    }

    public function testBookingCancelledByAdminReleasesSlot()
    {
        [$booking, $slot] = $this->makeBooking();

        $this->assertSame(1, (int) $slot->fresh()->taken);

        $booking->status = Booking::STATUS_CANCELLED;
        $booking->save();

        $slot->refresh();
        $this->assertSame(0, (int) $slot->taken);
        $this->assertNull($slot->booking_id);
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->date_processed);
    }

    public function testCancelledBookingCannotBeReturnedToWork()
    {
        [$booking] = $this->makeBooking();

        $booking->status = Booking::STATUS_CANCELLED;
        $booking->save();

        $this->expectException(ApplicationException::class);

        $booking->status = Booking::STATUS_CONFIRMED;
        $booking->save();
    }

    public function testServiceCancelReleasesSlotExactlyOnce()
    {
        [$booking, $slot] = $this->makeBooking();

        (new BookingService())->cancel($booking);
        (new BookingService())->cancel($booking);

        $this->assertSame(0, (int) $slot->fresh()->taken);
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
    }

    public function testConfirmKeepsSlotTaken()
    {
        [$booking, $slot] = $this->makeBooking();

        (new BookingService())->confirm($booking);

        $this->assertSame(1, (int) $slot->fresh()->taken);
        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->fresh()->status);
    }
}
