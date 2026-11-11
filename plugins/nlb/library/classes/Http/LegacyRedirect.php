<?php namespace Nlb\Library\Classes\Http;

use Closure;
use Illuminate\Http\Request;
use Nlb\Library\Classes\Sitemap;

/**
 * LegacyRedirect — 301 со старых адресов 1С-Битрикс на новые.
 *
 * Старое ядро отдавало разделы через файлы, а детальные страницы — параметром:
 * `/news/detail.php?CODE=slug`, `/about/index.php`, `/index.php`, `/sitemap.php`.
 * Внешние ссылки и закладки на эти адреса не должны превращаться в 404 после
 * переезда, поэтому каждый старый вид получает постоянный редирект на новый.
 *
 * Параметры запроса, кроме `CODE`, сохраняются (`utm_source` и прочие метки —
 * рабочая часть ссылки). Неизвестные пути не трогаются: их отдаёт CMS.
 */
class LegacyRedirect
{
    /**
     * INDEX_PAGES — разделы, у которых в источнике был `index.php`.
     */
    public const INDEX_PAGES = [
        'about',
        'auth',
        'books',
        'collections',
        'contacts',
        'events',
        'feedback',
        'news',
        'personal',
        'printing',
        'privacy',
        'rules',
        'search',
        'services',
        'sitemap',
        'subscribe',
        'terms',
    ];

    /**
     * handle — проверяет адрес и отдаёт 301 или пропускает запрос дальше.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (!$request->isMethod('GET') && !$request->isMethod('HEAD')) {
            return $next($request);
        }

        $target = $this->target($this->rawPath($request), $request);

        if ($target === null) {
            return $next($request);
        }

        return redirect()->to($target, 301);
    }

    /**
     * rawPath — путь из REQUEST_URI, а не из `path()`.
     *
     * `/index.php` — это `SCRIPT_NAME`, поэтому Symfony срезает его как базовый
     * путь и `path()` отдаёт `/`: без сырого URI старый адрес не отличить от
     * главной и редирект не сработал бы.
     */
    protected function rawPath(Request $request): string
    {
        $uri = (string) $request->server('REQUEST_URI', '/');
        $path = parse_url($uri, PHP_URL_PATH);

        if (!is_string($path) || $path === '') {
            return '/';
        }

        return '/' . ltrim(urldecode($path), '/');
    }

    /**
     * target — новый адрес для старого пути или null, если путь не старый.
     */
    protected function target(string $path, Request $request): ?string
    {
        $host = $request->getSchemeAndHttpHost();

        if ($path === '/index.php') {
            return $this->withQuery($host . '/', $request, ['CODE']);
        }

        if ($path === '/sitemap.php') {
            return $host . '/sitemap.xml';
        }

        if (preg_match('#^/([a-z0-9-]+)/index\.php$#', $path, $matches)
            && in_array($matches[1], static::INDEX_PAGES, true)
        ) {
            return $this->withQuery($host . '/' . $matches[1] . '/', $request, ['CODE']);
        }

        if (preg_match('#^/([a-z0-9-]+)/detail\.php$#', $path, $matches)
            && array_key_exists($matches[1], Sitemap::SECTIONS)
        ) {
            $prefix = $matches[1];
            $slug = trim((string) $request->query('CODE', ''));

            if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
                return $this->withQuery($host . '/' . $prefix . '/', $request, ['CODE']);
            }

            return $this->withQuery($host . '/' . $prefix . '/' . $slug . '/', $request, ['CODE']);
        }

        return null;
    }

    /**
     * withQuery переносит остальные параметры запроса в новый адрес.
     *
     * @param  array<int, string>  $drop
     */
    protected function withQuery(string $target, Request $request, array $drop = []): string
    {
        $query = array_diff_key($request->query(), array_flip($drop));
        $query = array_filter($query, function ($value) {
            return $value !== null && $value !== '';
        });

        if (!$query) {
            return $target;
        }

        return $target . '?' . http_build_query($query);
    }
}
