<?php namespace Nlb\Library\Classes\Mail;

/**
 * MailSettings — адреса получателей писем.
 *
 * Владелец задаёт ящик в `.env` (`NLB_ADMIN_EMAIL`); пока его нет, письма
 * уходят на `MAIL_FROM_ADDRESS`, чтобы форма не падала из-за незаполненного
 * окружения (P0-4).
 */
class MailSettings
{
    public static function adminAddress(): string
    {
        $admin = trim((string) env('NLB_ADMIN_EMAIL', ''));

        if ($admin !== '') {
            return $admin;
        }

        return (string) config('mail.from.address');
    }
}
