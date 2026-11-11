<?php

/**
 * css-size.php — печатает размер минифицированного CSS темы.
 *
 * Считает тем же кодом, что и макет (`Media::minifyCss`), поэтому гейт
 * `tools/check-css-budget.mjs` меряет ровно то, что встраивается в страницу.
 *
 *   php tools/css-size.php themes/nlb/assets/css/styles.css [ещё файлы…]
 *
 * Вывод — JSON-строки: {"file": "...", "raw": 81179, "minified": 57794}.
 */

require __DIR__ . '/../vendor/autoload.php';

$files = array_slice($argv, 1);
if (!$files) {
    fwrite(STDERR, "usage: php tools/css-size.php <css-file> […]" . PHP_EOL);
    exit(2);
}

foreach ($files as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "нет файла: {$file}" . PHP_EOL);
        exit(2);
    }

    $raw = (string) file_get_contents($file);
    echo json_encode([
        'file' => $file,
        'raw' => strlen($raw),
        'minified' => strlen(\Nlb\Library\Classes\Media::minifyCss($raw)),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
