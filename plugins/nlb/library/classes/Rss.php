<?php namespace Nlb\Library\Classes;

/**
 * Rss — лента новостей `/rss.xml` в формате RSS 2.0.
 *
 * Источник отдавал `/news/rss/`; адрес сохранён смыслом, но не путём: лента
 * одна на сайт, поэтому `/rss.xml` — как у большинства статических сайтов.
 * Записи берутся из того же `Content::records()`, что и страница новостей,
 * поэтому отключённая или удалённая новость в ленту не попадает.
 *
 * Сортировка — по дате публикации (свежие сверху), а не по `sort` из админки:
 * лента читается подрядчиком, порядок в списке новостей для неё неважен.
 * Дата без `published_at` откатывается на `created_at`, иначе такая запись
 * уехала бы в конец ленты навсегда.
 */
class Rss
{
    /**
     * HANDLE — Tailor-раздел, из которого собирается лента.
     */
    public const HANDLE = 'Nlb\News';

    /**
     * PREFIX — адрес списка новостей.
     */
    public const PREFIX = '/news/';

    /**
     * LIMIT — сколько записей отдавать: RSS-читалки не любят простыни.
     */
    public const LIMIT = 20;

    /**
     * xml собирает готовый документ ленты.
     */
    public static function xml(string $baseUrl): string
    {
        $base = rtrim($baseUrl, '/');

        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $out .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        $out .= "  <channel>\n";
        $out .= '    <title>' . static::escape(Seo::siteName() . ' — новости') . "</title>\n";
        $out .= '    <link>' . static::escape($base . static::PREFIX) . "</link>\n";
        $out .= '    <description>' . static::escape('Новости: услуги, встречи и события.') . "</description>\n";
        $out .= "    <language>ru</language>\n";
        $out .= '    <lastBuildDate>' . date(DATE_RSS) . "</lastBuildDate>\n";
        $out .= '    <atom:link href="' . static::escape($base . '/rss.xml') . '" rel="self" type="application/rss+xml"/>' . "\n";

        foreach (static::items() as $record) {
            $link = $base . static::PREFIX . trim((string) $record->slug) . '/';
            $title = trim((string) $record->title);
            $description = trim((string) $record->excerpt);

            if ($description === '') {
                $description = static::plain((string) $record->content);
            }

            $out .= "    <item>\n";
            $out .= '      <title>' . static::escape($title) . "</title>\n";
            $out .= '      <link>' . static::escape($link) . "</link>\n";
            $out .= '      <guid isPermaLink="true">' . static::escape($link) . "</guid>\n";
            $out .= '      <pubDate>' . static::date($record) . "</pubDate>\n";

            if ($description !== '') {
                $out .= '      <description>' . static::escape($description) . "</description>\n";
            }

            $out .= "    </item>\n";
        }

        $out .= "  </channel>\n";
        $out .= '</rss>' . "\n";

        return $out;
    }

    /**
     * items — опубликованные новости, свежие сверху.
     *
     * @return array<int, \Tailor\Models\EntryRecord>
     */
    public static function items(): array
    {
        $records = Content::records(static::HANDLE);

        usort($records, function ($left, $right) {
            $order = static::timestamp($right) <=> static::timestamp($left);

            return $order !== 0 ? $order : strcmp((string) $left->slug, (string) $right->slug);
        });

        return array_slice($records, 0, static::LIMIT);
    }

    /**
     * date — дата записи в формате RFC 2822.
     */
    protected static function date($record): string
    {
        $timestamp = static::timestamp($record);

        return $timestamp > 0 ? date(DATE_RSS, $timestamp) : date(DATE_RSS);
    }

    /**
     * timestamp — момент публикации записи.
     */
    protected static function timestamp($record): int
    {
        $value = $record->published_at ?: $record->created_at;

        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        return $value ? (int) strtotime((string) $value) : 0;
    }

    /**
     * plain — текст без разметки для описания новости.
     */
    protected static function plain(string $html): string
    {
        $spaced = preg_replace('/<[^>]+>/u', ' ', $html) ?? $html;
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($spaced)) ?? '');

        if (mb_strlen($text) <= 300) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, 300)) . '…';
    }

    /**
     * escape — экранирование для XML.
     */
    protected static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
