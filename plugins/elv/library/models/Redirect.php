<?php namespace Elv\Library\Models;

use Cache;
use DB;
use October\Rain\Database\Model;
use October\Rain\Database\Traits\Validation;

/**
 * Redirect — редирект, который редактор заводит в админке.
 *
 * Хардкод-карта старых адресов 1С-Битрикс живёт в `Classes\Http\LegacyRedirect`,
 * а эта модель закрывает то, что появляется после: переименованная страница,
 * склеенный раздел, старая ссылка из письма. Адрес хранения — `elv_redirects`,
 * отдача — `Classes\Http\ManagedRedirect`.
 */
class Redirect extends Model
{
    use Validation;

    public $table = 'elv_redirects';

    protected $guarded = [];

    protected $casts = [
        'status_code' => 'integer',
        'is_enabled' => 'boolean',
        'hit_count' => 'integer',
        'last_hit_at' => 'datetime',
    ];

    public $attributes = [
        'status_code' => 301,
        'is_enabled' => true,
        'hit_count' => 0,
    ];

    public $rules = [
        'from_path' => [
            'required',
            'string',
            'max:255',
            'regex:/^\/\S*$/',
            'not_in:/',
            'different:to_path',
            'unique:elv_redirects,from_path',
        ],
        'to_path' => [
            'required',
            'string',
            'max:255',
            'regex:/^(?:\/|https?:\/\/)\S*$/i',
        ],
        'status_code' => ['required', 'integer', 'in:301,302,307,308'],
        'is_enabled' => ['boolean'],
        'hit_count' => ['integer', 'min:0'],
    ];

    public $customMessages = [
        'from_path.required' => 'Укажите старый адрес.',
        'from_path.regex' => 'Адрес начинается со слэша: /news/old-page.',
        'from_path.not_in' => 'Главную страницу перенаправлять нельзя: получится петля.',
        'from_path.different' => 'Старый и новый адрес совпадают.',
        'from_path.unique' => 'Такой редирект уже есть.',
        'to_path.required' => 'Укажите новый адрес.',
        'to_path.regex' => 'Новый адрес — путь (/news/new-page) или ссылка https://…',
        'status_code.in' => 'Код ответа — 301, 302, 307 или 308.',
    ];

    public const CACHE_TTL = 600;

    public static function boot()
    {
        parent::boot();

        static::saved(function (self $redirect) {
            $redirect->forgetCache();
            $redirect->forgetCache($redirect->getOriginal('from_path'));
        });

        static::deleted(function (self $redirect) {
            $redirect->forgetCache();
        });
    }

    /**
     * setFromPathAttribute — адрес приводится к виду до проверки правил.
     *
     * Так `news/old/`, `/news/old?utm=1` и `/news//old` — один и тот же ключ,
     * и уникальность ловит дубль, а не создаёт второй.
     */
    public function setFromPathAttribute($value)
    {
        $this->attributes['from_path'] = self::normalizePath((string) $value);
    }

    public function setToPathAttribute($value)
    {
        $value = trim((string) $value);

        $this->attributes['to_path'] = preg_match('#^https?://#i', $value)
            ? $value
            : self::normalizePath($value);
    }

    /**
     * normalizePath — `/news//old/?utm=1` → `/news/old`.
     */
    public static function normalizePath(string $path): string
    {
        $path = trim($path);

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $path) && !preg_match('#^/#', $path)) {
            return $path;
        }

        $path = preg_split('/[?#]/', $path, 2)[0] ?? '';
        $path = preg_replace('#/+#', '/', '/' . ltrim($path, '/'));
        $path = rtrim((string) $path, '/');

        return $path === '' ? '/' : $path;
    }

    public static function cacheKey(string $path): string
    {
        return 'elv.redirect.' . md5($path);
    }

    /**
     * findForPath — включённый редирект для пути, из кеша или из базы.
     *
     * @return array{id: int, to_path: string, status_code: int}|null
     */
    public static function findForPath(string $path): ?array
    {
        $path = self::normalizePath($path);

        if ($path === '/') {
            return null;
        }

        $row = Cache::remember(self::cacheKey($path), self::CACHE_TTL, function () use ($path) {
            $redirect = static::query()
                ->where('from_path', $path)
                ->where('is_enabled', 1)
                ->first(['id', 'to_path', 'status_code']);

            if (!$redirect) {
                return null;
            }

            return [
                'id' => (int) $redirect->getKey(),
                'to_path' => (string) $redirect->to_path,
                'status_code' => (int) $redirect->status_code,
            ];
        });

        return is_array($row) ? $row : null;
    }

    /**
     * registerHit — счётчик срабатываний; ошибка записи не ломает редирект.
     */
    public static function registerHit(int $id): void
    {
        try {
            static::query()->whereKey($id)->update([
                'hit_count' => DB::raw('hit_count + 1'),
                'last_hit_at' => now(),
            ]);
        }
        catch (\Throwable $e) {
            // Счётчик — украшение списка, а не часть редиректа.
        }
    }

    protected function forgetCache(?string $path = null): void
    {
        $path = $path !== null && $path !== '' ? $path : (string) $this->getAttribute('from_path');

        if ($path === '') {
            return;
        }

        Cache::forget(self::cacheKey($path));
    }
}
