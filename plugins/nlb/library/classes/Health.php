<?php namespace Nlb\Library\Classes;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Health — проверка живости БД, кэша и очереди.
 *
 * Отвечает только «да/нет» по компонентам и числа для отчёта. Текст исключения
 * наружу не отдаётся: он попадает в лог, а в ответе — имя компонента, чтобы
 * health-страница не стала источником утечки (DSN, SQL, пароль).
 */
class Health
{
    /**
     * check — состояние трёх компонентов и общий вердикт.
     *
     * @return array{ok: bool, checks: array<string, bool>}
     */
    public function check(): array
    {
        $checks = [
            'db' => $this->database(),
            'cache' => $this->cache(),
            'queue' => $this->queue(),
        ];

        return [
            'ok' => !in_array(false, $checks, true),
            'checks' => $checks,
        ];
    }

    /**
     * metrics — числа для часового отчёта.
     *
     * @return array<string, mixed>
     */
    public function metrics(): array
    {
        return [
            'db_ms' => $this->databaseMilliseconds(),
            'queue_size' => $this->queueSize(),
            'disk_free_bytes' => $this->diskFreeBytes(),
            'checks' => $this->check()['checks'],
        ];
    }

    /**
     * database — соединение отвечает на простой запрос.
     */
    protected function database(): bool
    {
        try {
            DB::connection()->select('SELECT 1');

            return true;
        } catch (\Throwable $e) {
            $this->log('db', $e);

            return false;
        }
    }

    /**
     * cache — значение записывается и читается обратно.
     */
    protected function cache(): bool
    {
        try {
            $key = 'nlb:health:' . bin2hex(random_bytes(6));
            Cache::put($key, 'ok', 5);

            return Cache::pull($key) === 'ok';
        } catch (\Throwable $e) {
            $this->log('cache', $e);

            return false;
        }
    }

    /**
     * queue — соединение очереди доступно.
     *
     * `sync` проверять нечего: задания выполняются в том же процессе.
     */
    protected function queue(): bool
    {
        try {
            if (config('queue.default') === 'sync') {
                return true;
            }

            Queue::connection()->size();

            return true;
        } catch (\Throwable $e) {
            $this->log('queue', $e);

            return false;
        }
    }

    /**
     * databaseMilliseconds — время ответа БД или null, если она не ответила.
     */
    protected function databaseMilliseconds(): ?float
    {
        $start = microtime(true);

        try {
            DB::connection()->select('SELECT 1');

            return round((microtime(true) - $start) * 1000, 1);
        } catch (\Throwable $e) {
            $this->log('db', $e);

            return null;
        }
    }

    /**
     * queueSize — размер очереди или null, если она недоступна.
     */
    protected function queueSize(): ?int
    {
        try {
            if (config('queue.default') === 'sync') {
                return 0;
            }

            return (int) Queue::connection()->size();
        } catch (\Throwable $e) {
            $this->log('queue', $e);

            return null;
        }
    }

    /**
     * diskFreeBytes — свободное место на диске приложения.
     */
    protected function diskFreeBytes(): ?int
    {
        $free = @disk_free_space(base_path());

        return $free === false ? null : (int) $free;
    }

    /**
     * log — причина отказа уходит в лог, а не в ответ.
     */
    protected function log(string $component, \Throwable $e): void
    {
        \Log::warning('health: ' . $component . ' недоступен', [
            'component' => $component,
            'error' => $e->getMessage(),
        ]);
    }
}
