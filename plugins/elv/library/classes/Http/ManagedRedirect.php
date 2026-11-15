<?php namespace Elv\Library\Classes\Http;

use Closure;
use Elv\Library\Models\Redirect;
use Illuminate\Http\Request;

/**
 * ManagedRedirect — редиректы из админки (`elv_redirects`).
 *
 * Идёт после `LegacyRedirect`: сначала отдаются старые адреса Битрикса из
 * хардкод-карты, затем то, что редактор завёл руками. Неизвестный путь
 * пропускается к CMS, а недоступная база не превращает сайт в 500 — редирект
 * это удобство, а не условие работы страницы.
 */
class ManagedRedirect
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->isMethod('GET') && !$request->isMethod('HEAD')) {
            return $next($request);
        }

        try {
            $redirect = Redirect::findForPath(RequestPath::from($request));
        }
        catch (\Throwable $e) {
            return $next($request);
        }

        if ($redirect === null) {
            return $next($request);
        }

        Redirect::registerHit($redirect['id']);

        return redirect()->to(
            $this->target($redirect['to_path'], $request),
            $redirect['status_code']
        );
    }

    /**
     * target — новый адрес с параметрами исходного запроса.
     *
     * Метки (`utm_*`, `yclid`) — рабочая часть внешней ссылки, терять их нельзя.
     */
    protected function target(string $to, Request $request): string
    {
        $base = preg_match('#^https?://#i', $to)
            ? $to
            : $request->getSchemeAndHttpHost() . $to;

        $query = array_filter($request->query(), function ($value) {
            return $value !== null && $value !== '';
        });

        if (!$query) {
            return $base;
        }

        return $base . (str_contains($base, '?') ? '&' : '?') . http_build_query($query);
    }
}
