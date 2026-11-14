<?php namespace Elv\Library\Classes;

use Cms\Classes\Theme;

/**
 * Brand — бренд и контакты сайта из настроек темы.
 *
 * Значения берутся из `themes/elv/theme.yaml` (раздел `form.fields`) и
 * переопределяются настройками темы в админке (таблица `cms_theme_data`).
 * В коде — только нейтральные значения: ни названия организации, ни адреса,
 * ни телефона конкретного заказчика здесь нет.
 */
class Brand
{
    /**
     * FALLBACK — значения для чистой установки и для случая, когда настройки
     * темы пусты. Совпадают с `default` в `theme.yaml`.
     */
    public const FALLBACK = [
        'name' => 'Ваша организация',
        'legal_name' => 'Ваша организация',
        'tagline' => 'Центр услуг',
        'description' => 'Сайт организации: услуги, события и контакты.',
        'kind' => 'Организация',
        'phone' => '+375 (00) 000-00-00',
        'email' => 'info@example.com',
        'postal_code' => '000000',
        'city' => 'Город',
        'street' => 'ул. Примерная, 1',
        'country' => 'BY',
        'hours' => 'Ежедневно 10:00–22:00',
        'opens' => '10:00',
        'closes' => '22:00',
        'schema_type' => 'Organization',
        'social_telegram' => '',
        'social_whatsapp' => '',
        'social_vk' => '',
        'social_youtube' => '',
    ];

    /**
     * @var array|null значения, собранные за текущий запрос
     */
    protected static $values = null;

    /**
     * all — все значения бренда: запасные, затем default из `theme.yaml`,
     * затем сохранённые настройки темы.
     */
    public static function all(): array
    {
        if (static::$values !== null) {
            return static::$values;
        }

        $values = static::FALLBACK;

        try {
            $theme = Theme::getActiveTheme();
        }
        catch (\Throwable $ex) {
            return static::$values = $values;
        }

        if (!$theme) {
            return static::$values = $values;
        }

        foreach ((array) $theme->getConfig('form.fields', []) as $key => $field) {
            $name = static::normalizeKey((string) $key);

            if (array_key_exists($name, $values) && isset($field['default'])) {
                $values[$name] = (string) $field['default'];
            }
        }

        $data = $theme->getCustomData()->getAttribute('data');

        if (is_string($data)) {
            $data = json_decode($data, true) ?: [];
        }

        foreach ((array) $data as $key => $value) {
            $name = static::normalizeKey((string) $key);

            if (array_key_exists($name, $values) && is_scalar($value) && trim((string) $value) !== '') {
                $values[$name] = trim((string) $value);
            }
        }

        return static::$values = $values;
    }

    /**
     * normalizeKey — `brand_name` в настройках темы соответствует `name` в коде.
     */
    protected static function normalizeKey(string $key): string
    {
        return str_starts_with($key, 'brand_') ? substr($key, 6) : $key;
    }

    /**
     * get — одно значение бренда. `phone_href` отдаёт телефон в виде для `tel:`.
     */
    public static function get(string $key, string $default = ''): string
    {
        $values = static::all();

        if ($key === 'phone_href') {
            return static::phoneHref();
        }

        if ($key === 'address_line') {
            return static::addressLine();
        }

        return $values[$key] ?? $default;
    }

    /**
     * phoneHref — телефон без разделителей, с ведущим плюсом.
     */
    public static function phoneHref(): string
    {
        $digits = preg_replace('/\D+/', '', static::get('phone'));

        return $digits === '' ? '' : '+' . $digits;
    }

    /**
     * addressLine — адрес одной строкой: индекс, город, улица.
     */
    public static function addressLine(): string
    {
        return trim(implode(', ', array_filter([
            static::get('postal_code'),
            static::get('city'),
            static::get('street'),
        ])));
    }

    /**
     * replace — подстановка `{brand}`, `{legal}`, `{city}` и подобных в тексте
     * заголовков страниц (`title = "Контакты — {brand}"`).
     */
    public static function replace(string $text): string
    {
        return strtr($text, [
            '{brand}' => static::get('name'),
            '{legal}' => static::get('legal_name'),
            '{tagline}' => static::get('tagline'),
            '{kind}' => static::get('kind'),
            '{city}' => static::get('city'),
            '{street}' => static::get('street'),
            '{phone}' => static::get('phone'),
            '{email}' => static::get('email'),
        ]);
    }

    /**
     * forget — сбросить собранные значения (тесты и смена настроек).
     */
    public static function forget(): void
    {
        static::$values = null;
    }
}
