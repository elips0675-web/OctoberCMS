<?php namespace Nlb\Library\Tests;

use Nlb\Library\Classes\Health;
use PluginTestCase;

/**
 * HealthTest — `/health` и часовой отчёт.
 *
 * Проверяется не только «200 на живой системе»: отказ каждого компонента
 * обязан давать 503, а тело — имена компонентов без DSN, SQL и текста
 * исключений. Иначе health-страница сама становится утечкой.
 */
class HealthTest extends PluginTestCase
{
    protected $useTransactions = true;

    public function testHealthIsOkWhenEverythingAnswers(): void
    {
        $response = $this->get('/health');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $body = json_decode($response->getContent(), true);

        $this->assertSame('ok', $body['status']);
        $this->assertSame(['db' => true, 'cache' => true, 'queue' => true], $body['checks']);
        $this->assertNotEmpty($body['time']);
    }

    public function testHealthDoesNotStartSession(): void
    {
        $response = $this->get('/health');

        $this->assertFalse($response->headers->has('Set-Cookie'));
    }

    public function testDatabaseFailureReturnsServiceUnavailable(): void
    {
        $this->app->instance(Health::class, new class extends Health {
            protected function database(): bool
            {
                return false;
            }
        });

        $response = $this->get('/health');

        $response->assertStatus(503);

        $body = json_decode($response->getContent(), true);

        $this->assertSame('fail', $body['status']);
        $this->assertFalse($body['checks']['db']);
        $this->assertTrue($body['checks']['cache']);
    }

    public function testCacheFailureReturnsServiceUnavailable(): void
    {
        $this->app->instance(Health::class, new class extends Health {
            protected function cache(): bool
            {
                return false;
            }
        });

        $response = $this->get('/health');

        $response->assertStatus(503);
        $this->assertFalse(json_decode($response->getContent(), true)['checks']['cache']);
    }

    public function testQueueFailureReturnsServiceUnavailable(): void
    {
        $this->app->instance(Health::class, new class extends Health {
            protected function queue(): bool
            {
                return false;
            }
        });

        $response = $this->get('/health');

        $response->assertStatus(503);
        $this->assertFalse(json_decode($response->getContent(), true)['checks']['queue']);
    }

    public function testFailureBodyDoesNotLeakInternals(): void
    {
        $this->app->instance(Health::class, new class extends Health {
            protected function database(): bool
            {
                return false;
            }
        });

        $body = (string) $this->get('/health')->getContent();

        foreach (['SQLSTATE', 'mysql', 'password', '127.0.0.1', 'Exception', 'vendor/'] as $needle) {
            $this->assertStringNotContainsStringIgnoringCase($needle, $body);
        }
    }

    public function testMetricsCarryNumbers(): void
    {
        $metrics = (new Health())->metrics();

        $this->assertIsFloat($metrics['db_ms']);
        $this->assertIsInt($metrics['queue_size']);
        $this->assertIsInt($metrics['disk_free_bytes']);
        $this->assertSame(['db' => true, 'cache' => true, 'queue' => true], $metrics['checks']);
    }

    public function testReportCommandWritesJsonLine(): void
    {
        $path = storage_path('logs/health-test-' . bin2hex(random_bytes(4)) . '.log');

        try {
            $this->assertSame(0, \Artisan::call('nlb:health-report', ['--path' => $path]));

            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            $this->assertCount(1, $lines);

            $report = json_decode($lines[0], true);

            $this->assertIsArray($report);
            $this->assertNotEmpty($report['at']);
            $this->assertIsNumeric($report['db_ms']);
            $this->assertIsInt($report['queue_size']);
            $this->assertSame(['db' => true, 'cache' => true, 'queue' => true], $report['checks']);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testReportCommandAppendsAndFailsWithoutDatabase(): void
    {
        $path = storage_path('logs/health-test-' . bin2hex(random_bytes(4)) . '.log');

        try {
            $this->app->instance(Health::class, new class extends Health {
                protected function database(): bool
                {
                    return false;
                }

                protected function databaseMilliseconds(): ?float
                {
                    return null;
                }
            });

            $this->assertSame(1, \Artisan::call('nlb:health-report', ['--path' => $path]));

            $this->app->forgetInstance(Health::class);

            $this->assertSame(0, \Artisan::call('nlb:health-report', ['--path' => $path]));

            $this->assertCount(2, file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testQueueCheckSkipsSyncDriver(): void
    {
        config(['queue.default' => 'sync']);

        $this->assertTrue((new Health())->check()['checks']['queue']);
        $this->assertSame(0, (new Health())->metrics()['queue_size']);
    }

    public function testQueueCheckFailsOnBrokenConnection(): void
    {
        config(['queue.default' => 'nlb-missing-connection']);

        $this->assertFalse((new Health())->check()['checks']['queue']);
    }

    public function testScheduledTasksAreRegistered(): void
    {
        $events = collect($this->app->make(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->map(function ($event) {
                return (string) $event->command;
            })
            ->implode(' ');

        $this->assertStringContainsString('nlb:health-report', $events);
        $this->assertStringContainsString('nlb:slots-generate', $events);
    }
}
