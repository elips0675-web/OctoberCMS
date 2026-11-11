<?php namespace Nlb\Library\Classes;

use Tailor\Models\EntryRecord;
use Tailor\Models\StructureRecord;

/**
 * Content — выборка Tailor-записей для страниц темы.
 *
 * Запросы держатся в одном месте: шаблоны не знают про Tailor-модели,
 * а разделы (section) резолвятся по коду, а не по числовому id.
 */
class Content
{
    /**
     * @var array<string, array>
     */
    protected static $cache = [];

    /**
     * flush сбрасывает кеш выборок.
     *
     * Живому запросу он не нужен (кеш умирает вместе с запросом), но тесты
     * и импорт выполняются в одном процессе: без сброса следующий тест увидит
     * записи предыдущего, а карта сайта — удалённую запись.
     */
    public static function flush(): void
    {
        static::$cache = [];
    }

    /**
     * records возвращает опубликованные записи раздела по сортировке.
     *
     * @return array<int, EntryRecord>
     */
    public static function records(string $handle, $limit = null): array
    {
        $key = 'records|' . $handle . '|' . (string) $limit;
        if (array_key_exists($key, static::$cache)) {
            return static::$cache[$key];
        }

        $query = EntryRecord::inSection($handle)
            ->applyVisibleFrontend()
            ->orderBy('sort')
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit((int) $limit);
        }

        return static::$cache[$key] = $query->get()->all();
    }

    /**
     * recordsInSection возвращает записи одного раздела по его коду.
     *
     * @return array<int, EntryRecord>
     */
    public static function recordsInSection(string $handle, string $sectionHandle, string $code, $limit = null): array
    {
        $id = static::sectionId($sectionHandle, $code);
        if ($id === null) {
            return [];
        }

        $key = 'records-section|' . $handle . '|' . $id . '|' . (string) $limit;
        if (array_key_exists($key, static::$cache)) {
            return static::$cache[$key];
        }

        $query = EntryRecord::inSection($handle)
            ->applyVisibleFrontend()
            ->where('section_id', $id)
            ->orderBy('sort')
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit((int) $limit);
        }

        return static::$cache[$key] = $query->get()->all();
    }

    /**
     * record находит запись по slug.
     */
    public static function record(string $handle, $slug): ?EntryRecord
    {
        $slug = trim((string) $slug);
        if ($slug === '') {
            return null;
        }

        $key = 'record|' . $handle . '|' . $slug;
        if (array_key_exists($key, static::$cache)) {
            return static::$cache[$key];
        }

        $record = EntryRecord::inSection($handle)
            ->applyVisibleFrontend()
            ->where('slug', $slug)
            ->first();

        return static::$cache[$key] = $record;
    }

    /**
     * sections возвращает разделы инфоблока по сортировке.
     *
     * @return array<int, StructureRecord>
     */
    public static function sections(string $handle): array
    {
        $key = 'sections|' . $handle;
        if (array_key_exists($key, static::$cache)) {
            return static::$cache[$key];
        }

        return static::$cache[$key] = StructureRecord::inSection($handle)
            ->where('is_enabled', true)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * sectionId возвращает id раздела по его коду.
     */
    public static function sectionId(string $handle, string $code): ?int
    {
        foreach (static::sections($handle) as $section) {
            if ((string) $section->code === (string) $code) {
                return (int) $section->id;
            }
        }

        return null;
    }

    /**
     * sectionCode возвращает код раздела по его id.
     */
    public static function sectionCode(string $handle, $id): string
    {
        foreach (static::sections($handle) as $section) {
            if ((int) $section->id === (int) $id) {
                return (string) $section->code;
            }
        }

        return '';
    }

    /**
     * sectionTitle возвращает название раздела по его id.
     */
    public static function sectionTitle(string $handle, $id): string
    {
        foreach (static::sections($handle) as $section) {
            if ((int) $section->id === (int) $id) {
                return (string) $section->title;
            }
        }

        return '';
    }
}
