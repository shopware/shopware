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
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('framework')]
class IncrementRouteAclTest extends TestCase
{
    use AdminApiTestBehaviour;
    use IntegrationTestBehaviour;

    /**
     * @param list<string> $expectedAcl
     */
    #[DataProvider('routeAclProvider')]
    public function testRouteRequiresPrivilege(string $routeName, array $expectedAcl): void
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
        yield 'increment' => ['api.increment.increment', ['increment:manage']];
        yield 'decrement' => ['api.increment.decrement', ['increment:manage']];
        yield 'increment list' => ['api.increment.list', ['increment:manage']];
        yield 'increment reset' => ['api.increment.reset', ['increment:manage']];
        yield 'increment delete' => ['api.increment.delete', ['increment:manage']];
        yield 'queue stats' => ['api.info.queue', ['message_queue_stats:read']];
        yield 'message stats' => ['api.info.message-stats', ['message_queue_stats:read']];
    }

    public function testIntegrationWithoutPrivilegeGetsForbidden(): void
    {
        $ids = new IdsCollection();
        $browser = $this->getBrowserAuthenticatedWithIntegration($ids->create('integration'));

        static::getContainer()
            ->get(Connection::class)
            ->executeStatement('UPDATE `integration` SET `admin` = 0 WHERE id = :id', ['id' => Uuid::fromHexToBytes($ids->get('integration'))]);

        $browser->request('GET', '/api/_action/increment/user_activity', ['cluster' => 'phpunit']);
        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode());
        static::assertStringContainsString('FRAMEWORK__MISSING_PRIVILEGE_ERROR', (string) $browser->getResponse()->getContent());

        $browser->request('GET', '/api/_info/queue.json');
        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode());
        static::assertStringContainsString('FRAMEWORK__MISSING_PRIVILEGE_ERROR', (string) $browser->getResponse()->getContent());
    }

    public function testDefaultUserPrivilegesCoverIncrementAndQueueEndpoints(): void
    {
        $this->resetBrowser();
        $browser = $this->getBrowser();

        $user = TestUser::createNewTestUser(static::getContainer()->get(Connection::class), ['product:read']);
        $user->authorizeBrowser($browser);

        $browser->request('GET', '/api/_action/increment/user_activity', ['cluster' => 'phpunit']);
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());

        $browser->request('GET', '/api/_info/queue.json');
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
    }
}
