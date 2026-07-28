<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Acl;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('framework')]
class AdminActionRouteAclTest extends TestCase
{
    use AdminApiTestBehaviour;
    use IntegrationTestBehaviour;

    /**
     * @param list<string> $expectedAcl
     */
    #[DataProvider('routeAclProvider')]
    public function testAdminActionRouteRequiresPrivilege(string $routeName, array $expectedAcl): void
    {
        $route = static::getContainer()->get(RouterInterface::class)->getRouteCollection()->get($routeName);

        static::assertNotNull($route, \sprintf('Route "%s" not found', $routeName));
        static::assertSame($expectedAcl, $route->getDefault(PlatformRequest::ATTRIBUTE_ACL));
    }

    /**
     * @return iterable<string, array{0: string, 1: list<string>}>
     */
    public static function routeAclProvider(): iterable
    {
        yield 'message-queue consume' => ['api.action.message-queue.consume', ['system:queue:process']];
        yield 'scheduled-task run' => ['api.action.scheduled-task.run', ['system:queue:process']];
        yield 'scheduled-task min-run-interval' => ['api.action.scheduled-task.min-run-interval', ['scheduled_task:read']];
        yield 'increment' => ['api.increment.increment', ['increment:manage']];
        yield 'decrement' => ['api.increment.decrement', ['increment:manage']];
        yield 'increment list' => ['api.increment.list', ['increment:manage']];
        yield 'increment reset' => ['api.increment.reset', ['increment:manage']];
        yield 'increment delete' => ['api.increment.delete', ['increment:manage']];
        yield 'consents fetch' => ['api.consents.fetch', ['consent:manage']];
        yield 'consents accept' => ['api.consents.accept', ['consent:manage']];
        yield 'consents revoke' => ['api.consents.revoke', ['consent:manage']];
        yield 'seo-url-template context' => ['api.seo-url-template.context', ['seo_url_template:read']];
        yield 'seo-url-template default' => ['api.seo-url-template.default', ['seo_url_template:read']];
        yield 'seo-url canonical' => ['api.seo-url.canonical', ['seo_url:update']];
        yield 'seo-url create custom url' => ['api.seo-url.create', ['seo_url:create']];
        yield 'media upload' => ['api.action.media.upload', ['media:create']];
        yield 'media rename' => ['api.action.media.rename', ['media:update']];
        yield 'media provide-name' => ['api.action.media.provide-name', ['media:read']];
    }

    public function testUserWithoutPrivilegeGetsForbidden(): void
    {
        $browser = $this->authorizeTestUser(['product:read']);

        $browser->request('GET', '/api/_action/media/provide-name', ['fileName' => 'phpunit']);
        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode());
        static::assertStringContainsString('FRAMEWORK__MISSING_PRIVILEGE_ERROR', (string) $browser->getResponse()->getContent());

        $browser->request('PATCH', '/api/_action/seo-url/canonical');
        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode());
        static::assertStringContainsString('FRAMEWORK__MISSING_PRIVILEGE_ERROR', (string) $browser->getResponse()->getContent());
    }

    public function testUserWithPrivilegeIsAllowed(): void
    {
        $browser = $this->authorizeTestUser(['media:read']);

        $browser->request('GET', '/api/_action/media/provide-name', ['fileName' => 'phpunit', 'extension' => 'png']);

        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
    }

    public function testDefaultUserPrivilegesCoverAdminRuntimeEndpoints(): void
    {
        $browser = $this->authorizeTestUser(['product:read']);

        $browser->request('GET', '/api/_action/scheduled-task/min-run-interval');
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());

        $browser->request('GET', '/api/_action/increment/user_activity', ['cluster' => 'phpunit']);
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
    }

    /**
     * Admin users bypass ACL entirely (see AdminApiSource::isAllowed), so the assertions above
     * cannot prove the route privileges are sufficient. This runs as a non-admin user holding
     * exactly the route's privilege: any non-403 response — even a validation error — shows the
     * request passed the ACL layer. An empty privilege list proves the endpoint is usable through
     * AdminApiSource::DEFAULT_PRIVILEGES alone.
     *
     * @param list<string> $privileges
     * @param array<string, string> $routeParams
     * @param array<string, mixed> $requestParams
     */
    #[DataProvider('privilegeSufficiencyProvider')]
    public function testRoutePrivilegeIsSufficient(
        string $routeName,
        array $privileges,
        array $routeParams = [],
        array $requestParams = []
    ): void {
        $router = static::getContainer()->get(RouterInterface::class);
        $route = $router->getRouteCollection()->get($routeName);
        static::assertNotNull($route, \sprintf('Route "%s" not found', $routeName));

        $browser = $this->authorizeTestUser($privileges);
        $browser->request($route->getMethods()[0], $router->generate($routeName, $routeParams), $requestParams);

        $response = $browser->getResponse();
        static::assertNotSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
        static::assertStringNotContainsString('FRAMEWORK__MISSING_PRIVILEGE', (string) $response->getContent());
    }

    /**
     * @return iterable<string, array{0: string, 1: list<string>, 2?: array<string, string>, 3?: array<string, mixed>}>
     */
    public static function privilegeSufficiencyProvider(): iterable
    {
        yield 'message-queue consume passes with default privileges' => ['api.action.message-queue.consume', []];
        yield 'scheduled-task run passes with default privileges' => ['api.action.scheduled-task.run', []];
        yield 'consents fetch passes with default privileges' => ['api.consents.fetch', []];
        yield 'consents accept passes with default privileges' => ['api.consents.accept', []];
        yield 'consents revoke passes with default privileges' => ['api.consents.revoke', []];
        yield 'increment passes with default privileges' => ['api.increment.increment', [], ['pool' => 'user_activity']];
        yield 'decrement passes with default privileges' => ['api.increment.decrement', [], ['pool' => 'user_activity']];
        yield 'increment reset passes with default privileges' => ['api.increment.reset', [], ['pool' => 'user_activity']];
        yield 'increment delete passes with default privileges' => ['api.increment.delete', [], ['pool' => 'user_activity']];
        yield 'seo-url-template context passes with template read privilege' => ['api.seo-url-template.context', ['seo_url_template:read'], [], ['routeName' => 'frontend.detail.page']];
        yield 'seo-url-template default passes with template read privilege' => ['api.seo-url-template.default', ['seo_url_template:read'], ['routeName' => 'frontend.detail.page']];
        yield 'seo-url canonical passes with seo-url update privilege' => ['api.seo-url.canonical', ['seo_url:update']];
        yield 'seo-url create custom url passes with seo-url create privilege' => ['api.seo-url.create', ['seo_url:create'], [], ['urls' => [['seoPathInfo' => 'phpunit']]]];
        yield 'media upload passes with media create privilege' => ['api.action.media.upload', ['media:create'], ['mediaId' => Uuid::randomHex()]];
        yield 'media rename passes with media update privilege' => ['api.action.media.rename', ['media:update'], ['mediaId' => Uuid::randomHex()]];
    }

    /**
     * @param list<string> $privileges
     */
    private function authorizeTestUser(array $privileges): KernelBrowser
    {
        $this->resetBrowser();
        $browser = $this->getBrowser();

        $user = TestUser::createNewTestUser(static::getContainer()->get(Connection::class), $privileges);
        $user->authorizeBrowser($browser);

        return $browser;
    }
}
