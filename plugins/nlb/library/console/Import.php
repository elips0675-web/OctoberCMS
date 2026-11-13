<?php namespace Nlb\Library\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Tailor\Models\EntryRecord;
use Tailor\Models\StructureRecord;

class Import extends Command
{
    protected $signature = 'nlb:import
        {--source= : Каталог с data/*.php (по умолчанию выгрузка источника)}
        {--dry-run : Только посчитать, ничего не записывать}';

    protected $description = 'Импорт контента (идемпотентно по xml_id)';

    /**
     * @var array<int, string> repeaterFields хранятся в отдельной таблице связи
     */
    protected $repeaterFields = ['features', 'tariffs'];

    /**
     * @var array<string, array{blueprint: string, section: ?string, data: string, prefix: string}>
     */
    protected $entities = [
        'news' => [
            'blueprint' => 'Nlb\News',
            'section' => 'Nlb\NewsSection',
            'data' => 'news',
            'prefix' => 'nlb-news-',
        ],
        'events' => [
            'blueprint' => 'Nlb\Event',
            'section' => 'Nlb\EventSection',
            'data' => 'events',
            'prefix' => 'nlb-evt-',
        ],
        'books' => [
            'blueprint' => 'Nlb\Book',
            'section' => 'Nlb\BookSection',
            'data' => 'books',
            'prefix' => 'nlb-book-',
        ],
        'collections' => [
            'blueprint' => 'Nlb\Collection',
            'section' => null,
            'data' => 'collections',
            'prefix' => 'nlb-col-',
        ],
        'services' => [
            'blueprint' => 'Nlb\Service',
            'section' => null,
            'data' => 'services',
            'prefix' => 'nlb-srv-',
        ],
        'printing' => [
            'blueprint' => 'Nlb\Printing',
            'section' => null,
            'data' => 'printing',
            'prefix' => 'nlb-prn-',
        ],
    ];

    public function handle()
    {
        $source = rtrim((string) ($this->option('source') ?: 'D:/nlbbootstrap-main/db/config/data'), '/\\');
        $dryRun = (bool) $this->option('dry-run');

        if (!is_dir($source)) {
            $this->error("Каталог не найден: {$source}");

            return 1;
        }

        $iblocksFile = dirname($source) . '/iblocks.php';
        if (!is_file($iblocksFile)) {
            $this->error("Нет файла структуры: {$iblocksFile}");

            return 1;
        }

        $iblocks = include $iblocksFile;
        $totals = ['sections_created' => 0, 'sections_updated' => 0, 'created' => 0, 'updated' => 0];
        $rows = [];

        foreach ($this->entities as $code => $config) {
            $dataFile = $source . '/' . $config['data'] . '.php';
            if (!is_file($dataFile)) {
                $this->error("Нет файла данных: {$dataFile}");

                return 1;
            }

            $payload = include $dataFile;
            $items = (array) ($payload['items'] ?? []);

            $sectionMap = [];
            $sectionCount = 0;
            if ($config['section']) {
                $sections = (array) ($iblocks['iblocks'][$code]['sections_data'] ?? []);
                $sectionCount = count($sections);
                [$created, $updated] = $this->importSections($config['section'], $code, $sections, $dryRun);
                $totals['sections_created'] += $created;
                $totals['sections_updated'] += $updated;
                $sectionMap = $this->sectionIds($config['section'], $sections, $code);
            }

            [$created, $updated] = $this->importItems($code, $config, $items, $sectionMap, $dryRun);
            $totals['created'] += $created;
            $totals['updated'] += $updated;

            $rows[] = [$code, count($items), $sectionCount, $created, $updated];
        }

        $this->table(
            ['Инфоблок', 'Элементов', 'Разделов', 'Создано', 'Обновлено'],
            $rows
        );

        $this->info(sprintf(
            '%s Разделы: %d создано, %d обновлено. Элементы: %d создано, %d обновлено.',
            $dryRun ? '[dry-run]' : '[готово]',
            $totals['sections_created'],
            $totals['sections_updated'],
            $totals['created'],
            $totals['updated']
        ));

        return 0;
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function importSections(string $handle, string $iblock, array $sections, bool $dryRun): array
    {
        $created = 0;
        $updated = 0;

        foreach ($sections as $section) {
            $xmlId = 'nlb-sec-' . $iblock . '-' . $section['code'];
            $existing = $this->findRecord($handle, $xmlId);

            if (!$existing) {
                $created++;
                if (!$dryRun) {
                    $record = StructureRecord::inSection($handle);
                    $record->xml_id = $xmlId;
                    $record->title = $section['name'];
                    $record->code = $section['code'];
                    $record->sort = (int) ($section['sort'] ?? 100);
                    $record->is_enabled = true;
                    $record->save();
                }
                continue;
            }

            $updated++;
            if (!$dryRun) {
                $existing->title = $section['name'];
                $existing->code = $section['code'];
                $existing->sort = (int) ($section['sort'] ?? 100);
                $existing->is_enabled = true;
                $existing->save();
            }
        }

        return [$created, $updated];
    }

    /**
     * @return array<string, int>
     */
    protected function sectionIds(string $handle, array $sections, string $iblock): array
    {
        $map = [];

        foreach ($sections as $section) {
            $record = $this->findRecord($handle, 'nlb-sec-' . $iblock . '-' . $section['code']);
            if ($record) {
                $map[$section['code']] = $record->id;
            }
        }

        return $map;
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function importItems(string $code, array $config, array $items, array $sectionMap, bool $dryRun): array
    {
        $created = 0;
        $updated = 0;

        foreach ($items as $item) {
            $xmlId = (string) ($item['xml_id'] ?? '');
            if ($xmlId === '') {
                continue;
            }

            $existing = $this->findRecord($config['blueprint'], $xmlId);
            $attributes = $this->mapItem($code, $config, $item, $sectionMap);

            if (!$existing) {
                $created++;
                if (!$dryRun) {
                    $record = EntryRecord::inSection($config['blueprint']);
                    $record->xml_id = $xmlId;
                    $this->fill($record, $attributes);
                    $record->save();
                    $this->syncRepeaters($record, $attributes);
                }
                continue;
            }

            $updated++;
            if (!$dryRun) {
                $this->fill($existing, $attributes);
                $existing->save();
                $this->syncRepeaters($existing, $attributes);
            }
        }

        return [$created, $updated];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapItem(string $code, array $config, array $item, array $sectionMap): array
    {
        $props = (array) ($item['properties'] ?? []);

        $common = [
            'title' => (string) ($item['name'] ?? ''),
            'slug' => (string) ($item['code'] ?? ''),
            'sort' => (int) ($item['sort'] ?? 100),
            'published_at' => $this->parseDate($item['active_from'] ?? null),
            'is_enabled' => true,
            'excerpt' => $item['preview_text'] ?? null,
            'content' => $item['detail_text'] ?? null,
            'image' => $item['image'] ?? null,
        ];

        if ($config['section'] ?? false) {
            $sectionCode = (string) ($item['section'] ?? '');
            $common['section'] = $sectionMap[$sectionCode] ?? null;
        }

        $mapped = match ($code) {
            'news' => [
                'author' => $props['AUTHOR'] ?? null,
                'tags' => array_values((array) ($props['TAGS'] ?? [])),
                'read_time' => $props['READ_TIME'] ?? null,
                'source_url' => $props['SOURCE_URL'] ?? null,
            ],
            'events' => [
                'event_date' => $this->parseDate($props['DATE'] ?? null),
                'place' => $props['PLACE'] ?? null,
                'category' => $props['CATEGORY'] ?? null,
                'format' => $props['FORMAT'] ?? null,
                'seats' => isset($props['SEATS']) ? (int) $props['SEATS'] : null,
                'price' => $props['PRICE'] ?? null,
                'duration' => $props['DURATION'] ?? null,
                'register_url' => $props['REGISTER_URL'] ?? null,
            ],
            'books' => [
                'author' => $props['AUTHOR'] ?? null,
                'year' => $props['YEAR'] ?? null,
                'isbn' => $props['ISBN'] ?? null,
                'publisher' => $props['PUBLISHER'] ?? null,
                'pages' => isset($props['PAGES']) ? (int) $props['PAGES'] : null,
                'age_limit' => $props['AGE_LIMIT'] ?? null,
                'available' => $props['AVAILABLE'] ?? null,
                'shelf' => $props['SHELF'] ?? null,
                'price' => isset($props['PRICE']) ? (int) $props['PRICE'] : null,
            ],
            'collections' => [
                'items_count' => isset($props['COUNT']) ? (int) $props['COUNT'] : null,
                'color' => $props['COLOR'] ?? null,
                'icon' => $props['ICON'] ?? null,
            ],
            'services' => [
                'icon' => $props['ICON'] ?? null,
                'color' => $props['COLOR'] ?? null,
                'subtitle' => $props['SUBTITLE'] ?? null,
                'price_from' => isset($props['PRICE_FROM']) ? (int) $props['PRICE_FROM'] : null,
                'price_label' => $props['PRICE_LABEL'] ?? null,
                'duration' => $props['DURATION'] ?? null,
                'schedule' => $this->toLines($props['SCHEDULE'] ?? []),
                'phone' => $props['PHONE'] ?? null,
                'email' => $props['EMAIL'] ?? null,
                'features' => $this->mapFeatures($props['FEATURES'] ?? []),
                'tariffs' => $this->mapTariffs($props['TARIFFS'] ?? []),
            ],
            'printing' => [
                'icon' => $props['ICON'] ?? null,
                'color' => $props['COLOR'] ?? null,
                'price_from' => isset($props['PRICE_FROM']) ? (int) $props['PRICE_FROM'] : null,
                'unit' => $props['UNIT'] ?? null,
            ],
            default => [],
        };

        return array_merge($common, $mapped);
    }

    protected function fill($record, array $attributes): void
    {
        foreach ($attributes as $field => $value) {
            if (in_array($field, $this->repeaterFields, true)) {
                continue;
            }

            if ($field === 'section') {
                $record->section = $value;
                continue;
            }

            $record->{$field} = $value;
        }
    }

    protected function syncRepeaters($record, array $attributes): void
    {
        foreach ($this->repeaterFields as $field) {
            if (!array_key_exists($field, $attributes)) {
                continue;
            }

            $record->{$field}()->delete();

            foreach ((array) $attributes[$field] as $index => $row) {
                $item = $record->{$field}()->make();
                foreach ((array) $row as $key => $value) {
                    $item->{$key} = $value;
                }
                $item->sort_order = $index + 1;
                $item->save();
            }
        }
    }

    protected function findRecord(string $handle, string $xmlId)
    {
        return EntryRecord::inSection($handle)->where('xml_id', $xmlId)->first();
    }

    protected function parseDate($value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        foreach (['Y-m-d H:i:s', 'Y-m-d'] as $format) {
            $date = Carbon::createFromFormat($format, (string) $value);
            if ($date !== false && $date->format($format) === (string) $value) {
                return $date;
            }
        }

        return Carbon::parse((string) $value);
    }

    /**
     * @return array<int, array{title: string, description: string}>
     */
    protected function mapFeatures($features): array
    {
        $result = [];

        foreach ((array) $features as $feature) {
            $parts = explode('|', (string) $feature, 2);
            $title = trim($parts[0]);
            if ($title === '') {
                continue;
            }
            $result[] = [
                'title' => $title,
                'description' => trim($parts[1] ?? ''),
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array{title: string, price: string, includes: string, popular: bool}>
     */
    protected function mapTariffs($tariffs): array
    {
        $result = [];

        foreach ((array) $tariffs as $tariff) {
            $parts = array_map('trim', explode('|', (string) $tariff));
            $title = (string) ($parts[0] ?? '');
            if ($title === '') {
                continue;
            }

            $includes = [];
            if (($parts[2] ?? '') !== '') {
                $includes[] = $parts[2];
            }
            if (($parts[3] ?? '') !== '') {
                foreach (explode(';', $parts[3]) as $one) {
                    $one = trim($one);
                    if ($one !== '') {
                        $includes[] = $one;
                    }
                }
            }

            $result[] = [
                'title' => $title,
                'price' => (string) ($parts[1] ?? ''),
                'includes' => implode("\n", array_values(array_unique($includes))),
                'popular' => ($parts[4] ?? '') !== '',
            ];
        }

        return $result;
    }

    protected function toLines($value): string
    {
        if (is_array($value)) {
            return implode("\n", array_map('trim', $value));
        }

        return trim((string) $value);
    }
}
