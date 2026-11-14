<?php namespace Elv\Library\Classes\Http;

use Illuminate\Http\JsonResponse;
use Elv\Library\Classes\Health;

/**
 * HealthController — ответ `/health` для внешней пробы.
 *
 * Живой сайт — 200, отказ любого компонента — 503. В теле только имена
 * компонентов и «да/нет»: без DSN, SQL и текста исключений.
 */
class HealthController
{
    /**
     * index отдаёт состояние БД, кэша и очереди.
     */
    public function index(Health $health): JsonResponse
    {
        $result = $health->check();

        return response()->json([
            'status' => $result['ok'] ? 'ok' : 'fail',
            'checks' => $result['checks'],
            'time' => now()->toIso8601String(),
        ], $result['ok'] ? 200 : 503, [
            'Cache-Control' => 'no-store',
        ]);
    }
}
