<?php namespace Nlb\Library\Classes;

use Cms\Classes\Theme;

/**
 * Pwa — оболочка прогрессивного веб-приложения.
 *
 * Файлы лежат в теме (`themes/nlb/assets/pwa/`), а отдаются с корня
 * (`/manifest.webmanifest`, `/sw.js`, `/offline.html`): service worker
 * с корня управляет всем сайтом, а из папки темы его область ограничилась бы
 * `/themes/nlb/`. Поэтому маршруты, а не статика.
 *
 * `sw.js` — шаблон: `Pwa::serviceWorker()` подставляет VERSION (хеш времени
 * правки файлов оболочки) и SHELL (список кеша). Один источник — `SHELL`
 * в этом классе, поэтому список не расходится с тем, что реально кешируется.
 */
class Pwa
{
    /**
     * SHELL — файлы оболочки, которые service worker кладёт в кеш.
     */
    public const SHELL = [
        '/offline.html',
        '/manifest.webmanifest',
        '/themes/nlb/assets/vendor/bootstrap.bundle.min.js',
        '/themes/nlb/assets/js/script.js',
        '/themes/nlb/assets/js/pwa.js',
        '/themes/nlb/assets/icons/icon-192.png',
        '/themes/nlb/assets/icons/icon-512.png',
        '/themes/nlb/assets/icons/icon-maskable-512.png',
        '/themes/nlb/assets/icons/icon.svg',
    ];

    /**
     * manifest отдаёт Web App Manifest.
     */
    public static function manifest(): string
    {
        return str_replace(
            ['__BRAND__', '__DESCRIPTION__'],
            [Brand::get('name'), Brand::get('description')],
            (string) file_get_contents(static::themeFile('assets/pwa/manifest.webmanifest'))
        );
    }

    /**
     * offline отдаёт страницу «нет подключения».
     */
    public static function offline(): string
    {
        return (string) file_get_contents(static::themeFile('assets/pwa/offline.html'));
    }

    /**
     * serviceWorker отдаёт sw.js с подставленными версией и списком оболочки.
     */
    public static function serviceWorker(): string
    {
        $template = (string) file_get_contents(static::themeFile('assets/pwa/sw.js'));

        return str_replace(
            ['__VERSION__', '__SHELL__', '__BRAND__'],
            [static::version(), (string) json_encode(static::SHELL, JSON_UNESCAPED_SLASHES), Brand::get('name')],
            $template
        );
    }

    /**
     * version — хеш времени правки файлов оболочки.
     *
     * Меняется вместе с ассетом, поэтому кеш обновляется без ручного
     * поднятия номера. Список можно передать в тесте.
     *
     * @param  array<int, string>|null  $shell
     */
    public static function version(?array $shell = null): string
    {
        $parts = [];

        foreach ($shell ?? static::SHELL as $url) {
            $file = static::pathFor($url);

            $parts[] = $url . ':' . ($file !== null && is_file($file) ? (int) filemtime($file) : 0);
        }

        return substr(sha1(implode('|', $parts)), 0, 8);
    }

    /**
     * pathFor — путь на диске для адреса оболочки.
     */
    public static function pathFor(string $url): ?string
    {
        if ($url === '/offline.html') {
            return static::themePath('assets/pwa/offline.html');
        }

        if ($url === '/manifest.webmanifest') {
            return static::themePath('assets/pwa/manifest.webmanifest');
        }

        if (str_starts_with($url, '/themes/')) {
            return base_path(ltrim($url, '/'));
        }

        return null;
    }

    /**
     * themeFile — существующий файл темы или исключение.
     */
    protected static function themeFile(string $relative): string
    {
        $path = static::themePath($relative);

        if ($path === null || !is_file($path)) {
            throw new \RuntimeException('PWA: нет файла темы ' . $relative);
        }

        return $path;
    }

    /**
     * themePath — путь внутри активной темы.
     */
    protected static function themePath(string $relative): ?string
    {
        $theme = Theme::getActiveTheme();

        return $theme ? rtrim($theme->getPath(), '/\\') . '/' . ltrim($relative, '/') : null;
    }
}
