<?php namespace Elv\Library\Classes;

use Cms\Classes\Theme;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Media — адаптивные картинки и встроенный CSS темы.
 *
 * Перенос elv_media.php из источника: <picture> с AVIF/WebP и JPG-фолбэком,
 * кеш вариантов на время запроса, минификация CSS без внешних зависимостей.
 */
class Media
{
    /**
     * @var array<string, array>
     */
    protected static $variantCache = [];

    /**
     * @var array<string, string>
     */
    protected static $cssCache = [];

    /**
     * themePath возвращает абсолютный путь файла темы.
     */
    public static function themePath(string $relative): string
    {
        $theme = Theme::getActiveTheme();
        $root = $theme ? $theme->getPath() : base_path('themes/elv');

        return $root . '/' . ltrim($relative, '/');
    }

    /**
     * themeUrl превращает путь внутри темы в адрес от корня сайта.
     */
    public static function themeUrl(string $relative): string
    {
        $theme = Theme::getActiveTheme();
        $code = $theme ? $theme->getDirName() : 'elv';

        return '/themes/' . $code . '/' . ltrim($relative, '/');
    }

    /**
     * imagePath достаёт путь из значения mediafinder.
     *
     * Tailor хранит картинку как JSON-строку ("assets/images/news-1.jpg"),
     * поэтому значение сначала декодируется, а уже потом используется.
     */
    public static function imagePath($value): string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        if (!is_string($value)) {
            return '';
        }

        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $first = $value[0];
        if ($first === '"' || $first === '[' || $first === '{') {
            $decoded = json_decode($value, true);
            if (is_string($decoded)) {
                return $decoded;
            }
            if (is_array($decoded)) {
                $decoded = reset($decoded);

                return is_string($decoded) ? $decoded : '';
            }
        }

        return $value;
    }

    /**
     * picture выводит <picture> с AVIF/WebP и JPG-фолбэком.
     *
     * $options: class, sizes, loading, decoding, priority, fetchpriority.
     */
    public static function picture($src, string $alt = '', array $options = []): string
    {
        $src = static::imagePath($src);
        if ($src === '') {
            return '';
        }

        $class = (string) ($options['class'] ?? '');
        $sizes = (string) ($options['sizes'] ?? '100vw');
        $loading = (string) ($options['loading'] ?? 'lazy');
        $decoding = (string) ($options['decoding'] ?? 'async');
        $priority = !empty($options['priority']);
        $fetchPriority = (string) ($options['fetchpriority'] ?? '');

        if ($priority) {
            $loading = 'eager';
        } elseif ($loading !== 'eager' && $fetchPriority === '') {
            $fetchPriority = 'low';
        }

        $url = static::themeUrl($src);
        $attrs = '';
        if ($class !== '') {
            $attrs .= ' class="' . static::escape($class) . '"';
        }
        $attrs .= ' src="' . static::escape($url) . '"';
        $attrs .= ' alt="' . static::escape($alt) . '"';
        $attrs .= ' loading="' . static::escape($loading) . '"';
        $attrs .= ' decoding="' . static::escape($decoding) . '"';
        if ($priority) {
            $attrs .= ' fetchpriority="high"';
        } elseif ($fetchPriority !== '') {
            $attrs .= ' fetchpriority="' . static::escape($fetchPriority) . '"';
        }

        $variants = static::variants($src);
        if ($variants === []) {
            return '<img' . $attrs . '>';
        }

        $sizesAttribute = $sizes !== '' ? ' sizes="' . static::escape($sizes) . '"' : '';
        $jpegSrcset = static::srcset($variants, 'jpg');
        if ($jpegSrcset !== '') {
            $attrs .= ' srcset="' . static::escape($jpegSrcset) . '"';
        }

        $sources = '';
        foreach (['avif' => 'image/avif', 'webp' => 'image/webp'] as $format => $type) {
            $srcset = static::srcset($variants, $format);
            if ($srcset === '') {
                continue;
            }
            $sources .= '<source type="' . $type . '" srcset="' . static::escape($srcset) . '"'
                . $sizesAttribute . '>' . "\n";
        }

        return '<picture class="elv-picture">' . "\n"
            . $sources
            . '<img' . $attrs . $sizesAttribute . '>' . "\n"
            . '</picture>';
    }

    /**
     * variants собирает варианты ширины и форматов для исходника.
     *
     * @return array{full: array, widths: array}|array
     */
    public static function variants(string $src): array
    {
        if (array_key_exists($src, static::$variantCache)) {
            return static::$variantCache[$src];
        }

        static::$variantCache[$src] = [];

        if (!preg_match('~\.jpe?g$~i', $src)) {
            return [];
        }

        $file = static::themePath($src);
        if (!is_file($file)) {
            return [];
        }

        $size = @getimagesize($file);
        if (!$size) {
            return [];
        }

        $width = (int) $size[0];
        $stemUrl = (string) preg_replace('~\.jpe?g$~i', '', $src);
        $stemFile = (string) preg_replace('~\.jpe?g$~i', '', $file);

        $pick = static function (string $suffix) use ($stemUrl, $stemFile): string {
            return is_file($stemFile . $suffix) ? $stemUrl . $suffix : '';
        };

        $full = [
            'width' => $width,
            'jpg' => $src,
            'webp' => $pick('.webp'),
            'avif' => $pick('.avif'),
        ];

        $widths = [];
        foreach ([480, 768, 960, 1280] as $target) {
            if ($target >= $width || !is_file($stemFile . '-' . $target . '.jpg')) {
                continue;
            }
            $widths[] = [
                'width' => $target,
                'jpg' => $stemUrl . '-' . $target . '.jpg',
                'webp' => $pick('-' . $target . '.webp'),
                'avif' => $pick('-' . $target . '.avif'),
            ];
        }

        if ($widths === [] && $full['webp'] === '' && $full['avif'] === '') {
            return [];
        }

        return static::$variantCache[$src] = ['full' => $full, 'widths' => $widths];
    }

    /**
     * srcset собирает строку srcset для указанного формата.
     */
    public static function srcset(array $variants, string $format): string
    {
        $parts = [];
        foreach ($variants['widths'] as $variant) {
            if (($variant[$format] ?? '') !== '') {
                $parts[] = static::themeUrl($variant[$format]) . ' ' . $variant['width'] . 'w';
            }
        }

        $full = $variants['full'];
        if (($full[$format] ?? '') !== '') {
            $parts[] = static::themeUrl($full[$format]) . ' ' . $full['width'] . 'w';
        }

        return implode(', ', $parts);
    }

    /**
     * minifyCss сжимает CSS, не трогая строки, url() и calc().
     */
    public static function minifyCss(string $css): string
    {
        $out = '';
        $length = strlen($css);
        $quote = '';
        $paren = 0;
        $space = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $css[$i];

            if ($quote !== '') {
                $out .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $out .= $css[++$i];
                    continue;
                }
                if ($char === $quote) {
                    $quote = '';
                }
                continue;
            }

            if ($char === '/' && $i + 1 < $length && $css[$i + 1] === '*') {
                $end = strpos($css, '*/', $i + 2);
                $i = $end === false ? $length : $end + 1;
                $space = true;
                continue;
            }

            if ($char === '"' || $char === "'") {
                if ($space && $out !== '' && !in_array(substr($out, -1), ['{', '}', ';', ','], true)) {
                    $out .= ' ';
                }
                $out .= $char;
                $quote = $char;
                $space = false;
                continue;
            }

            if ($char === '(') {
                $paren++;
            } elseif ($char === ')') {
                $paren = max(0, $paren - 1);
            }

            if (ctype_space($char)) {
                $space = true;
                continue;
            }

            if ($space && $out !== '') {
                $previous = substr($out, -1);
                $drop = in_array($previous, ['{', '}', ';', ','], true)
                    || ($paren === 0 && in_array($char, ['{', '}', ';', ','], true));
                if (!$drop) {
                    $out .= ' ';
                }
            }

            $out .= $char;
            $space = false;
        }

        return $out;
    }

    /**
     * inlineCss отдаёт сжатый CSS файла темы, кешируя по mtime и размеру.
     */
    public static function inlineCss(string $relative): string
    {
        if (array_key_exists($relative, static::$cssCache)) {
            return static::$cssCache[$relative];
        }

        $file = static::themePath($relative);
        if (!is_file($file)) {
            return static::$cssCache[$relative] = '';
        }

        clearstatcache(true, $file);
        $key = 'elv-css-' . md5($relative . '|' . filemtime($file) . '|' . filesize($file));

        return static::$cssCache[$relative] = (string) Cache::rememberForever(
            $key,
            static function () use ($file): string {
                return Media::minifyCss((string) File::get($file));
            }
        );
    }

    /**
     * num форматирует целое с пробелом как разделителем тысяч.
     */
    public static function num($value): string
    {
        return number_format((int) $value, 0, '', ' ');
    }

    /**
     * price превращает число в «от 350 ₽», ноль — в пустую строку.
     */
    public static function price($value): string
    {
        $number = (int) $value;
        if ($number <= 0) {
            return '';
        }

        return 'от ' . static::num($number) . ' ₽';
    }

    /**
     * money выводит сумму без приставки «от».
     */
    public static function money($value): string
    {
        $number = (int) $value;

        return static::num($number) . ' ₽';
    }

    /**
     * coverColor выбирает цвет обложки сувенира по названию.
     *
     * Цвет не хранится в БД источника — он считается от crc32 названия,
     * поэтому хелпер повторяет ту же формулу, что и эталон.
     */
    public static function coverColor($title): string
    {
        $palette = ['primary', 'green', 'sky', 'pink', 'orange'];

        return $palette[abs(crc32((string) $title)) % count($palette)];
    }

    /**
     * collectionColor переводит цвет записи в суффикс CSS-класса.
     */
    public static function collectionColor($color): string
    {
        $map = [
            'primary' => 'blue',
            'green' => 'green',
            'sky' => 'sky',
            'pink' => 'violet',
            'orange' => 'orange',
        ];

        return $map[(string) $color] ?? 'blue';
    }

    /**
     * priceLabel выбирает подпись цены услуги.
     */
    public static function priceLabel($label, $from = 0): string
    {
        $label = trim((string) $label);
        if ($label !== '') {
            return $label;
        }

        $price = static::price($from);

        return $price !== '' ? $price : 'Бесплатно для читателей';
    }

    /**
     * schedule делит строки «День: часы» на пары.
     *
     * @return array<int, array{day: string, time: string}>
     */
    public static function schedule($value): array
    {
        $rows = [];
        foreach (static::lines($value) as $line) {
            $parts = explode(':', $line, 2);
            $rows[] = [
                'day' => trim($parts[0]),
                'time' => trim((string) ($parts[1] ?? '')),
            ];
        }

        return $rows;
    }

    /**
     * phoneLink оставляет в телефоне только цифры и плюс для href="tel:".
     */
    public static function phoneLink($value): string
    {
        return (string) preg_replace('~[^+\d]~', '', (string) $value);
    }

    /**
     * dateRu форматирует дату по-русски: «6 ноября 2026».
     */
    public static function dateRu($value, string $format = 'j F Y'): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $date = $value instanceof \DateTimeInterface
            ? \Carbon\Carbon::instance($value)
            : \Carbon\Carbon::parse((string) $value);

        return $date->locale('ru')->translatedFormat($format);
    }

    /**
     * value приводит значение поля к строке без null.
     */
    public static function value($value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map('strval', $value));
        }

        return trim((string) $value);
    }

    /**
     * lines режет текстовое поле на непустые строки.
     *
     * @return array<int, string>
     */
    public static function lines($value): array
    {
        $value = static::value($value);
        if ($value === '') {
            return [];
        }

        $lines = array_map('trim', preg_split('~\r\n|\r|\n~', $value) ?: []);

        return array_values(array_filter($lines, static function ($line) {
            return $line !== '';
        }));
    }

    protected static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
