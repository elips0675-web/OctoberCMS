<?php

/**
 * Отдача статики локальным сервером: gzip для текста, кеш как у nginx.
 *
 * Подключается из `server.php` (роутер `php artisan serve`). На проде не
 * используется: там `nginx.conf` с `gzip` и `expires`.
 */

$path = realpath(getcwd() . urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/'));
$root = realpath(getcwd());

if (!$path || !$root || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
    http_response_code(404);
    return true;
}

$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

$types = [
    'html' => 'text/html; charset=UTF-8',
    'css' => 'text/css; charset=UTF-8',
    'js' => 'application/javascript; charset=UTF-8',
    'mjs' => 'application/javascript; charset=UTF-8',
    'json' => 'application/json; charset=UTF-8',
    'webmanifest' => 'application/manifest+json; charset=UTF-8',
    'xml' => 'application/xml; charset=UTF-8',
    'txt' => 'text/plain; charset=UTF-8',
    'svg' => 'image/svg+xml',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
    'avif' => 'image/avif',
    'gif' => 'image/gif',
    'ico' => 'image/x-icon',
    'woff' => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf' => 'font/ttf',
    'map' => 'application/json; charset=UTF-8',
    'pdf' => 'application/pdf',
    'mp4' => 'video/mp4',
];

$compressible = ['html', 'css', 'js', 'mjs', 'json', 'webmanifest', 'xml', 'txt', 'svg', 'map'];

$contentType = $types[$extension] ?? (function_exists('mime_content_type') ? (mime_content_type($path) ?: 'application/octet-stream') : 'application/octet-stream');

$lastModified = filemtime($path) ?: time();
$size = filesize($path) ?: 0;
$etag = '"' . dechex($lastModified) . '-' . dechex($size) . '"';

header('Content-Type: ' . $contentType);
header('Cache-Control: ' . (in_array($extension, $compressible, true) ? 'public, max-age=31536000, immutable' : 'public, max-age=31536000, immutable'));
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');

$acceptsGzip = stripos((string) ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''), 'gzip') !== false;

if ($acceptsGzip) {
    header('Vary: Accept-Encoding');
}

$notModified = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag;
if ($notModified) {
    http_response_code(304);
    return true;
}

if ($acceptsGzip && in_array($extension, $compressible, true) && $size >= 1024) {
    $compressed = gzencode((string) file_get_contents($path), 6);

    if ($compressed !== false && strlen($compressed) < $size) {
        header('Content-Encoding: gzip');
        header('Content-Length: ' . strlen($compressed));
        echo $compressed;
        return true;
    }
}

header('Content-Length: ' . $size);
readfile($path);

return true;
