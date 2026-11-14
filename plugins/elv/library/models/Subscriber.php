<?php namespace Elv\Library\Models;

use October\Rain\Database\Model;
use October\Rain\Database\Traits\Validation;
use Str;

/**
 * Subscriber — подписка на рассылку с подтверждением (double opt-in).
 */
class Subscriber extends Model
{
    use Validation;

    public $table = 'elv_subscribers';

    protected $guarded = [];

    public $rules = [
        'email' => 'required|email|max:191',
        'token' => 'required|string|max:64',
    ];

    public $customMessages = [
        'email.required' => 'Укажите e-mail.',
        'email.email' => 'Проверьте адрес электронной почты.',
    ];

    public $casts = [
        'confirmed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    public static function subscribe(string $email): self
    {
        $email = mb_strtolower(trim($email));
        $subscriber = static::where('email', $email)->first();

        if ($subscriber) {
            if ($subscriber->unsubscribed_at !== null) {
                $subscriber->token = static::makeToken();
                $subscriber->confirmed_at = null;
                $subscriber->unsubscribed_at = null;
                $subscriber->save();
            }

            return $subscriber;
        }

        return static::create([
            'email' => $email,
            'token' => static::makeToken(),
        ]);
    }

    public static function makeToken(): string
    {
        do {
            $token = Str::random(48);
        } while (static::where('token', $token)->exists());

        return $token;
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    public function isUnsubscribed(): bool
    {
        return $this->unsubscribed_at !== null;
    }

    public function confirm(): bool
    {
        if ($this->isUnsubscribed() || $this->isConfirmed()) {
            return false;
        }

        $this->confirmed_at = now();
        $this->save();

        return true;
    }

    public function unsubscribe(): void
    {
        if ($this->isUnsubscribed()) {
            return;
        }

        $this->unsubscribed_at = now();
        $this->save();
    }

    public static function confirmByToken(string $token): ?self
    {
        $subscriber = static::where('token', $token)->first();
        if (!$subscriber || !$subscriber->confirm()) {
            return null;
        }

        return $subscriber;
    }

    public static function unsubscribeByToken(string $token): ?self
    {
        $subscriber = static::where('token', $token)->first();
        if (!$subscriber) {
            return null;
        }

        $subscriber->unsubscribe();

        return $subscriber;
    }
}
