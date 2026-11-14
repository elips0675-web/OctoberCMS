<?php namespace Elv\Library\Classes;

use Illuminate\Http\Request;

/**
 * FormFields — разбор полей формы в формате источника (Bitrix).
 *
 * Источник присылает вложенные имена (`PROPERTY[EMAIL][0]`), Laravel раскладывает
 * их в массив `PROPERTY => [EMAIL => [0 => …]]`. Здесь это превращается в плоский
 * набор `EMAIL`, `PHONE`, `DATE`, … — с ним работают валидация и модели, а не с
 * именами Битрикса.
 */
class FormFields
{
    /**
     * fromRequest собирает плоские поля из запроса.
     *
     * @return array<string, string>
     */
    public static function fromRequest(Request $request): array
    {
        return static::normalize((array) $request->all());
    }

    /**
     * normalize превращает `PROPERTY[*][0]` и простые имена в плоский массив.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public static function normalize(array $input): array
    {
        $fields = [];

        foreach ((array) ($input['PROPERTY'] ?? []) as $name => $value) {
            $fields[static::key($name)] = static::scalar($value);
        }

        foreach ($input as $name => $value) {
            if (!is_string($name) || is_array($value)) {
                continue;
            }

            $key = static::key($name);
            if ($key !== '' && !array_key_exists($key, $fields)) {
                $fields[$key] = static::scalar($value);
            }
        }

        return array_filter($fields, static function ($value) {
            return $value !== '';
        });
    }

    /**
     * consentGiven отвечает, отмечен ли чекбокс согласия.
     *
     * @param  array<string, mixed>  $input
     */
    public static function consentGiven(array $input): bool
    {
        $fields = static::normalize($input);
        $value = mb_strtolower((string) ($fields['CONSENT'] ?? ''));

        return in_array($value, ['y', 'yes', '1', 'on', 'true'], true);
    }

    /**
     * page возвращает путь страницы, с которой пришла форма.
     */
    public static function page(Request $request): ?string
    {
        $referer = (string) $request->headers->get('referer', '');
        if ($referer === '') {
            return null;
        }

        $path = (string) parse_url($referer, PHP_URL_PATH);

        return $path !== '' ? mb_substr($path, 0, 255) : null;
    }

    protected static function key($name): string
    {
        return strtoupper(trim((string) $name));
    }

    /**
     * scalar берёт первое значение из `[0 => …]` и приводит его к строке.
     */
    protected static function scalar($value): string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
