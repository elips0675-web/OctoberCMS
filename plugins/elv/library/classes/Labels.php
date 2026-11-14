<?php namespace Elv\Library\Classes;

/**
 * Labels — подписи свойств и цвета карточек.
 *
 * Значения взяты из db/config/iblocks.php источника: шаблоны не хранят
 * русский текст свойств, а берут его из одного места.
 */
class Labels
{
    /**
     * @var array<string, string>
     */
    protected static $tags = [
        'books' => 'Книги',
        'digital' => 'Цифровые ресурсы',
        'events' => 'События',
        'kids' => 'Дети',
        'science' => 'Наука',
        'library' => 'О нас',
    ];

    /**
     * @var array<string, string>
     */
    protected static $eventCategories = [
        'literature' => 'Литература',
        'children' => 'Для детей',
        'tech' => 'Технологии',
        'lecture' => 'Лекции',
        'lectures' => 'Лекции',
    ];

    /**
     * @var array<string, string>
     */
    protected static $eventGradients = [
        'literature' => 'bg-grad-sky',
        'children' => 'bg-grad-pink',
        'tech' => 'bg-grad-green',
        'lecture' => 'bg-grad-orange',
        'lectures' => 'bg-grad-orange',
    ];

    /**
     * @var array<string, string>
     */
    protected static $formats = [
        'offline' => 'Офлайн',
        'online' => 'Онлайн',
        'hybrid' => 'Гибрид',
    ];

    /**
     * @var array<string, array{label: string, class: string}>
     */
    protected static $availability = [
        'available' => ['label' => 'В наличии', 'class' => 'is-available'],
        'reserved' => ['label' => 'Под заказ', 'class' => 'is-reserved'],
        'unavailable' => ['label' => 'Нет в наличии', 'class' => 'is-unavailable'],
    ];

    /**
     * @var array<string, string>
     */
    protected static $ages = [
        'all' => 'Всем',
        'kids' => 'Детям',
        'adult' => 'Взрослым',
    ];

    /**
     * eventCategories возвращает все категории событий в порядке источника.
     *
     * @return array<string, string>
     */
    public static function eventCategories(): array
    {
        return [
            'literature' => 'Литература',
            'children' => 'Для детей',
            'tech' => 'Технологии',
            'lecture' => 'Лекции',
        ];
    }

    /**
     * tag возвращает подпись тега новости.
     */
    public static function tag($code): string
    {
        $code = (string) $code;

        return static::$tags[$code] ?? $code;
    }

    /**
     * eventCategory возвращает подпись категории события.
     */
    public static function eventCategory($code): string
    {
        $code = (string) $code;

        return static::$eventCategories[$code] ?? 'Событие';
    }

    /**
     * eventGradient возвращает класс градиента категории события.
     */
    public static function eventGradient($code): string
    {
        return static::$eventGradients[(string) $code] ?? 'bg-grad-sky';
    }

    /**
     * format возвращает подпись формата события.
     */
    public static function format($code): string
    {
        $code = (string) $code;

        return static::$formats[$code] ?? $code;
    }

    /**
     * availability возвращает подпись и класс статуса наличия.
     *
     * @return array{label: string, class: string}
     */
    public static function availability($code): array
    {
        return static::$availability[(string) $code]
            ?? ['label' => 'Нет в наличии', 'class' => 'is-unavailable'];
    }

    /**
     * age возвращает подпись возрастной группы.
     */
    public static function age($code): string
    {
        $code = (string) $code;

        return static::$ages[$code] ?? $code;
    }
}
