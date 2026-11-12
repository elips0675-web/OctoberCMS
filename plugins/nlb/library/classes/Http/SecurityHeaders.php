<?php namespace Nlb\Library\Classes\Http;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SecurityHeaders — CSP и защитные заголовки для публичных страниц.
 *
 * Источник (`nlb_security.php`, 77 строк) отдавал CSP, X-Frame-Options,
 * Referrer-Policy и Permissions-Policy. В October заголовки обязан ставить
 * периметр (nginx), но shared-хостинг и `php artisan serve` остаются без него,
 * поэтому логика дублируется middleware'ом: nginx скрывает эти же заголовки
 * от PHP и добавляет свои (`nginx.conf`), а CSP живёт только здесь — в ней
 * одноразовый nonce, который nginx знать не может.
 *
 * Админка исключена из CSP: October подключает инлайновые скрипты и eval
 * в бэкенде, и строгая политика ломает редакторы и виджеты. Для неё остаются
 * статические заголовки с X-Frame-Options SAMEORIGIN (всплывающие окна).
 */
class SecurityHeaders
{
    protected const NONCE_ATTRIBUTE = 'nlb_csp_nonce';

    protected static ?string $nonce = null;

    public function handle(Request $request, Closure $next): Response
    {
        self::$nonce = base64_encode(random_bytes(16));
        $request->attributes->set(self::NONCE_ATTRIBUTE, self::$nonce);

        $response = $next($request);

        if (!$response instanceof Response) {
            return $response;
        }

        return $this->apply($request, $response);
    }

    /**
     * nonce — значение для инлайновых скриптов страницы.
     */
    public static function nonce(): string
    {
        return self::$nonce ?? '';
    }

    /**
     * reset — сбрасывает nonce между запросами в тестах.
     */
    public static function reset(): void
    {
        self::$nonce = null;
    }

    protected function apply(Request $request, Response $response): Response
    {
        $headers = $response->headers;
        $backend = $this->isBackend($request);

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $headers->set('X-Frame-Options', $backend ? 'SAMEORIGIN' : 'DENY');

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (!$backend) {
            $headers->set('Content-Security-Policy', $this->policy($request));
        }

        return $response;
    }

    protected function isBackend(Request $request): bool
    {
        $uri = trim((string) config('backend.uri', 'admin'), '/');

        if ($uri === '') {
            return false;
        }

        return $request->is($uri) || $request->is($uri . '/*');
    }

    protected function policy(Request $request): string
    {
        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "img-src 'self' data:",
            "font-src 'self'",
            "style-src 'self' 'unsafe-inline'",
            "script-src 'self' 'nonce-" . self::nonce() . "'",
            "connect-src 'self'",
            "media-src 'self'",
            "manifest-src 'self'",
            "worker-src 'self'",
        ];

        if ($request->isSecure()) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }
}
