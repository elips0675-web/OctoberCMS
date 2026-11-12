<?php namespace Nlb\Library\Classes\Http;

use Illuminate\Http\Response;
use Nlb\Library\Classes\Rss;
use Nlb\Library\Classes\Seo;
use Nlb\Library\Classes\Sitemap;

/**
 * SeoController — служебные SEO-адреса: карта сайта и robots.txt.
 *
 * Оба файла генерируются, а не лежат в `public/`: адрес сайта появляется
 * только на M17, а директива `Sitemap` и `<loc>` с чужим доменом уводят
 * поисковики на чужой сайт. Домен берётся из запроса — как в источнике
 * (`sitemap.php`).
 */
class SeoController
{
    /**
     * sitemap отдаёт `/sitemap.xml`.
     */
    public function sitemap(): Response
    {
        return response(Sitemap::xml(url('/')), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    /**
     * rss отдаёт `/rss.xml` — лента новостей.
     */
    public function rss(): Response
    {
        return response(Rss::xml(url('/')), 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
        ]);
    }

    /**
     * robots отдаёт `/robots.txt`.
     */
    public function robots(): Response
    {
        return response(Seo::robots(url('/sitemap.xml')), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
