<?php namespace Nlb\Library\Classes\Http;

use Illuminate\Http\JsonResponse;
use Nlb\Library\Classes\Content;
use Nlb\Library\Classes\Media;
use Nlb\Library\Classes\Sitemap;

/**
 * ApiController — публичный JSON контента Tailor (`/api/v1/...`).
 *
 * Отдаёт только опубликованные записи (тот же фильтр, что у страниц темы:
 * `Content::records()` / `Content::record()`), только согласованные поля
 * и только известные разделы из `Sitemap::SECTIONS`. Неизвестный раздел —
 * 404 в JSON, а не HTML-страница ошибки: клиент — мобильное приложение или
 * бот, разбирающий тело ответа.
 *
 * Контракт описан в `docs/architecture.md` (раздел «REST API контента»).
 */
class ApiController
{
    /**
     * MAX_LIMIT — потолок параметра `limit`, чтобы один запрос не выгружал всё сразу.
     */
    public const MAX_LIMIT = 100;

    /**
     * FIELDS — поля записи в ответе: контракт, а не «что есть в модели».
     */
    public const FIELDS = ['id', 'slug', 'title', 'excerpt', 'image', 'url', 'updated_at'];

    /**
     * index отдаёт список записей раздела.
     */
    public function index(string $handle): JsonResponse
    {
        $prefix = $this->prefix($handle);

        if ($prefix === null) {
            return $this->unknownSection($handle);
        }

        $limit = $this->limit();

        if ($limit === false) {
            return response()->json([
                'message' => 'Параметр limit должен быть числом от 1 до ' . static::MAX_LIMIT . '.',
            ], 422);
        }

        $records = Content::records(Sitemap::SECTIONS[$prefix], $limit);

        $data = [];

        foreach ($records as $record) {
            $data[] = $this->payload($record, $prefix);
        }

        return response()->json([
            'data' => $data,
            'meta' => [
                'section' => $prefix,
                'count' => count($data),
            ],
        ], 200, [
            'Cache-Control' => 'public, max-age=60',
        ]);
    }

    /**
     * show отдаёт одну запись по slug.
     */
    public function show(string $handle, string $slug): JsonResponse
    {
        $prefix = $this->prefix($handle);

        if ($prefix === null) {
            return $this->unknownSection($handle);
        }

        $record = Content::record(Sitemap::SECTIONS[$prefix], $slug);

        if ($record === null) {
            return response()->json([
                'message' => 'Запись не найдена.',
            ], 404);
        }

        return response()->json([
            'data' => $this->payload($record, $prefix),
        ], 200, [
            'Cache-Control' => 'public, max-age=60',
        ]);
    }

    /**
     * prefix приводит handle запроса к ключу `Sitemap::SECTIONS` либо отдаёт null.
     */
    protected function prefix(string $handle): ?string
    {
        $prefix = strtolower(trim($handle));

        return array_key_exists($prefix, Sitemap::SECTIONS) ? $prefix : null;
    }

    /**
     * limit разбирает `?limit=`: null — параметра нет, false — значение негодное.
     *
     * @return int|null|false
     */
    protected function limit()
    {
        if (!request()->has('limit')) {
            return null;
        }

        $raw = trim((string) request()->query('limit'));

        if ($raw === '' || !ctype_digit($raw)) {
            return false;
        }

        $limit = (int) $raw;

        return $limit >= 1 && $limit <= static::MAX_LIMIT ? $limit : false;
    }

    /**
     * payload собирает запись по контракту `FIELDS`.
     */
    protected function payload($record, string $prefix): array
    {
        $slug = trim((string) $record->slug);
        $updated = $record->updated_at ?: $record->created_at;

        return [
            'id' => (int) $record->id,
            'slug' => $slug,
            'title' => trim((string) $record->title),
            'excerpt' => $this->excerpt($record),
            'image' => $this->image($record),
            'url' => rtrim(url('/'), '/') . '/' . $prefix . '/' . $slug . '/',
            'updated_at' => $updated ? $updated->toIso8601String() : null,
        ];
    }

    /**
     * excerpt берёт анонс, иначе текст описания без разметки.
     */
    protected function excerpt($record): string
    {
        $excerpt = trim((string) ($record->excerpt ?? ''));

        if ($excerpt === '') {
            $excerpt = trim(strip_tags((string) ($record->content ?? '')));
        }

        $excerpt = trim(preg_replace('~\s+~u', ' ', $excerpt));

        return mb_substr($excerpt, 0, 200);
    }

    /**
     * image отдаёт абсолютный адрес картинки либо null.
     */
    protected function image($record): ?string
    {
        $path = Media::imagePath($record->image ?? '');

        return $path === '' ? null : url(Media::themeUrl($path));
    }

    /**
     * unknownSection — единый 404 для опечатки в handle.
     */
    protected function unknownSection(string $handle): JsonResponse
    {
        return response()->json([
            'message' => 'Неизвестный раздел «' . $handle . '». Доступны: ' . implode(', ', array_keys(Sitemap::SECTIONS)) . '.',
        ], 404);
    }
}
