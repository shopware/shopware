<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Service\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ServiceControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use IntegrationTestBehaviour;

    public function testUninstallDoesNotRequireApiServiceTogglePrivilege(): void
    {
        $integrationId = Uuid::randomHex();
        $browser = $this->getBrowserAuthenticatedWithIntegration($integrationId);
        $connection = static::getContainer()->get(Connection::class);

        $connection->update('integration', [
            'admin' => 0,
        ], [
            'id' => Uuid::fromHexToBytes($integrationId),
        ]);

        $this->createServiceApp($integrationId);

        $browser->request('POST', '/api/service/uninstall/TestService');

        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode());
        static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM app WHERE name = :name', [
            'name' => 'TestService',
        ]));
    }

    public function testUninstallRequiresIntegrationAuthentication(): void
    {
        $this->getBrowser()->request('POST', '/api/service/uninstall/TestService');

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->getBrowser()->getResponse()->getStatusCode());

        $response = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('SERVICE__UPDATE_REQUIRES_INTEGRATION', $response['errors'][0]['code']);
    }

    private function createServiceApp(string $integrationId): void
    {
        /** @var EntityRepository<AppCollection> $appRepository */
        $appRepository = static::getContainer()->get('app.repository');

        $appRepository->create([[
            'name' => 'TestService',
            'path' => __DIR__ . '/../../Framework/App/Command/_fixtures/withoutPermissions',
            'version' => '0.9.0',
            'label' => 'Test service',
            'accessToken' => 'test',
            'integrationId' => $integrationId,
            'selfManaged' => true,
            'aclRole' => [
                'name' => 'TestService',
                'privileges' => [],
            ],
        ]], Context::createDefaultContext());
    }
}
