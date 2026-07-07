<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Service\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ServiceControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    /**
     * @var EntityRepository<AppCollection>
     */
    private EntityRepository $appRepository;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->appRepository = static::getContainer()->get('app.repository');
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testServiceCanUninstallItself(): void
    {
        $serviceName = 'RemoteUninstall' . Uuid::randomHex();
        $integrationId = Uuid::randomHex();

        $browser = $this->getBrowserAuthenticatedWithIntegration($integrationId);
        $appId = $this->createServiceApp($serviceName, $integrationId);

        $browser->jsonRequest('POST', '/api/service/uninstall/' . $serviceName);

        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        static::assertNull($this->appRepository->search(new Criteria([$appId]), Context::createDefaultContext())->first());
    }

    public function testServiceCannotUninstallAnotherService(): void
    {
        $ownServiceName = 'RemoteUninstallOwn' . Uuid::randomHex();
        $otherServiceName = 'RemoteUninstallOther' . Uuid::randomHex();
        $integrationId = Uuid::randomHex();

        $browser = $this->getBrowserAuthenticatedWithIntegration($integrationId);
        $this->createServiceApp($ownServiceName, $integrationId);
        $otherAppId = $this->createServiceApp($otherServiceName, $this->createIntegration());

        $browser->jsonRequest('POST', '/api/service/uninstall/' . $otherServiceName);

        static::assertSame(Response::HTTP_NOT_FOUND, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        static::assertNotNull($this->appRepository->search(new Criteria([$otherAppId]), Context::createDefaultContext())->first());
    }

    /**
     * @return string the app id
     */
    private function createServiceApp(string $serviceName, string $integrationId): string
    {
        $appId = Uuid::randomHex();

        $app = [
            'id' => $appId,
            'name' => $serviceName,
            'path' => __DIR__ . '/../../Framework/App/Command/_fixtures/withoutPermissions',
            'version' => '1.0.0',
            'label' => $serviceName,
            'active' => true,
            'accessToken' => 'test',
            'selfManaged' => true,
            'integrationId' => $integrationId,
            'aclRole' => [
                'name' => $serviceName,
                'privileges' => [],
            ],
        ];

        $this->appRepository->create([$app], Context::createDefaultContext());

        return $appId;
    }

    private function createIntegration(): string
    {
        $integrationId = Uuid::randomHex();

        $this->connection->insert('integration', [
            'id' => Uuid::fromHexToBytes($integrationId),
            'label' => 'test integration',
            'access_key' => AccessKeyHelper::generateAccessKey('integration'),
            'secret_access_key' => TestDefaults::HASHED_PASSWORD,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $integrationId;
    }
}
