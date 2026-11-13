<?php namespace Nlb\Library\Classes\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Nlb\Library\Classes\Mail\SubscriptionConfirmation;
use Nlb\Library\Models\Subscriber;

/**
 * SubscriberController — подписка с двойным подтверждением (P0-5).
 *
 * Рассылки нет: есть подтверждение по токену и отписка. Повторная подписка не
 * создаёт дубль (`UNIQUE(email)`), неподтверждённый адрес остаётся в базе с
 * `confirmed_at = null` — рассылать по нему нельзя.
 */
class SubscriberController extends FormController
{
    protected function rules(): array
    {
        return [
            'EMAIL' => 'required|email|max:191',
        ];
    }

    protected function messages(): array
    {
        return [
            'EMAIL.required' => 'Укажите e-mail.',
            'EMAIL.email' => 'Проверьте адрес электронной почты.',
        ];
    }

    protected function handle(array $data, Request $request)
    {
        $subscriber = Subscriber::subscribe((string) $data['EMAIL']);

        if (!$subscriber->isConfirmed() && !$subscriber->isUnsubscribed()) {
            $root = rtrim((string) config('app.url'), '/');

            Mail::queue(
                (new SubscriptionConfirmation(
                    $subscriber->email,
                    $root . '/subscribe/confirm/' . $subscriber->token,
                    $root . '/unsubscribe/' . $subscriber->token
                ))->to($subscriber->email)
            );
        }

        return null;
    }

    /**
     * confirm подтверждает подписку по ссылке из письма.
     */
    public function confirm(string $token)
    {
        $subscriber = Subscriber::confirmByToken($token);

        return $this->page(
            $subscriber ? 200 : 410,
            $subscriber ? 'Подписка подтверждена' : 'Ссылка не сработала',
            $subscriber
                ? 'Теперь вы будете получать анонсы событий и новости.'
                : 'Ссылка уже использована или подписка отменена. Оформите подписку заново на странице «Подписка».'
        );
    }

    /**
     * unsubscribe отписывает по ссылке из письма. Повторный переход безопасен.
     */
    public function unsubscribe(string $token)
    {
        $subscriber = Subscriber::unsubscribeByToken($token);

        return $this->page(
            $subscriber ? 200 : 410,
            $subscriber ? 'Вы отписались' : 'Ссылка не сработала',
            $subscriber
                ? 'Письма больше не будут приходить. Подписаться снова можно в любой момент.'
                : 'Такой подписки нет. Возможно, ссылка устарела.'
        );
    }

    /**
     * page отдаёт страницу результата: ссылка из письма открывается в браузере.
     */
    protected function page(int $status, string $title, string $message)
    {
        return response(
            view('nlb.library::action', [
                'title' => $title,
                'message' => $message,
            ])->render(),
            $status
        );
    }

    protected function requiresConsent(): bool
    {
        return false;
    }

    protected function fallbackPath(): string
    {
        return '/subscribe/';
    }

    protected function anchor(): string
    {
        return 'form';
    }
}
