<?php namespace Nlb\Library\Classes\Http;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CompressResponse — gzip для текстовых ответов и кеш-заголовки для ассетов.
 *
 * Нужен там, где перед PHP нет nginx с gzip: `php artisan serve`, shared-хостинг
 * без настроенного сжатия. С nginx middleware безвреден: уже сжатый ответ он
 * не трогает (сравнивает Accept-Encoding и наличие Content-Encoding).
 */
class CompressResponse
{
    protected const COMPRESSIBLE = [
        'text/html',
        'text/css',
        'text/plain',
        'text/xml',
        'text/javascript',
        'application/javascript',
        'application/json',
        'application/xml',
        'application/rss+xml',
        'application/ld+json',
        'image/svg+xml',
    ];

    protected const MIN_LENGTH = 1024;

    protected const COMPRESSION_LEVEL = 6;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!$this->isCompressible($request, $response)) {
            return $this->withAssetsCache($request, $response);
        }

        $content = $response->getContent();
        $compressed = gzencode($content, self::COMPRESSION_LEVEL);

        if ($compressed === false || strlen($compressed) >= strlen($content)) {
            return $this->withAssetsCache($request, $response);
        }

        $response->setContent($compressed);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Content-Length', (string) strlen($compressed));
        $response->headers->set('Vary', 'Accept-Encoding', false);

        return $this->withAssetsCache($request, $response);
    }

    protected function isCompressible(Request $request, Response $response): bool
    {
        if (!$response instanceof Response || $response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) {
            return false;
        }

        if ($response->headers->has('Content-Encoding')) {
            return false;
        }

        if (!$this->clientAcceptsGzip($request->headers->get('Accept-Encoding', ''))) {
            return false;
        }

        $content = $response->getContent();
        if (!is_string($content) || strlen($content) < self::MIN_LENGTH) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');

        foreach (self::COMPRESSIBLE as $type) {
            if (stripos($contentType, $type) !== false) {
                return true;
            }
        }

        return false;
    }

    protected function clientAcceptsGzip(string $header): bool
    {
        if (stripos($header, 'gzip') === false) {
            return false;
        }

        if (preg_match('/gzip\s*;\s*q=([0-9.]+)/i', $header, $matches)) {
            return (float) $matches[1] > 0;
        }

        return true;
    }

    protected function withAssetsCache(Request $request, Response $response): Response
    {
        if ($response->headers->has('Cache-Control')) {
            return $response;
        }

        $path = '/' . ltrim($request->path(), '/');
        $maxAge = $this->assetMaxAge($path);

        if ($maxAge === null) {
            return $response;
        }

        $response->headers->set('Cache-Control', 'public, max-age=' . $maxAge . ', immutable');

        return $response;
    }

    protected function assetMaxAge(string $path): ?int
    {
        if (preg_match('#^/(themes|plugins|modules)/[^/]+/(assets|resources)/#', $path)) {
            return 31536000;
        }

        if (str_starts_with($path, '/storage/app/')) {
            return 604800;
        }

        return null;
    }
}
