<?php namespace Nlb\Library\Classes\Mail;

use October\Rain\Mail\Mailable;

/**
 * BookingConfirmation — письмо клиенту о созданной брони.
 */
class BookingConfirmation extends Mailable
{
    /**
     * @param  array<string, string>  $booking
     */
    public function __construct(public array $booking)
    {
    }

    public function build()
    {
        return $this->subject('Бронь принята: ' . ($this->booking['SERVICE'] ?? 'услуга'))
            ->view('nlb.library::mail.booking-client')
            ->with(['booking' => $this->booking]);
    }
}
