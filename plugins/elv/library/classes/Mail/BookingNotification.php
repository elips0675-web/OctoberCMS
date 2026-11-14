<?php namespace Elv\Library\Classes\Mail;

use October\Rain\Mail\Mailable;

/**
 * BookingNotification — письмо сотрудникам о новой брони.
 */
class BookingNotification extends Mailable
{
    /**
     * @param  array<string, string>  $booking
     */
    public function __construct(public array $booking)
    {
    }

    public function build()
    {
        return $this->subject('Новая бронь: ' . ($this->booking['SERVICE'] ?? 'услуга'))
            ->view('elv.library::mail.booking-staff')
            ->with(['booking' => $this->booking]);
    }
}
