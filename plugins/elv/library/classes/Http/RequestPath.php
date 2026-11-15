<?php namespace Elv\Library\Classes\Http;

use Illuminate\Http\Request;

/**
 * RequestPath — путь запроса из сырого `REQUEST_URI`.
 *
 * Symfony срезает `SCRIPT_NAME` (`/index.php`) как базовый путь, поэтому
 * `path()` отдаёт `/` и старый адрес главной не отличить от самой главной.
 * Здесь путь берётся из сырого URI, декодируется и приводится к виду
 * `/сегмент/сегмент` — один источник правды для `LegacyRedirect`
 * и `ManagedRedirect`.
 */
class RequestPath
{
    public static function from(Request $request): string
    {
        $uri = (string) $request->server('REQUEST_URI', '/');
        $path = parse_url($uri, PHP_URL_PATH);

        if (!is_string($path) || $path === '') {
            return '/';
        }

        return '/' . ltrim(urldecode($path), '/');
    }
}
