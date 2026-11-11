<?php namespace Nlb\Library\Classes\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Nlb\Library\Classes\FormFields;

/**
 * FormController — общая часть публичных форм.
 *
 * Один вход, три выхода: успех (`?sent=1#якорь`), ошибка валидации (`422` для
 * JSON, редирект с ошибками для браузера), конфликт слота (`409` для JSON).
 * Проверка согласия и разбор полей Bitrix — здесь, чтобы не расходились
 * четыре копии.
 */
abstract class FormController
{
    /**
     * store принимает отправку формы.
     *
     * @return JsonResponse|RedirectResponse
     */
    public function store(Request $request)
    {
        $input = (array) $request->all();

        if ($this->requiresConsent() && !FormFields::consentGiven($input)) {
            return $this->invalid($request, [
                'CONSENT' => ['Нужно согласие на обработку персональных данных.'],
            ]);
        }

        $data = FormFields::normalize($input);
        $validator = Validator::make($data, $this->rules(), $this->messages());

        if ($validator->fails()) {
            return $this->invalid($request, $validator->errors()->toArray());
        }

        return $this->handle($data, $request) ?: $this->done($request);
    }

    /**
     * rules — правила валидации плоских полей.
     *
     * @return array<string, mixed>
     */
    abstract protected function rules(): array;

    /**
     * messages — человеческие тексты ошибок.
     *
     * @return array<string, string>
     */
    abstract protected function messages(): array;

    /**
     * handle сохраняет данные и ставит письма в очередь. Возвращает ответ,
     * если запись не состоялась (конфликт слота), иначе null.
     *
     * @param  array<string, string>  $data
     * @return JsonResponse|RedirectResponse|null
     */
    abstract protected function handle(array $data, Request $request);

    /**
     * fallbackPath — куда вернуть, если браузер не прислал Referer.
     */
    abstract protected function fallbackPath(): string;

    /**
     * anchor — якорь блока формы для возврата.
     */
    abstract protected function anchor(): string;

    /**
     * requiresConsent — нужен ли чекбокс согласия. У подписки его нет в макете:
     * добровольная отправка адреса и есть согласие, подтверждение — ссылкой.
     */
    protected function requiresConsent(): bool
    {
        return true;
    }

    /**
     * invalid отдаёт ошибки валидации.
     *
     * @param  array<string, mixed>  $errors
     * @return JsonResponse|RedirectResponse
     */
    protected function invalid(Request $request, array $errors)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'errors' => $errors,
            ], 422);
        }

        return $this->back($request, ['error' => '1'])->withInput()->withErrors($errors);
    }

    /**
     * conflict отвечает на занятый слот.
     *
     * @return JsonResponse|RedirectResponse
     */
    protected function conflict(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'code' => 'SLOT_UNAVAILABLE',
                'message' => $message,
            ], 409);
        }

        return $this->back($request, ['error' => 'slot'])
            ->withInput()
            ->withErrors(['TIME' => $message]);
    }

    /**
     * done отвечает на успешную отправку.
     *
     * @return JsonResponse|RedirectResponse
     */
    protected function done(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => true], 201);
        }

        return $this->back($request, ['sent' => '1']);
    }

    /**
     * back возвращает на страницу формы: Referer того же хоста, иначе fallback.
     *
     * @param  array<string, string>  $query
     */
    protected function back(Request $request, array $query): RedirectResponse
    {
        return redirect($this->returnUrl($request, $query) . '#' . $this->anchor());
    }

    /**
     * returnUrl собирает адрес возврата с параметрами, не теряя чужие.
     *
     * @param  array<string, string>  $query
     */
    protected function returnUrl(Request $request, array $query): string
    {
        $referer = trim((string) $request->headers->get('referer', ''));
        $host = $referer !== '' ? (string) parse_url($referer, PHP_URL_HOST) : '';

        $url = ($referer !== '' && $host !== '' && $host === $request->getHost())
            ? $referer
            : rtrim(url('/'), '/') . $this->fallbackPath();

        $parts = parse_url($url);
        $params = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $params);
        }

        $params = array_merge($params, $query);

        $base = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? 'localhost');
        if (!empty($parts['port'])) {
            $base .= ':' . $parts['port'];
        }
        $base .= $parts['path'] ?? '/';

        return $base . '?' . http_build_query($params);
    }
}
