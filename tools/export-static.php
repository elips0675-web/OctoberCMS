<?php

/**
 * export-static — статическая демо-копия шаблона для Vercel.
 *
 * Vercel не исполняет PHP, поэтому October CMS там отдаётся файлом: команда
 * снимает готовый HTML с локального сервера, переписывает базовый адрес,
 * отключает формы и складывает страницы в demo-static/<path>/index.html.
 *
 * Запуск: php tools/export-static.php --origin=https://octobercms.vercel.app
 * Нужен поднятый сервер: php artisan serve --port=8080 --host=127.0.0.1
 */

$options = getopt('', ['base::', 'origin::', 'out::', 'stable!', 'manifest::']);

$stable = ($options['stable'] ?? true) !== false;

$base = rtrim($options['base'] ?? 'http://127.0.0.1:8080', '/');
$origin = rtrim($options['origin'] ?? 'https://octobercms.vercel.app', '/');
$out = rtrim($options['out'] ?? dirname(__DIR__) . '/demo-static', '/\\');

function staticFetch(string $url): array
{
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'ElvThemeStaticExport/1.0',
    ]);

    $body = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $error = curl_error($handle);
    curl_close($handle);

    if ($body === false) {
        fwrite(STDERR, 'fetch failed: ' . $url . ' — ' . $error . PHP_EOL);

        return [0, ''];
    }

    return [$status, (string) $body];
}

function staticRemove(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($directory);
}

function staticCopy(string $source, string $target): void
{
    if (!is_dir($source)) {
        return;
    }

    if (!is_dir($target) && !mkdir($target, 0775, true) && !is_dir($target)) {
        throw new RuntimeException('cannot create ' . $target);
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($items as $item) {
        $destination = $target . '/' . $items->getSubPathname();

        if ($item->isDir()) {
            if (!is_dir($destination)) {
                mkdir($destination, 0775, true);
            }

            continue;
        }

        copy($item->getPathname(), $destination);
    }
}

function staticPagePath(string $out, string $path): string
{
    return $path === '/'
        ? $out . '/index.html'
        : ($path === '/404' ? $out . '/404.html' : $out . '/' . trim($path, '/') . '/index.html');
}

function staticFinalize(string $html, string $base, string $origin, string $inject): string
{
    $html = str_replace($base, $origin, $html);
    $quoted = preg_quote($origin, '~');
    $html = (string) preg_replace('~(\b(?:href|src|action|poster|content)=")' . $quoted . '/~i', '$1/', $html);
    $html = (string) preg_replace('~(\b(?:href|src|action|poster)=")' . $quoted . '"~i', '$1/"', $html);
    $html = (string) preg_replace('~(<link rel="canonical" href=")/~i', '$1' . $origin . '/', $html);
    $html = (string) preg_replace('~(<meta property="og:url" content=")/~i', '$1' . $origin . '/', $html);

    $bar = '<div id="elv-demo-bar" role="status">Статическая демо-версия шаблона ELV на October CMS: формы, брони и заказы отключены. Рабочая версия — на PHP-хостинге.</div>';

    if (!str_contains($html, 'elv-demo-bar')) {
        $html = (string) preg_replace('~(<body[^>]*>)~i', '$1' . $bar, $html, 1);
    }

    return str_replace('</body>', $inject . '</body>', $html);
}

function staticStable(string $html): string
{
    $html = (string) preg_replace('~\bnonce="[^"]*"~i', 'nonce="static-demo"', $html);
    $html = (string) preg_replace('~(<meta name="csrf-token" content=")[^"]*(")~i', '$1static-demo$2', $html);
    $html = (string) preg_replace('~(<input type="hidden" name="_token" value=")[^"]*(")~i', '$1static-demo$2', $html);

    return $html;
}

function staticWrite(string $out, string $path, string $html, bool $stable = true): void
{
    $file = staticPagePath($out, $path);

    if (!is_dir(dirname($file))) {
        mkdir(dirname($file), 0775, true);
    }

    file_put_contents($file, $stable ? staticStable($html) : $html);
}

/**
 * staticInlineCss выносит одинаковые инлайн-блоки CSS в файлы.
 *
 * Тема вкладывает `bootstrap` и `styles` в каждую страницу (по ~110 КБ),
 * из-за чего статическая копия из 113 страниц весит 16 МБ. Если содержимое
 * блока совпадает на всех страницах, оно пишется один раз в
 * `themes/elv/assets/css/`, а `<style>` заменяется на `<link>`.
 */
function staticInlineCss(string $out): void
{
    $pages = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($out, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'html') {
            $pages[] = $file->getPathname();
        }
    }

    $variants = [];
    $content = [];

    foreach ($pages as $page) {
        $html = (string) file_get_contents($page);
        preg_match_all('~<style data-elv-css="([a-z0-9-]+)"[^>]*>([\s\S]*?)</style>~i', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $variants[$match[1]][md5($match[2])] = true;
            $content[$match[1]][md5($match[2])] = $match[2];
        }
    }

    $target = $out . '/themes/elv/assets/css';

    if (!is_dir($target) && !mkdir($target, 0775, true) && !is_dir($target)) {
        throw new RuntimeException('cannot create ' . $target);
    }

    $saved = 0;

    foreach ($variants as $name => $hashes) {
        if (count($hashes) !== 1) {
            fwrite(STDERR, 'css: у блока "' . $name . '" разное содержимое на страницах — оставлен инлайн' . PHP_EOL);
            continue;
        }

        $hash = array_key_first($hashes);
        $css = (string) $content[$name][$hash];
        $file = $target . '/static-' . $name . '.css';
        file_put_contents($file, $css);
        $saved += (strlen($css) - 80) * count($pages);

        foreach ($pages as $page) {
            $html = (string) file_get_contents($page);
            $html = (string) preg_replace(
                '~<style data-elv-css="' . preg_quote($name, '~') . '"[^>]*>[\s\S]*?</style>~i',
                '<link rel="stylesheet" href="/themes/elv/assets/css/static-' . $name . '.css">',
                $html
            );
            file_put_contents($page, $html);
        }

        echo 'css: static-' . $name . '.css (' . strlen($css) . ' байт)' . PHP_EOL;
    }

    echo 'css saved: ' . round($saved / 1048576, 1) . ' МБ' . PHP_EOL;
}

$style = '<style>'
    . '#elv-demo-bar{position:relative;z-index:80;background:#0b1220;color:#dfe6f5;font:14px/1.45 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;padding:9px 16px;text-align:center}'
    . '#elv-static-note{position:fixed;left:50%;bottom:24px;transform:translateX(-50%) translateY(8px);background:#0b1220;color:#fff;padding:12px 18px;border-radius:12px;font:14px/1.4 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;opacity:0;pointer-events:none;transition:opacity .2s,transform .2s;z-index:200;max-width:90vw;text-align:center;box-shadow:0 12px 40px rgba(0,0,0,.35)}'
    . '#elv-static-note.is-visible{opacity:1;transform:translateX(-50%) translateY(0)}'
    . '</style>';

$script = <<<'HTML'
<script>
(function () {
    window.ELV_STATIC_DEMO = true;

    var note = function (text) {
        var box = document.getElementById('elv-static-note');

        if (!box) {
            box = document.createElement('div');
            box.id = 'elv-static-note';
            box.setAttribute('role', 'status');
            document.body.appendChild(box);
        }

        box.textContent = text;
        box.classList.add('is-visible');
        window.clearTimeout(box.elvTimer);
        box.elvTimer = window.setTimeout(function () {
            box.classList.remove('is-visible');
        }, 6000);
    };

    document.addEventListener('submit', function (event) {
        event.preventDefault();
        event.stopImmediatePropagation();
        note('Формы отключены в статической демо-версии. Рабочая версия — на PHP-хостинге.');
    }, true);

    document.addEventListener('click', function (event) {
        var node = event.target instanceof Element ? event.target : null;

        if (!node) {
            return;
        }

        if (node.closest('[data-chat-open]')) {
            event.preventDefault();
            event.stopImmediatePropagation();
            note('Консультант доступен в полной версии на PHP-хостинге.');
            return;
        }

        if (node.closest('a[href*="/subscribe/confirm/"], a[href*="/unsubscribe/"]')) {
            event.preventDefault();
            event.stopImmediatePropagation();
            note('Подтверждение подписки работает только в полной версии.');
        }
    }, true);

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistrations().then(function (items) {
            items.forEach(function (item) { item.unregister(); });
        });
    }
})();
</script>
HTML;

$inject = $style . $script;

staticRemove($out);
mkdir($out, 0775, true);

$rootPages = [
    '/', '/books/', '/printing/', '/news/', '/services/', '/events/', '/collections/',
    '/about/', '/auth/', '/contacts/', '/feedback/', '/personal/', '/privacy/',
    '/rules/', '/search/', '/sitemap/', '/subscribe/', '/terms/', '/blog/',
];

[, $sitemap] = staticFetch($base . '/sitemap.xml');
preg_match_all('~<loc>([^<]+)</loc>~', $sitemap, $matches);

$paths = [];

foreach ($matches[1] ?? [] as $loc) {
    $path = (string) parse_url(html_entity_decode($loc, ENT_QUOTES | ENT_XML1), PHP_URL_PATH);

    if ($path === '' || str_starts_with($path, '/admin') || str_starts_with($path, '/api/')) {
        continue;
    }

    $paths[rtrim($path, '/') ?: '/'] = true;
}

foreach ($rootPages as $path) {
    $paths[rtrim($path, '/') ?: '/'] = true;
}

ksort($paths);

$pages = 0;
$failed = [];

foreach (array_keys($paths) as $path) {
    $url = $base . ($path === '/' ? '/' : $path . '/');
    [$status, $html] = staticFetch($url);

    if ($status !== 200 || $html === '') {
        $failed[] = $path . ' (' . $status . ')';
        continue;
    }

    $html = staticFinalize($html, $base, $origin, $inject);
    staticWrite($out, $path, $html, $stable);
    $pages++;
}

[$notFoundStatus, $notFoundHtml] = staticFetch($base . '/non-existent/');

if ($notFoundHtml !== '') {
    file_put_contents(
        $out . '/404.html',
        staticStable(staticFinalize($notFoundHtml, $base, $origin, $inject))
    );
    echo 'page: 404.html (' . $notFoundStatus . ')' . PHP_EOL;
} else {
    fwrite(STDERR, 'skip: 404' . PHP_EOL);
}

foreach (['sitemap.xml', 'robots.txt', 'rss.xml', 'blog/rss.xml', 'manifest.webmanifest', 'sw.js', 'offline.html'] as $name) {
    [$status, $body] = staticFetch($base . '/' . $name);

    if ($status !== 200 || $body === '') {
        fwrite(STDERR, 'skip: ' . $name . ' (' . $status . ')' . PHP_EOL);
        continue;
    }

    $file = $out . '/' . $name;

    if (!is_dir(dirname($file))) {
        mkdir(dirname($file), 0775, true);
    }

    file_put_contents($file, str_replace($base, $origin, $body));
    echo 'file: ' . $name . PHP_EOL;
}

staticCopy(dirname(__DIR__) . '/themes/elv/assets', $out . '/themes/elv/assets');
staticInlineCss($out);

$missing = [];
$assets = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($out, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'html') {
        continue;
    }

    $html = (string) file_get_contents($file->getPathname());
    $prefixes = [];
    preg_match_all('~\b(?:href|src|poster)="(/[^"#?]+)"~i', $html, $prefixes);

    foreach ($prefixes[1] ?? [] as $asset) {
        if ($asset === '/' || is_dir($out . rtrim($asset, '/'))) {
            continue;
        }

        if (str_contains($asset, '.') && !is_file($out . $asset)) {
            $missing[$asset] = true;
        }
    }

    $assets += count($prefixes[1] ?? []);
}

foreach (array_keys($missing) as $asset) {
    fwrite(STDERR, 'missing asset: ' . $asset . PHP_EOL);
}

if (!empty($options['manifest'])) {
    $manifest = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($out, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($out) + 1));
        $manifest[$relative] = hash_file('sha256', $file->getPathname());
    }

    ksort($manifest);

    $lines = [];

    foreach ($manifest as $relative => $hash) {
        $lines[] = $hash . '  ' . $relative;
    }

    file_put_contents($options['manifest'], implode("\n", $lines) . "\n");
    echo 'manifest: ' . $options['manifest'] . ' (' . count($manifest) . ' файлов)' . PHP_EOL;
}

echo 'pages: ' . $pages . PHP_EOL;
echo 'failed: ' . (count($failed) ? implode(', ', $failed) : 'нет') . PHP_EOL;
echo 'missing assets: ' . (count($missing) ? count($missing) : 'нет') . PHP_EOL;
echo 'origin: ' . $origin . PHP_EOL;
echo 'output: ' . $out . PHP_EOL;

exit(count($failed) || count($missing) ? 1 : 0);
