<?php namespace Elv\Site\Classes\Mail;

use October\Rain\Mail\Mailable;

/**
 * FeedbackNotification — письмо сотрудникам об обращении с сайта.
 */
class FeedbackNotification extends Mailable
{
    /**
     * @param  array<string, string>  $feedback
     */
    public function __construct(public array $feedback)
    {
    }

    public function build()
    {
        return $this->subject('Обращение с сайта: ' . ($this->feedback['SUBJECT'] ?? 'без темы'))
            ->view('elv.site::mail.feedback')
            ->with(['feedback' => $this->feedback]);
    }
}
