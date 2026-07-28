<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Acl;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\TestUser;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('framework')]
class ActionRouteAclTest extends TestCase
{
    use AdminApiTestBehaviour;
    use IntegrationTestBehaviour;

    public function testUserWithoutPrivilegeGetsForbidden(): void
    {
        $browser = $this->authorizeTestUser(['product:read']);

        $browser->request('POST', '/api/_action/trigger-event/phpunit.event');
        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode());
        static::assertStringContainsString('FRAMEWORK__MISSING_PRIVILEGE_ERROR', (string) $browser->getResponse()->getContent());

        $browser->request('POST', '/api/app-system/shop-id/change');
        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode());
        static::assertStringContainsString('FRAMEWORK__MISSING_PRIVILEGE_ERROR', (string) $browser->getResponse()->getContent());

        $browser->request('POST', '/api/_action/sso/invite-user');
        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode());
        static::assertStringContainsString('FRAMEWORK__MISSING_PRIVILEGE_ERROR', (string) $browser->getResponse()->getContent());
    }

    /**
     * Admin users bypass ACL entirely (see AdminApiSource::isAllowed), so the assertions above
     * cannot prove the route privileges are sufficient. This runs as a non-admin user holding
     * exactly the route's privilege: any non-403 response — even a validation error — shows the
     * request passed the ACL layer.
     *
     * @param list<string> $privileges
     * @param array<string, string> $routeParams
     * @param array<string, string> $requestParams
     */
    #[DataProvider('privilegeSufficiencyProvider')]
    public function testRoutePrivilegeIsSufficient(string $routeName, array $privileges, array $routeParams = [], array $requestParams = []): void
    {
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
     * @return iterable<string, array{0: string, 1: list<string>, 2?: array<string, string>, 3?: array<string, string>}>
     */
    public static function privilegeSufficiencyProvider(): iterable
    {
        yield 'trigger custom flow event passes with flow dispatch privilege' => ['api.action.trigger_event', ['flow:dispatch'], ['eventName' => 'phpunit.event']];
        yield 'change shop id passes with app change privilege' => ['api.app_system.shop_id.change', ['system:app:change']];
        // the invalid localeId fails DAL write validation (400) before a user is created or mail is sent
        yield 'sso invite user passes with user create privilege' => ['api.action.sso.invite-user', ['user:create'], [], ['email' => 'phpunit-invite@example.com', 'localeId' => 'not-a-locale-id']];
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
