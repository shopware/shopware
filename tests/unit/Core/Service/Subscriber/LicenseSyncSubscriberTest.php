<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Subscriber;

use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Service\AuthenticatedServiceClient;
use Shopware\Core\Service\ServiceClientFactory;
use Shopware\Core\Service\ServiceException;
use Shopware\Core\Service\ServiceRegistryClient;
use Shopware\Core\Service\ServiceRegistryEntry;
use Shopware\Core\Service\Subscriber\LicenseSyncSubscriber;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[CoversClass(LicenseSyncSubscriber::class)]
#[Package('core')]
class LicenseSyncSubscriberTest extends TestCase
{
    /**
     * @var StaticEntityRepository<AppCollection>
     */
    private StaticEntityRepository $appRepository;

    private ServiceRegistryClient&MockObject $serviceRegistryClient;

    private ServiceClientFactory&MockObject $clientFactory;

    private LicenseSyncSubscriber $subscriber;

    private StaticSystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->serviceRegistryClient = $this->createMock(ServiceRegistryClient::class);
        $this->clientFactory = $this->createMock(ServiceClientFactory::class);
        $this->systemConfigService = new StaticSystemConfigService();
        $this->appRepository = new StaticEntityRepository([]);

        $this->subscriber = new LicenseSyncSubscriber(
            $this->systemConfigService,
            $this->serviceRegistryClient,
            $this->appRepository,
            new Logger('test'),
            $this->clientFactory,
        );
    }

    public function testGetSubscribedEventsReturnsCorrectEvents(): void
    {
        $expectedEvents = [
            AppInstalledEvent::class => 'serviceInstalled',
            AppUpdatedEvent::class => 'serviceInstalled',
            SystemConfigChangedEvent::class => 'syncLicense',
        ];

        static::assertEquals($expectedEvents, LicenseSyncSubscriber::getSubscribedEvents());
    }

    public function testLicenseSyncWithValidLicense(): void
    {
        $event = new SystemConfigChangedEvent(
            LicenseSyncSubscriber::CONFIG_STORE_LICENSE_KEY,
            'valid_license_key',
            Uuid::randomHex(),
        );

        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setUniqueIdentifier('app_id');
        $app->setAppSecret('app_secret');
        $app->setName('app_name');
        $app->setSelfManaged(true);

        $app2 = new AppEntity();
        $app2->setId(Uuid::randomHex());
        $app2->setUniqueIdentifier('app_id_2');
        $app2->setAppSecret('app_secret_2');
        $app2->setName('app_name_2');
        $app2->setSelfManaged(true);

        $app3 = new AppEntity();
        $app3->setId(Uuid::randomHex());
        $app3->setUniqueIdentifier('app_id_3');
        $app3->setSelfManaged(false);

        $serviceEntry = new ServiceRegistryEntry('serviceA', 'description', 'host', 'appEndpoint', true, 'licenseSyncEndPoint');

        $this->appRepository = new StaticEntityRepository([
            new EntityCollection([$app, $app2, $app3]),
        ]);

        $this->subscriber = new LicenseSyncSubscriber(
            $this->systemConfigService,
            $this->serviceRegistryClient,
            $this->appRepository,
            new Logger('test'),
            $this->clientFactory,
        );

        $serviceAuthedClient = $this->createMock(AuthenticatedServiceClient::class);

        $this->serviceRegistryClient->method('get')->willReturn($serviceEntry);
        $this->clientFactory->method('newAuthenticatedFor')->willReturn($serviceAuthedClient);

        $this->clientFactory->expects(static::exactly(2))->method('newAuthenticatedFor');
        $serviceAuthedClient->expects(static::exactly(2))->method('syncLicense');
        $this->subscriber->syncLicense($event);
    }

    public function testLicenseIsNotSyncedWhenAppSecretIsNull(): void
    {
        $event = new SystemConfigChangedEvent(
            LicenseSyncSubscriber::CONFIG_STORE_LICENSE_KEY,
            'valid_license_key',
            Uuid::randomHex(),
        );

        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setUniqueIdentifier('app_id');
        $app->setName('app_name');
        $app->setSelfManaged(true);

        $serviceEntry = new ServiceRegistryEntry('serviceA', 'description', 'host', 'appEndpoint', true);

        $this->appRepository = new StaticEntityRepository([
            new EntitySearchResult(
                'app',
                1,
                new EntityCollection([$app]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            ),
        ]);

        $this->subscriber = new LicenseSyncSubscriber(
            $this->systemConfigService,
            $this->serviceRegistryClient,
            $this->appRepository,
            new Logger('test'),
            $this->clientFactory,
        );

        $serviceAuthedClient = $this->createMock(AuthenticatedServiceClient::class);

        $this->serviceRegistryClient->method('get')->willReturn($serviceEntry);

        $this->clientFactory->expects(static::never())->method('newAuthenticatedFor');
        $serviceAuthedClient->expects(static::never())->method('syncLicense');
        $this->subscriber->syncLicense($event);
    }

    public function testLicenseIsNotSyncedWhenAppIsNotService(): void
    {
        $event = new SystemConfigChangedEvent(
            LicenseSyncSubscriber::CONFIG_STORE_LICENSE_KEY,
            'valid_license_key',
            Uuid::randomHex(),
        );

        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setUniqueIdentifier('app_id');
        $app->setName('app_name');
        $app->setSelfManaged(false);
        $app->setAppSecret('app_secret');

        $serviceEntry = new ServiceRegistryEntry('serviceA', 'description', 'host', 'appEndpoint', true);

        $this->appRepository = new StaticEntityRepository([
            new EntitySearchResult(
                'app',
                1,
                new EntityCollection([$app]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            ),
        ]);

        $this->subscriber = new LicenseSyncSubscriber(
            $this->systemConfigService,
            $this->serviceRegistryClient,
            $this->appRepository,
            new Logger('test'),
            $this->clientFactory,
        );

        $serviceAuthedClient = $this->createMock(AuthenticatedServiceClient::class);

        $this->serviceRegistryClient->method('get')->willReturn($serviceEntry);

        $this->clientFactory->expects(static::never())->method('newAuthenticatedFor');
        $serviceAuthedClient->expects(static::never())->method('syncLicense');
        $this->subscriber->syncLicense($event);
    }

    public function testLicenseIsNotSyncedWhenIntegrationIdDoesNotMatch(): void
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setUniqueIdentifier('app_id');
        $app->setName('app_name');
        $app->setSelfManaged(true);
        $app->setAppSecret('app_secret');
        $app->setIntegrationId('not_match_integration_id');

        $event = new AppInstalledEvent(
            $app,
            $this->createMock(Manifest::class),
            Context::createCLIContext(new AdminApiSource(
                'user_id',
                'integration_id',
            )),
        );

        $this->clientFactory->expects(static::never())->method('newAuthenticatedFor');
        $this->subscriber->serviceInstalled($event);
    }

    public function testLicenseIsNotSyncedWhenClientFactoryFails(): void
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setUniqueIdentifier('app_id');
        $app->setName('app_name');
        $app->setSelfManaged(true);
        $app->setAppSecret('app_secret');
        $app->setIntegrationId('integration_id');

        $event = new AppInstalledEvent(
            $app,
            $this->createMock(Manifest::class),
            Context::createCLIContext(new AdminApiSource(
                'user_id',
                'integration_id',
            )),
        );

        $this->clientFactory->method('newAuthenticatedFor')->willThrowException(new \Exception('error'));
        $this->clientFactory->expects(static::never())->method('newAuthenticatedFor');

        $this->subscriber->serviceInstalled($event);
    }

    public function testLicenseIsNotSyncedWhenServiceDefinitionDoesNotSpecifyLicenseEndpoint(): void
    {
        $event = new SystemConfigChangedEvent(
            LicenseSyncSubscriber::CONFIG_STORE_LICENSE_KEY,
            'valid_license_key',
            Uuid::randomHex(),
        );

        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setUniqueIdentifier('app_id');
        $app->setAppSecret('app_secret');
        $app->setName('app_name');
        $app->setSelfManaged(true);

        $serviceEntry = new ServiceRegistryEntry('serviceA', 'description', 'host', 'appEndpoint', true);

        $this->appRepository = new StaticEntityRepository([
            new EntitySearchResult(
                'app',
                1,
                new EntityCollection([$app]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            ),
        ]);

        $this->subscriber = new LicenseSyncSubscriber(
            $this->systemConfigService,
            $this->serviceRegistryClient,
            $this->appRepository,
            new Logger('test'),
            $this->clientFactory,
        );

        $serviceAuthedClient = $this->createMock(AuthenticatedServiceClient::class);

        $this->serviceRegistryClient->method('get')->willReturn($serviceEntry);

        $this->clientFactory->expects(static::never())->method('newAuthenticatedFor');
        $serviceAuthedClient->expects(static::never())->method('syncLicense');
        $this->subscriber->syncLicense($event);
    }

    public function testLicenseIsNotSyncedWhenLicenseIsInvalid(): void
    {
        $event = new SystemConfigChangedEvent(
            LicenseSyncSubscriber::CONFIG_STORE_LICENSE_KEY,
            '',
            Uuid::randomHex(),
        );

        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setUniqueIdentifier('app_id');
        $app->setAppSecret('app_secret');
        $app->setName('app_name');
        $app->setSelfManaged(true);

        $serviceEntry = new ServiceRegistryEntry('serviceA', 'description', 'host', 'appEndpoint', true, 'licenseSyncEndPoint');

        $this->appRepository = new StaticEntityRepository([
            new EntitySearchResult(
                'app',
                1,
                new EntityCollection([$app]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            ),
        ]);

        $this->subscriber = new LicenseSyncSubscriber(
            $this->systemConfigService,
            $this->serviceRegistryClient,
            $this->appRepository,
            new Logger('test'),
            $this->clientFactory,
        );

        $serviceAuthedClient = $this->createMock(AuthenticatedServiceClient::class);

        $this->serviceRegistryClient->method('get')->willReturn($serviceEntry);

        $this->clientFactory->expects(static::never())->method('newAuthenticatedFor');
        $serviceAuthedClient->expects(static::never())->method('syncLicense');
        $this->subscriber->syncLicense($event);
    }

    public function testLicenseIsNotSyncedWithInvalidConfigChanges(): void
    {
        $event = new SystemConfigChangedEvent(
            'invalid_key',
            'valid_license_key',
            Uuid::randomHex(),
        );

        $this->serviceRegistryClient->expects(static::never())->method('get');
        $this->subscriber->syncLicense($event);
    }

    public function testLicenseIsNotSyncedWithInvalidConfigValue(): void
    {
        $event = new SystemConfigChangedEvent(
            LicenseSyncSubscriber::CONFIG_STORE_LICENSE_KEY,
            null,
            Uuid::randomHex(),
        );

        $this->serviceRegistryClient->expects(static::never())->method('get');
        $this->subscriber->syncLicense($event);
    }

    public function testLicenseIsSyncedOnAppInstall(): void
    {
        $app = new AppEntity();
        $app->setAppSecret('app_secret');
        $app->setName('app_name');
        $app->setSelfManaged(true);
        $context = Context::createDefaultContext();

        $event = new AppInstalledEvent($app, $this->createMock(Manifest::class), $context);

        $this->systemConfigService = new StaticSystemConfigService(
            [
                LicenseSyncSubscriber::CONFIG_STORE_LICENSE_KEY => 'shop_secret',
            ]
        );

        $this->subscriber = new LicenseSyncSubscriber(
            $this->systemConfigService,
            $this->serviceRegistryClient,
            $this->appRepository,
            new Logger('test'),
            $this->clientFactory,
        );

        $serviceEntry = new ServiceRegistryEntry('serviceA', 'description', 'host', 'appEndpoint', true, 'licenseSyncEndPoint');

        $this->serviceRegistryClient->method('get')->willReturn($serviceEntry);

        $this->clientFactory->expects(static::once())->method('newAuthenticatedFor');
        $this->subscriber->serviceInstalled($event);
    }

    public function testLicenseIsNotSyncedOnAppInstallWithInvalidSecret(): void
    {
        $context = Context::createDefaultContext();
        $app = new AppEntity();
        $app->setName('app_name');
        $app->setSelfManaged(true);

        $event = new AppInstalledEvent($app, $this->createMock(Manifest::class), $context);

        $this->systemConfigService = new StaticSystemConfigService([]);

        $this->serviceRegistryClient->expects(static::never())->method('get');
        $this->subscriber->serviceInstalled($event);
    }

    public function testLicenseIsNotSyncIfThrowException(): void
    {
        $event = new SystemConfigChangedEvent(
            LicenseSyncSubscriber::CONFIG_STORE_LICENSE_KEY,
            'valid_license_key',
            Uuid::randomHex(),
        );

        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setUniqueIdentifier('app_id');
        $app->setName('app_name');
        $app->setSelfManaged(true);
        $app->setAppSecret('app_secret');

        $serviceEntry = new ServiceRegistryEntry('serviceA', 'description', 'host', 'appEndpoint', true, 'licenseSyncEndPoint');

        $this->appRepository = new StaticEntityRepository([
            new EntitySearchResult(
                'app',
                1,
                new EntityCollection([$app]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            ),
        ]);

        $this->subscriber = new LicenseSyncSubscriber(
            $this->systemConfigService,
            $this->serviceRegistryClient,
            $this->appRepository,
            new Logger('test'),
            $this->clientFactory,
        );

        $serviceAuthedClient = $this->createMock(AuthenticatedServiceClient::class);
        $this->serviceRegistryClient->method('get')->willReturn($serviceEntry);

        $this->clientFactory->method('newAuthenticatedFor')->willThrowException(new ServiceException(301, 'error', 'error'));

        $serviceAuthedClient->expects(static::never())->method('syncLicense');
        $this->subscriber->syncLicense($event);
    }
}
