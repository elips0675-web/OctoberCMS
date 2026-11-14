<?php namespace Elv\Library\Console;

use Illuminate\Console\Command;
use Elv\Library\Classes\Health;

/**
 * HealthReport — часовая строка состояния для мониторинга.
 *
 * Пишет JSON-строку (время, ответ БД в мс, размер очереди, свободное место),
 * а не человеческий текст: строку читает скрипт, а не человек. ПДн и секретов
 * в отчёте нет — только числа и «да/нет» по компонентам.
 */
class HealthReport extends Command
{
    protected $signature = 'elv:health-report
        {--path= : файл для строки отчёта (по умолчанию storage/logs/health.log)}';

    protected $description = 'Записывает строку состояния БД, очереди и диска';

    public function handle()
    {
        $health = $this->laravel->make(Health::class);
        $metrics = $health->metrics();

        $line = json_encode(
            ['at' => now()->toIso8601String()] + $metrics,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        $path = (string) ($this->option('path') ?: storage_path('logs/health.log'));
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);

        $this->line($line);

        return $metrics['checks']['db'] ? self::SUCCESS : self::FAILURE;
    }
}
