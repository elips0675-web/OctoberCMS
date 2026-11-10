<?php namespace Nlb\Library\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Nlb\Library\Models\Slot;
use Tailor\Models\EntryRecord;

/**
 * SlotsGenerate — слоты услуг на ближайшие дни.
 *
 * Часы совпадают с источником (`nlb_form_time_options()`): 10:00–21:00.
 * Повторный запуск не создаёт дублей: ключ — UNIQUE(service_code, date, time).
 */
class SlotsGenerate extends Command
{
    protected $signature = 'nlb:slots-generate
        {--days=30 : Сколько дней вперёд генерировать}
        {--from= : Первый день в формате Y-m-d (по умолчанию сегодня)}
        {--service=* : Код услуги, можно несколько (по умолчанию все включённые)}
        {--times= : Времена через запятую, например 10:00,12:00}';

    protected $description = 'Генерация слотов услуг (идемпотентно)';

    public function handle()
    {
        $days = max(1, (int) $this->option('days'));
        $from = $this->resolveFrom();
        $times = $this->resolveTimes();
        $services = $this->resolveServices();

        if (!$services) {
            $this->warn('Не найдено ни одной включённой услуги — генерировать нечего.');
            return 1;
        }

        $created = 0;
        $existing = 0;

        foreach ($services as $serviceCode) {
            $date = $from->copy();

            for ($day = 0; $day < $days; $day++) {
                foreach ($times as $time) {
                    $slot = Slot::firstOrCreate(
                        [
                            'service_code' => $serviceCode,
                            'date' => $date->format('Y-m-d'),
                            'time' => $time,
                        ],
                        [
                            'capacity' => 1,
                            'taken' => 0,
                        ]
                    );

                    $slot->wasRecentlyCreated ? $created++ : $existing++;
                }

                $date->addDay();
            }
        }

        $this->table(
            ['Услуг', 'Дней', 'Времён', 'Создано', 'Уже было'],
            [[count($services), $days, count($times), $created, $existing]]
        );

        $this->info(sprintf(
            'Слоты: %d создано, %d уже существовало (с %s).',
            $created,
            $existing,
            $from->format('Y-m-d')
        ));

        return 0;
    }

    protected function resolveFrom(): Carbon
    {
        $value = (string) $this->option('from');
        if ($value === '') {
            return Carbon::today();
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);
        }
        catch (\Throwable $e) {
            throw new \InvalidArgumentException("Неверная дата: {$value}. Ожидается Y-m-d.");
        }

        if ($date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException("Неверная дата: {$value}. Ожидается Y-m-d.");
        }

        return $date;
    }

    /**
     * @return string[]
     */
    protected function resolveTimes(): array
    {
        $value = (string) $this->option('times');
        if ($value === '') {
            return $this->defaultTimes();
        }

        $times = [];
        foreach (explode(',', $value) as $time) {
            $time = trim($time);
            if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $time, $matches)) {
                $times[] = sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
            }
        }

        if (!$times) {
            throw new \InvalidArgumentException('Неверный список времён. Ожидается 10:00,12:00.');
        }

        return array_values(array_unique($times));
    }

    /**
     * @return string[]
     */
    protected function defaultTimes(): array
    {
        $times = [];
        for ($hour = 10; $hour <= 21; $hour++) {
            $times[] = sprintf('%02d:00', $hour);
        }

        return $times;
    }

    /**
     * @return string[]
     */
    protected function resolveServices(): array
    {
        $requested = array_values(array_filter(array_map('trim', (array) $this->option('service'))));
        if ($requested) {
            return $requested;
        }

        return EntryRecord::inSection('Nlb\Service')
            ->where('is_enabled', true)
            ->orderBy('sort')
            ->pluck('slug')
            ->filter()
            ->values()
            ->all();
    }
}
