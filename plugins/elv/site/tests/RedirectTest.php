<?php namespace Elv\Site\Tests;

use Backend\Models\User as BackendUser;
use Backend\Models\UserRole;
use Elv\Site\Classes\Http\ManagedRedirect;
use Elv\Site\Models\Redirect;
use Illuminate\Http\Request;
use October\Rain\Database\ModelException;
use October\Rain\Exception\ForbiddenException;
use PluginTestCase;
use Schema;

/**
 * RedirectTest — редиректы из админки: хранение, отдача и права.
 *
 * Хардкод-карта старых адресов проверяется в `SeoTest`; здесь — второй слой:
 * правило, которое редактор завёл сам, и его поведение на живом пути.
 */
class RedirectTest extends PluginTestCase
{
    protected $useTransactions = true;

    public function testRedirectsTableHasExpectedColumns()
    {
        $this->assertTrue(Schema::hasTable('elv_redirects'));

        foreach (['from_path', 'to_path', 'status_code', 'is_enabled', 'hit_count', 'last_hit_at'] as $column) {
            $this->assertTrue(
                Schema::hasColumn('elv_redirects', $column),
                'В elv_redirects нет колонки ' . $column
            );
        }
    }

    public function testFromPathIsNormalizedBeforeValidation()
    {
        $redirect = Redirect::create([
            'from_path' => 'news//old-page/?utm_source=mail',
            'to_path' => '/news/new-page/',
        ]);

        $this->assertSame('/news/old-page', $redirect->from_path);
        $this->assertSame('/news/new-page', $redirect->to_path);
        $this->assertSame(301, $redirect->status_code);
        $this->assertTrue($redirect->is_enabled);
        $this->assertSame(0, $redirect->hit_count);
    }

    public function testDuplicateIsRejectedAfterNormalization()
    {
        Redirect::create(['from_path' => '/news/old', 'to_path' => '/news/new']);

        $this->expectException(ModelException::class);

        Redirect::create(['from_path' => '/news/old/', 'to_path' => '/other']);
    }

    public function testHomePageCannotBeRedirected()
    {
        $this->expectException(ModelException::class);

        Redirect::create(['from_path' => '/', 'to_path' => '/news/']);
    }

    public function testLoopToItselfIsRejected()
    {
        $this->expectException(ModelException::class);

        Redirect::create(['from_path' => '/news/old', 'to_path' => '/news/old/']);
    }

    public function testExternalHttpTargetIsAllowed()
    {
        $redirect = Redirect::create([
            'from_path' => '/old-blog',
            'to_path' => 'https://example.com/blog/',
        ]);

        $this->assertSame('https://example.com/blog/', $redirect->to_path);
    }

    public function testDangerousSchemeIsRejected()
    {
        $this->expectException(ModelException::class);

        Redirect::create([
            'from_path' => '/old-blog',
            'to_path' => 'javascript:alert(1)',
        ]);
    }

    public function testStatusCodeWhitelist()
    {
        $this->expectException(ModelException::class);

        Redirect::create(['from_path' => '/old', 'to_path' => '/new', 'status_code' => 200]);
    }

    public function testMiddlewareRedirectsAndKeepsQuery()
    {
        Redirect::create(['from_path' => '/old-page', 'to_path' => '/new-page']);

        $response = $this->runMiddleware('/old-page?utm_source=mail');

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame(
            'http://localhost/new-page?utm_source=mail',
            (string) $response->headers->get('Location')
        );
    }

    public function testMiddlewareTrailingSlashHitsSameRule()
    {
        Redirect::create(['from_path' => '/old-page', 'to_path' => '/new-page']);

        $response = $this->runMiddleware('/old-page/');

        $this->assertSame(301, $response->getStatusCode());
    }

    public function testMiddlewareHonoursStatusCode()
    {
        Redirect::create([
            'from_path' => '/old-temp',
            'to_path' => '/new-temp',
            'status_code' => 302,
        ]);

        $this->assertSame(302, $this->runMiddleware('/old-temp')->getStatusCode());
    }

    public function testMiddlewareUsesAbsoluteTargetAsIs()
    {
        Redirect::create([
            'from_path' => '/old-blog',
            'to_path' => 'https://example.com/blog/',
        ]);

        $this->assertSame(
            'https://example.com/blog/',
            (string) $this->runMiddleware('/old-blog')->headers->get('Location')
        );
    }

    public function testDisabledRedirectPassesThrough()
    {
        Redirect::create([
            'from_path' => '/old-off',
            'to_path' => '/new-off',
            'is_enabled' => false,
        ]);

        $response = $this->runMiddleware('/old-off');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('passthrough', $response->getContent());
    }

    public function testUnknownPathPassesThrough()
    {
        $response = $this->runMiddleware('/no-such-rule');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testPostIsNotRedirected()
    {
        Redirect::create(['from_path' => '/old-form', 'to_path' => '/new-form']);

        $response = $this->runMiddleware('/old-form', 'POST');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('passthrough', $response->getContent());
    }

    public function testHitCounterIsWritten()
    {
        $redirect = Redirect::create(['from_path' => '/counted', 'to_path' => '/target']);

        $this->runMiddleware('/counted');
        $this->runMiddleware('/counted');

        $redirect = $redirect->fresh();

        $this->assertSame(2, $redirect->hit_count);
        $this->assertNotNull($redirect->last_hit_at);
    }

    public function testCacheIsDroppedWhenRuleChanges()
    {
        $this->assertNull(Redirect::findForPath('/cached-page'));

        $redirect = Redirect::create(['from_path' => '/cached-page', 'to_path' => '/first']);

        $this->assertSame('/first', Redirect::findForPath('/cached-page')['to_path']);

        $redirect->to_path = '/second';
        $redirect->save();

        $this->assertSame('/second', Redirect::findForPath('/cached-page')['to_path']);

        $redirect->delete();

        $this->assertNull(Redirect::findForPath('/cached-page'));
    }

    public function testDisabledRuleIsNotCachedAsActive()
    {
        $redirect = Redirect::create([
            'from_path' => '/toggle',
            'to_path' => '/target',
            'is_enabled' => false,
        ]);

        $this->assertNull(Redirect::findForPath('/toggle'));

        $redirect->is_enabled = true;
        $redirect->save();

        $this->assertSame('/target', Redirect::findForPath('/toggle')['to_path']);
    }

    public function testHomePathIsNeverResolved()
    {
        Redirect::create(['from_path' => '/news/old', 'to_path' => '/news/new']);

        $this->assertNull(Redirect::findForPath('/'));
    }

    public function testRedirectsPageListsRules()
    {
        $this->be($this->makeSuperUser());
        Redirect::create(['from_path' => '/news/old-path', 'to_path' => '/news/new-path']);

        $response = $this->get('/admin/elv/site/redirects');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('/news/old-path', $response->getContent());
        $this->assertStringContainsString('/news/new-path', $response->getContent());
    }

    public function testRedirectCreatePageRendersFields()
    {
        $this->be($this->makeSuperUser());

        $response = $this->get('/admin/elv/site/redirects/create');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Старый адрес', $response->getContent());
        $this->assertStringContainsString('Код ответа', $response->getContent());
    }

    public function testRedirectsPageIsForbiddenWithoutPermission()
    {
        $this->be($this->makeOperator(['elv.site.requests' => 1]));

        $this->expectException(ForbiddenException::class);

        $this->withoutExceptionHandling()->get('/admin/elv/site/redirects');
    }

    protected function runMiddleware(string $url, string $method = 'GET')
    {
        $middleware = new ManagedRedirect;

        return $middleware->handle(Request::create($url, $method), function () {
            return response('passthrough', 200);
        });
    }

    protected function makeSuperUser(): BackendUser
    {
        $user = new BackendUser();
        $user->login = 'qa-redirect';
        $user->email = 'qa-redirect@example.by';
        $user->first_name = 'QA';
        $user->last_name = 'Redirect';
        $user->password = 'secret12345';
        $user->password_confirmation = 'secret12345';
        $user->is_superuser = true;
        $user->save();

        return $user;
    }

    protected function makeOperator(array $permissions): BackendUser
    {
        $role = new UserRole();
        $role->name = 'QA redirect operator';
        $role->code = 'qa-redirect-operator';
        $role->permissions = ['general.backend' => 1] + $permissions;
        $role->save();

        $user = new BackendUser();
        $user->login = 'qa-redirect-operator';
        $user->email = 'qa-redirect-operator@example.by';
        $user->first_name = 'QA';
        $user->last_name = 'Operator';
        $user->password = 'secret12345';
        $user->password_confirmation = 'secret12345';
        $user->role_id = $role->id;
        $user->save();

        return $user;
    }
}
