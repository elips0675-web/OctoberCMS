<?php namespace Nlb\Library\Tests;

use Cms\Classes\Theme;
use Config;
use Nlb\Library\Classes\Pwa;
use PluginTestCase;

/**
 * PwaTest — manifest, service worker и офлайн-страница.
 *
 * Оболочка проверяется как контракт браузера: manifest разбирается, у иконок
 * есть файлы, service worker получает версию и список кеша, офлайн-страница
 * не содержит инлайнового обработчика (его заблокирует CSP Stage 10).
 */
class PwaTest extends PluginTestCase
{
    protected $useTransactions = true;

    protected $autoMigrateTailor = true;

    /**
     * @var string|null previous active theme to restore after the test
     */
    protected $previousTheme;

    public function setUp(): void
    {
        parent::setUp();

        $this->previousTheme = Config::get('cms.active_theme');
        Config::set('cms.active_theme', 'nlb');
        Theme::resetCache();
    }

    public function tearDown(): void
    {
        Config::set('cms.active_theme', $this->previousTheme);
        Theme::resetCache();

        parent::tearDown();
    }

    public function testManifestIsServedAndParsed(): void
    {
        $response = $this->get('/manifest.webmanifest');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/manifest+json', (string) $response->headers->get('Content-Type'));

        $manifest = json_decode($response->getContent(), true);
        $this->assertIsArray($manifest);
        $this->assertSame('/', $manifest['id']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('ru', $manifest['lang']);
        $this->assertStringContainsString('utm_source=pwa', $manifest['start_url']);
    }

    public function testManifestIconsExistOnDisk(): void
    {
        $manifest = json_decode($this->get('/manifest.webmanifest')->getContent(), true);

        $this->assertCount(4, $manifest['icons']);

        foreach ($manifest['icons'] as $icon) {
            $path = Pwa::pathFor($icon['src']);

            $this->assertNotNull($path, 'Неизвестный адрес иконки ' . $icon['src']);
            $this->assertFileExists($path);
        }
    }

    public function testManifestShortcutsPointToRealSections(): void
    {
        $manifest = json_decode($this->get('/manifest.webmanifest')->getContent(), true);

        $this->assertCount(3, $manifest['shortcuts']);

        foreach ($manifest['shortcuts'] as $shortcut) {
            $this->assertStringStartsWith('/', $shortcut['url']);
            $this->assertNotSame('', trim($shortcut['name']));
        }
    }

    public function testServiceWorkerIsServedWithVersionAndShell(): void
    {
        $response = $this->get('/sw.js');

        $response->assertStatus(200);
        $this->assertStringContainsString('javascript', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('/', $response->headers->get('Service-Worker-Allowed'));

        $body = $response->getContent();

        $this->assertMatchesRegularExpression("/const VERSION = '[0-9a-f]{8}';/", $body);
        $this->assertStringNotContainsString('__VERSION__', $body);
        $this->assertStringNotContainsString('__SHELL__', $body);
        $this->assertStringContainsString('const SHELL = [', $body);
        $this->assertStringContainsString('"/offline.html"', $body);
        $this->assertStringContainsString('"/manifest.webmanifest"', $body);
        $this->assertStringContainsString("url.pathname.startsWith('/admin/')", $body);
    }

    public function testShellFilesExistAndVersionIsStable(): void
    {
        foreach (Pwa::SHELL as $url) {
            $path = Pwa::pathFor($url);

            $this->assertNotNull($path, 'Нет соответствия для ' . $url);
            $this->assertFileExists($path, 'Файл оболочки отсутствует: ' . $url);
        }

        $this->assertSame(Pwa::version(), Pwa::version());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', Pwa::version());
    }

    public function testVersionDependsOnShellContents(): void
    {
        $this->assertNotSame(
            Pwa::version(['/manifest.webmanifest']),
            Pwa::version(['/offline.html'])
        );
    }

    public function testOfflinePageIsSafeForCsp(): void
    {
        $response = $this->get('/offline.html');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));

        $body = $response->getContent();

        $this->assertStringContainsString('Нет подключения к интернету', $body);
        $this->assertStringNotContainsString('onclick=', $body);
        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringContainsString('href="/"', $body);
    }

    public function testLayoutLinksManifestAndRegistersWorker(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('rel="manifest" href="' . url('/manifest.webmanifest') . '"', $html);
        $this->assertStringContainsString('assets/js/pwa.js', $html);
    }

    public function testRegistrationScriptUsesRootScope(): void
    {
        $script = (string) file_get_contents(base_path('themes/nlb/assets/js/pwa.js'));

        $this->assertStringContainsString("register('/sw.js', { scope: '/' })", $script);
        $this->assertStringNotContainsString('/themes/nlb/assets/pwa/sw.js', $script);
    }
}
