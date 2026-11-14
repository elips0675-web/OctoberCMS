<?php namespace Elv\Library\Tests;

use Elv\Library\Models\Order;
use Elv\Library\Models\Request;
use Elv\Library\Models\Subscriber;
use October\Rain\Database\ModelException;
use PluginTestCase;

class ModelsTest extends PluginTestCase
{
    protected $useTransactions = true;

    public function testRequestRequiresMessageAndOneContact()
    {
        $request = new Request();

        try {
            $request->save();
            $this->fail('Пустая заявка сохранилась.');
        }
        catch (ModelException $e) {
            $errors = $e->getErrors()->toArray();
            $this->assertArrayHasKey('subject', $errors);
            $this->assertArrayHasKey('message', $errors);
            $this->assertArrayHasKey('email', $errors);
            $this->assertArrayHasKey('phone', $errors);
        }

        $this->assertSame(0, Request::query()->count());
    }

    public function testRequestAcceptsPhoneWithoutEmail()
    {
        $request = Request::create([
            'subject' => 'Запись в библиотеку',
            'message' => 'Подскажите часы работы.',
            'phone' => '+375291112233',
            'page' => '/contacts/',
            'source' => 'feedback',
        ]);

        $this->assertTrue($request->exists);
        $this->assertSame(Request::STATUS_NEW, $request->status);

        $request->markProcessed();

        $this->assertSame(Request::STATUS_DONE, $request->fresh()->status);
        $this->assertNotNull($request->fresh()->date_processed);
    }

    public function testOrderCalculatesTotalAndValidatesQuantity()
    {
        $order = Order::create([
            'item' => 'Брелок «Библиотека»',
            'article' => 'elv-book-001',
            'price' => 300,
            'qty' => 2,
            'person' => 'Иван Петров',
            'phone' => '+375291112233',
        ]);

        $this->assertSame(600, $order->total());
        $this->assertSame(Order::STATUS_NEW, $order->status);

        try {
            Order::create([
                'item' => 'Брелок',
                'price' => 300,
                'qty' => 0,
                'person' => 'Иван',
                'phone' => '+375291112233',
            ]);
            $this->fail('Нулевое количество прошло валидацию.');
        }
        catch (ModelException $e) {
            $this->assertArrayHasKey('qty', $e->getErrors()->toArray());
        }
    }

    public function testSubscriberDoubleOptIn()
    {
        $subscriber = Subscriber::subscribe('Reader@Example.com');

        $this->assertSame('reader@example.com', $subscriber->email);
        $this->assertFalse($subscriber->isConfirmed());
        $this->assertSame(48, strlen($subscriber->token));

        $repeated = Subscriber::subscribe('reader@example.com');
        $this->assertSame($subscriber->id, $repeated->id);
        $this->assertSame(1, Subscriber::query()->count());

        $confirmed = Subscriber::confirmByToken($subscriber->token);
        $this->assertNotNull($confirmed);
        $this->assertTrue($confirmed->isConfirmed());
        $this->assertNull(Subscriber::confirmByToken('unknown-token'));
    }

    public function testSubscriberUnsubscribeAndResubscribe()
    {
        $subscriber = Subscriber::subscribe('reader@example.com');
        Subscriber::confirmByToken($subscriber->token);

        $unsubscribed = Subscriber::unsubscribeByToken($subscriber->token);
        $this->assertNotNull($unsubscribed);
        $this->assertTrue($unsubscribed->isUnsubscribed());
        $this->assertNull(Subscriber::confirmByToken($subscriber->token));

        $again = Subscriber::subscribe('reader@example.com');
        $this->assertSame($subscriber->id, $again->id);
        $this->assertFalse($again->isUnsubscribed());
        $this->assertFalse($again->isConfirmed());
        $this->assertNotSame($subscriber->token, $again->token);
    }
}
