<?php namespace Nlb\Library\Tests;

use Illuminate\Http\Request;
use Nlb\Library\Classes\Http\SecurityHeaders;
use PluginTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * SecurityHeadersTest — CSP, X-Frame-Options и защитные заголовки.
 *
 * Middleware подключается только в HTTP-контексте (`runningInConsole()` в
 * `Plugin::boot()`), поэтому проверяется напрямую: класс, запрос, ответ.
 * Заголовки — часть периметра, и их отсутствие не видно ни в тестах страниц,
 * ни в DOM-паритете; проверяем ровно то, что уедет в браузер.
 */
class SecurityHeadersTest extends PluginTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        SecurityHeaders::reset();
    }

    public function tearDown(): void
    {
        SecurityHeaders::reset();

        parent::tearDown();
    }

    public function testFrontendGetsStaticHeaders(): void
    {
        $response = $this->handle('/');

        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $this->assertSame('camera=(), microphone=(), geolocation=()', $response->headers->get('Permissions-Policy'));
    }

    public function testFrontendDeniesFraming(): void
    {
        $response = $this->handle('/');

        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
    }

    public function testCspHasConservativeDirectives(): void
    {
        $csp = $this->handle('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringContainsString("img-src 'self' data:", $csp);
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline'", $csp);
        $this->assertStringContainsString("connect-src 'self'", $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
    }

    public function testCspGivesInlineScriptANonce(): void
    {
        $response = $this->handle('/');
        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertSame(24, strlen(SecurityHeaders::nonce()));
        $this->assertStringContainsString("script-src 'self' 'nonce-" . SecurityHeaders::nonce() . "'", $csp);
        $this->assertStringContainsString('nonce-', $csp);
    }

    public function testNonceIsUniquePerRequest(): void
    {
        $first = $this->nonceOf($this->handle('/'));
        $second = $this->nonceOf($this->handle('/'));

        $this->assertNotSame('', $first);
        $this->assertNotSame($first, $second);
    }

    public function testInsecureRequestHasNoHsts(): void
    {
        $response = $this->handle('/');

        $this->assertNull($response->headers->get('Strict-Transport-Security'));
        $this->assertStringNotContainsString('upgrade-insecure-requests', $response->headers->get('Content-Security-Policy'));
    }

    public function testSecureRequestHasHstsAndUpgrade(): void
    {
        $response = $this->handle('/', true);

        $this->assertSame('max-age=31536000; includeSubDomains', $response->headers->get('Strict-Transport-Security'));
        $this->assertStringContainsString('upgrade-insecure-requests', $response->headers->get('Content-Security-Policy'));
    }

    public function testBackendHasNoCspAndAllowsSameOriginFrames(): void
    {
        $response = $this->handle('/admin');

        $this->assertNull($response->headers->get('Content-Security-Policy'));
        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function testBackendNestedPathIsAlsoExcluded(): void
    {
        $response = $this->handle('/admin/nlb/library/books');

        $this->assertNull($response->headers->get('Content-Security-Policy'));
        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
    }

    public function testAdminLookalikePathGetsFrontendPolicy(): void
    {
        $response = $this->handle('/administrator');

        $this->assertNotNull($response->headers->get('Content-Security-Policy'));
        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
    }

    public function testHeadersAreAppliedToErrorResponses(): void
    {
        $response = $this->handle('/', false, 404);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertNotNull($response->headers->get('Content-Security-Policy'));
    }

    public function testResetClearsNonce(): void
    {
        $this->handle('/');
        $this->assertNotSame('', SecurityHeaders::nonce());

        SecurityHeaders::reset();

        $this->assertSame('', SecurityHeaders::nonce());
    }

    protected function handle(string $uri, bool $secure = false, int $status = 200): Response
    {
        $request = Request::create(($secure ? 'https' : 'http') . '://localhost' . $uri);
        $middleware = new SecurityHeaders();

        return $middleware->handle($request, function () use ($status) {
            return new Response('ok', $status);
        });
    }

    protected function nonceOf(Response $response): string
    {
        $csp = (string) $response->headers->get('Content-Security-Policy');

        if (preg_match("/'nonce-([^']+)'/", $csp, $matches) !== 1) {
            return '';
        }

        return $matches[1];
    }
}
