<?php namespace Elv\Library\Classes;

use Str;
use MediaLibrary;
use Tailor\Models\StructureRecord;

/**
 * PageBuilder — страница, собранная из блоков Tailor.
 *
 * Модель данных — blueprint `Elv\Page` (type `structure`): у записи есть
 * заголовок, адрес и повторитель `blocks` с группами из `Elv\Blocks`.
 * Здесь только чтение и подготовка данных к рендеру: шаблоны не знают ни про
 * Tailor-модели, ни про то, как хранятся группы повторителя.
 *
 * Три правила, которые держат конструктор в рамках проекта:
 *
 * 1. Белый список блоков и разделов: ключи групп совпадают с именами
 *    партиалов `themes/elv/partials/blocks/*.htm`, ключи разделов — с
 *    `SOURCES`. Неизвестная группа пропускается, а не роняет страницу.
 * 2. Отключённая страница не отдаётся: выборка идёт через
 *    `applyVisibleFrontend()` — тот же фильтр, что у новостей и книг.
 * 3. Блок «сетка записей» берёт записи только через `Content::records()`:
 *    второй источник выборки означал бы второй фильтр опубликованности.
 */
class PageBuilder
{
    /**
     * @var string SECTION blueprint-раздел страниц
     */
    public const SECTION = 'Elv\\Page';

    /**
     * @var int MAX_LIMIT потолок записей в блоке «сетка»
     */
    public const MAX_LIMIT = 12;

    /**
     * @var array<int, string> GROUPS группы повторителя, они же имена партиалов
     */
    public const GROUPS = ['text', 'text-image', 'quote', 'records', 'video', 'toc'];

    /**
     * @var array<string, array<string, string>> SOURCES разделы для блока «сетка»
     */
    public const SOURCES = [
        'books' => [
            'handle' => 'Elv\\Book',
            'card' => 'cards/book',
            'title' => 'Сувениры и подарки',
            'url' => '/books/',
        ],
        'collections' => [
            'handle' => 'Elv\\Collection',
            'card' => 'cards/collection',
            'title' => 'Подборки',
            'url' => '/collections/',
        ],
        'events' => [
            'handle' => 'Elv\\Event',
            'card' => 'cards/event',
            'title' => 'События',
            'url' => '/events/',
        ],
        'news' => [
            'handle' => 'Elv\\News',
            'card' => 'cards/news',
            'title' => 'Новости',
            'url' => '/news/',
        ],
        'printing' => [
            'handle' => 'Elv\\Printing',
            'card' => 'cards/print',
            'title' => 'Печать',
            'url' => '/printing/',
        ],
        'services' => [
            'handle' => 'Elv\\Service',
            'card' => 'cards/service',
            'title' => 'Услуги',
            'url' => '/services/',
        ],
    ];

    /**
     * @var array<string, array<int, string>> FIELDS поля каждой группы
     */
    public const FIELDS = [
        'text' => ['title', 'body'],
        'text-image' => ['title', 'body', 'image', 'image_position', 'caption'],
        'quote' => ['kind', 'text', 'author'],
        'records' => ['title', 'source', 'limit'],
        'video' => ['title', 'file', 'poster', 'caption'],
        'toc' => ['title', 'include_h3'],
    ];

    /**
     * @var array<string, array|null> cache выборок по адресу
     */
    protected static $cache = [];

    /**
     * @var array<int, array{slug: string, lastmod: string}>|null cache для карты сайта
     */
    protected static $pages;

    /**
     * flush сбрасывает кеш выборок между тестами и импортом.
     */
    public static function flush(): void
    {
        static::$cache = [];
        static::$pages = null;
    }

    /**
     * pages отдаёт опубликованные страницы конструктора для карты сайта.
     *
     * Отдельный список нужен потому, что статические страницы темы перечислены
     * в `Sitemap::PAGES` руками, а собранные в админке появляются и исчезают
     * без правки кода — карта сайта обязана видеть их через БД.
     *
     * @return array<int, array{slug: string, lastmod: string}>
     */
    public static function pages(): array
    {
        if (static::$pages !== null) {
            return static::$pages;
        }

        $pages = [];

        $records = StructureRecord::inSection(static::SECTION)
            ->applyVisibleFrontend()
            ->whereNull('parent_id')
            ->get();

        foreach ($records as $page) {
            $slug = trim((string) $page->slug);

            if ($slug === '') {
                continue;
            }

            $pages[] = [
                'slug' => $slug,
                'lastmod' => date('Y-m-d', strtotime((string) $page->updated_at ?: 'now')),
            ];
        }

        return static::$pages = $pages;
    }

    /**
     * find находит опубликованную страницу по адресу.
     *
     * Берутся только страницы верхнего уровня: адрес страницы — `/slug/`,
     * вложенность в конструкторе не поддерживается (`structure.maxDepth: 1`),
     * иначе `fullslug` перестал бы совпадать с адресом.
     */
    public static function find(string $slug): ?StructureRecord
    {
        $slug = trim($slug);

        if ($slug === '') {
            return null;
        }

        if (array_key_exists($slug, static::$cache)) {
            return static::$cache[$slug];
        }

        $page = StructureRecord::inSection(static::SECTION)
            ->applyVisibleFrontend()
            ->where('slug', $slug)
            ->whereNull('parent_id')
            ->first();

        return static::$cache[$slug] = $page;
    }

    /**
     * prepare готовит блоки к рендеру.
     *
     * Возвращает `rows` (по строке на блок: `group`, `id`, `data`) и `toc`
     * (оглавление страницы, собранное из подзаголовков всех текстовых блоков).
     *
     * @return array{rows: array<int, array>, toc: array<int, array>}
     */
    public static function prepare(?StructureRecord $page): array
    {
        $rows = [];
        $toc = [];
        $used = [];

        if (!$page) {
            return ['rows' => [], 'toc' => []];
        }

        foreach ($page->blocks as $item) {
            $group = (string) $item->content_group;

            if (!in_array($group, static::GROUPS, true)) {
                continue;
            }

            $data = static::data($group, $item);
            $data = static::applyHeadings($group, $data, $toc, $used);

            if ($group === 'records') {
                $data = static::applyRecords($data);
            }

            if ($group === 'quote') {
                $data['kind_class'] = ($data['kind'] ?? '') === 'warning' ? 'alert-warning' : 'alert-info';
            }

            if ($group === 'video' && isset($data['file'])) {
                $data['src'] = static::fileUrl((string) $data['file']);
            }

            $rows[] = [
                'group' => $group,
                'id' => (int) $item->id,
                'data' => $data,
            ];
        }

        return ['rows' => $rows, 'toc' => $toc];
    }

    /**
     * grid возвращает записи раздела для блока «сетка».
     *
     * Раздел вне белого списка даёт пустой массив, а не выборку «по всему»:
     * опечатка в YAML не должна вываливать на страницу весь инфоблок.
     *
     * @return array<int, \Tailor\Models\EntryRecord>
     */
    public static function grid(string $source, int $limit = 3): array
    {
        $meta = static::SOURCES[$source] ?? null;

        if ($meta === null) {
            return [];
        }

        $limit = max(1, min(static::MAX_LIMIT, $limit));

        return Content::records($meta['handle'], $limit);
    }

    /**
     * fileUrl превращает значение mediafinder в адрес файла.
     *
     * Внешние адреса отбрасываются: CSP разрешает медиа только со своего
     * домена (`media-src` не выводится, работает `default-src 'self'`),
     * поэтому вставленный чужой плеер всё равно не заработал бы.
     */
    public static function fileUrl(string $value): string
    {
        $path = Media::imagePath($value);

        if ($path === '' || preg_match('#^(https?:)?//#i', $path)) {
            return '';
        }

        if ($path[0] === '/') {
            return $path;
        }

        if (str_starts_with($path, 'storage/')) {
            return '/' . $path;
        }

        if (str_starts_with($path, 'assets/')) {
            return Media::themeUrl($path);
        }

        return (string) MediaLibrary::url($path);
    }

    /**
     * data собирает поля группы в массив для партиала.
     */
    protected static function data(string $group, $item): array
    {
        $data = [];

        foreach (static::FIELDS[$group] as $field) {
            $value = $item->{$field};
            $data[$field] = is_string($value) ? trim($value) : $value;
        }

        return $data;
    }

    /**
     * applyHeadings добавляет якоря подзаголовкам и собирает оглавление.
     *
     * Заголовок самого блока и `<h2>/<h3>` внутри richeditor нумеруются
     * в одном пространстве: два блока с одинаковым подзаголовком получают
     * `-2`, `-3`, иначе ссылка из оглавления ведёт не туда.
     */
    protected static function applyHeadings(string $group, array $data, array &$toc, array &$used): array
    {
        if (!in_array($group, ['text', 'text-image'], true)) {
            return $data;
        }

        $title = (string) ($data['title'] ?? '');

        if ($title !== '') {
            $data['title_id'] = static::anchor($title, $used);
            $toc[] = ['level' => 2, 'id' => $data['title_id'], 'text' => $title];
        }

        $data['body'] = static::bodyAnchors((string) ($data['body'] ?? ''), $toc, $used);

        return $data;
    }

    /**
     * bodyAnchors расставляет id у `<h2>/<h3>` и дополняет оглавление.
     */
    protected static function bodyAnchors(string $html, array &$toc, array &$used): string
    {
        if ($html === '' || !preg_match('/<h[23][\s>]/i', $html)) {
            return $html;
        }

        $result = preg_replace_callback('/<h([23])([^>]*)>(.*?)<\/h\1>/is', function ($matches) use (&$toc, &$used) {
            $level = (int) $matches[1];
            $attrs = (string) $matches[2];
            $text = trim(strip_tags((string) $matches[3]));

            if ($text === '') {
                return $matches[0];
            }

            if (preg_match('/\bid=["\']([^"\']+)["\']/i', $attrs, $found)) {
                $id = $found[1];
            }
            else {
                $id = static::anchor($text, $used);
                $attrs .= ' id="' . e($id) . '"';
            }

            $toc[] = ['level' => $level, 'id' => $id, 'text' => $text];

            return '<h' . $level . $attrs . '>' . $matches[3] . '</h' . $level . '>';
        }, $html);

        return (string) $result;
    }

    /**
     * anchor возвращает уникальный якорь из текста подзаголовка.
     */
    protected static function anchor(string $text, array &$used): string
    {
        $base = Str::slug($text);

        if ($base === '') {
            $base = 'section';
        }

        $id = $base;
        $counter = 2;

        while (isset($used[$id])) {
            $id = $base . '-' . $counter;
            $counter++;
        }

        $used[$id] = true;

        return $id;
    }

    /**
     * applyRecords дополняет блок «сетка» записями и адресами карточки.
     */
    protected static function applyRecords(array $data): array
    {
        $source = (string) ($data['source'] ?? '');
        $limit = (int) ($data['limit'] ?? 3);

        $data['items'] = static::grid($source, $limit);
        $data['card'] = static::SOURCES[$source]['card'] ?? '';
        $data['more_url'] = static::SOURCES[$source]['url'] ?? '';

        return $data;
    }
}
