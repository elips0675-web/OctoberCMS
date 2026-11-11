<?php namespace Nlb\Library\Classes\Mail;

use October\Rain\Mail\Mailable;

/**
 * OrderNotification — письмо сотрудникам о заказе сувенира.
 */
class OrderNotification extends Mailable
{
    /**
     * @param  array<string, string>  $order
     */
    public function __construct(public array $order)
    {
    }

    public function build()
    {
        return $this->subject('Заказ: ' . ($this->order['ITEM'] ?? 'сувенир'))
            ->view('nlb.library::mail.order-staff')
            ->with(['order' => $this->order]);
    }
}
