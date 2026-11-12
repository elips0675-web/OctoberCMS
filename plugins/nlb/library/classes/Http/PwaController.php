<?php namespace Nlb\Library\Classes\Http;

use Illuminate\Http\Response;
use Nlb\Library\Classes\Pwa;

/**
 * PwaController — оболочка приложения с корня сайта.
 *
 * Service worker обязан жить в корне: иначе его область — только папка,
 * из которой он загружен (`/themes/nlb/`). Manifest и офлайн-страница
 * отдаются там же по тем же причинам: адреса предсказуемы, а файлы остаются
 * в теме рядом с ассетами.
 */
class PwaController
{
    /**
     * manifest отдаёт `/manifest.webmanifest`.
     */
    public function manifest(): Response
    {
        return response(Pwa::manifest(), 200, [
            'Content-Type' => 'application/manifest+json; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * serviceWorker отдаёт `/sw.js` без долгого кеша.
     */
    public function serviceWorker(): Response
    {
        return response(Pwa::serviceWorker(), 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-cache',
            'Service-Worker-Allowed' => '/',
        ]);
    }

    /**
     * offline отдаёт `/offline.html`.
     */
    public function offline(): Response
    {
        return response(Pwa::offline(), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
