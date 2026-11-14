<?php namespace Elv\Library\Classes\Mail;

use October\Rain\Mail\Mailable;

/**
 * SubscriptionConfirmation — письмо double opt-in со ссылкой подтверждения.
 */
class SubscriptionConfirmation extends Mailable
{
    public function __construct(
        public string $email,
        public string $confirmUrl,
        public string $unsubscribeUrl
    ) {
    }

    public function build()
    {
        return $this->subject('Подтвердите подписку на новости')
            ->view('elv.library::mail.subscribe-confirm')
            ->with([
                'email' => $this->email,
                'confirmUrl' => $this->confirmUrl,
                'unsubscribeUrl' => $this->unsubscribeUrl,
            ]);
    }
}
