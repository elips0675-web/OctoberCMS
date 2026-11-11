<?php

/**
 * Роутер для `php artisan serve`: статика отдаётся со сжатием и кешем, как в проде.
 *
 * Laravel подхватывает этот файл автоматически, если он лежит в корне проекта
 * (`ServeCommand` проверяет `base_path('server.php')`). Нужен потому, что
 * стандартный роутер отдаёт файлы напрямую, без gzip и без `Cache-Control`:
 * локальный Lighthouse тогда меряет не то, что увидит посетитель за nginx.
 *
 * На проде файл не участвует: там статику отдаёт nginx (`nginx.conf`, gzip + expires).
 */

$publicPath = getcwd();
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');

if ($uri !== '/' && !str_contains($uri, "\0")) {
    $path = realpath($publicPath . $uri);
    $root = realpath($publicPath);
    $extension = strtolower(pathinfo($uri, PATHINFO_EXTENSION));

    if (
        $path
        && $root
        && str_starts_with($path, $root . DIRECTORY_SEPARATOR)
        && is_file($path)
        && !in_array($extension, ['php', 'phtml', 'phar'], true)
    ) {
        return require __DIR__ . '/tools/serve-static.php';
    }
}

require_once $publicPath . '/index.php';
