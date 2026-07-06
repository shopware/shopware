<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\AppStateService;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\ManifestFactory;
use Shopware\Core\Framework\App\Source\TemporaryDirectoryFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Service\AppInfo;
use Shopware\Core\Service\Event\ServiceInstalledEvent;
use Shopware\Core\Service\Event\ServiceUpdatedEvent;
use Shopware\Core\Service\Requirement\RequirementsValidator;
use Shopware\Core\Service\ServiceClient;
use Shopware\Core\Service\ServiceClientFactory;
use Shopware\Core\Service\ServiceException;
use Shopware\Core\Service\ServiceLifecycle;
use Shopware\Core\Service\ServiceRegistry\Client as ServiceRegistryClient;
use Shopware\Core\Service\ServiceRegistry\ServiceEntry;
use Shopware\Core\Service\ServiceSourceResolver;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(ServiceLifecycle::class)]
class ServiceLifecycleTest extends TestCase
{
    private ServiceEntry $entry;

    private LoggerInterface&Stub $logger;

    private ServiceRegistryClient&Stub $serviceRegistryClient;

    private ServiceSourceResolver&Stub $sourceResolver;

    private AppStateService&Stub $appState;

    private AppInfo $appInfo;

    /**
     * @var StaticEntityRepository<AppCollection>
     * */
    private EntityRepository $appRepo;

    private RequirementsValidator&Stub $requirementsValidator;

    protected function setUp(): void
    {
        $this->entry = new ServiceEntry('MyCoolService', 'MyCoolService', 'https://example.com', '/service/lifecycle/choose-app');
        $this->appInfo = new AppInfo('MyCoolService', '6.6.0.0', 'a1bcd', '6.6.0.0-a1bcd', 'https://example.com/service/lifecycle/app-zip/6.6.0.0', ['service_consent'], 'sha256', '6.6.0.0');
        $this->logger = static::createStub(LoggerInterface::class);
        $this->serviceRegistryClient = static::createStub(ServiceRegistryClient::class);
        $this->sourceResolver = static::createStub(ServiceSourceResolver::class);
        $this->appState = static::createStub(AppStateService::class);
        $this->appRepo = new StaticEntityRepository([
            [], // empty search for app -> service migration
        ]);
        $this->requirementsValidator = static::createStub(RequirementsValidator::class);
        $this->requirementsValidator->method('isValidSet')->willReturn(true);
    }

    public function testInstallDoesNotLogErrorIfAppCannotBeDownloaded(): void
    {
        $serviceClient = $this->createMock(ServiceClient::class);
        $serviceClient->expects($this->once())->method('latestAppInfo')->willThrowException(ServiceException::missingAppVersionInformation('app-version'));
        $serviceClientFactory = $this->createMock(ServiceClientFactory::class);
        $serviceClientFactory->expects($this->once())->method('newFor')->with($this->entry)->willReturn($serviceClient);

        $manifestFactory = $this->createMock(ManifestFactory::class);
        $manifestFactory->expects($this->never())->method('createFromXmlFile');

        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $appLifecycle->expects($this->never())->method('install');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())->method('dispatch');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->never())
            ->method('error')
            ->with('Cannot install service "MyCoolService" because of error: "Error downloading app. The version information was missing."');

        $lifecycle = new ServiceLifecycle(
            $this->serviceRegistryClient,
            $serviceClientFactory,
            $appLifecycle,
            $this->buildAppRepository(),
            $logger,
            $manifestFactory,
            $this->sourceResolver,
            $this->appState,
            $eventDispatcher,
            $this->requirementsValidator
        );

        $lifecycle->install($this->entry, Context::createDefaultContext());
    }

    public function testInstallLogsErrorIfAppCannotBeInstalled(): void
    {
        $tempDirectoryFactory = static::createStub(TemporaryDirectoryFactory::class);
        $tempDirectoryFactory->method('path')->willReturn('/tmp/path');

        $serviceClient = $this->createMock(ServiceClient::class);
        $serviceClient->expects($this->once())->method('latestAppInfo')->willReturn($this->appInfo);
        $serviceClientFactory = $this->createMock(ServiceClientFactory::class);
        $serviceClientFactory->expects($this->once())->method('newFor')->with($this->entry)->willReturn($serviceClient);

        $sourceResolver = $this->createMock(ServiceSourceResolver::class);
        $sourceResolver->expects($this->once())
            ->method('filesystemForVersion')
            ->with($this->appInfo)
            ->willReturn(new StaticFilesystem());

        $manifest = $this->createManifest();
        $manifestFactory = $this->createMock(ManifestFactory::class);
        $manifestFactory
            ->expects($this->once())
            ->method('createFromXmlFile')
            ->with('/app-root/manifest.xml')
            ->willReturn($manifest);

        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $appLifecycle->expects($this->once())
            ->method('install')
            ->willThrowException(AppException::notCompatible('MyCoolService'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with('Cannot install service "MyCoolService" because of error: "App MyCoolService is not compatible with this Shopware version"');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())->method('dispatch');

        $lifecycle = new ServiceLifecycle(
            $this->serviceRegistryClient,
            $serviceClientFactory,
            $appLifecycle,
            $this->buildAppRepository(),
            $logger,
            $manifestFactory,
            $sourceResolver,
            $this->appState,
            $eventDispatcher,
            $this->requirementsValidator
        );

        static::assertFalse($lifecycle->install($this->entry, Context::createDefaultContext()));
    }

    public function testInstall(): void
    {
        $tempDirectoryFactory = static::createStub(TemporaryDirectoryFactory::class);

        $tempDirectoryFactory->method('path')->willReturn('/tmp/path');

        $serviceClient = $this->createMock(ServiceClient::class);
        $serviceClient->expects($this->once())->method('latestAppInfo')->willReturn($this->appInfo);
        $serviceClientFactory = $this->createMock(ServiceClientFactory::class);
        $serviceClientFactory->expects($this->once())->method('newFor')->with($this->entry)->willReturn($serviceClient);

        $sourceResolver = $this->createMock(ServiceSourceResolver::class);
        $sourceResolver->expects($this->once())
            ->method('filesystemForVersion')
            ->with($this->appInfo)
            ->willReturn(new StaticFilesystem());

        $manifest = $this->createManifest();
        $manifestFactory = $this->createMock(ManifestFactory::class);
        $manifestFactory
            ->expects($this->once())
            ->method('createFromXmlFile')
            ->with('/app-root/manifest.xml')
            ->willReturn($manifest);

        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $appLifecycle->expects($this->once())
            ->method('install')
            ->willReturnCallback(static function (Manifest $manifest): void {
                static::assertSame('https://example.com', $manifest->getPath());
                static::assertSame([
                    'version' => '6.6.0.0',
                    'hash' => 'a1bcd',
                    'revision' => '6.6.0.0-a1bcd',
                    'zip-url' => 'https://example.com/service/lifecycle/app-zip/6.6.0.0',
                    'hash-algorithm' => 'sha256',
                    'min-shop-supported-version' => '6.6.0.0',
                    'requirements' => ['service_consent'],
                ], $manifest->getSourceConfig());
                static::assertTrue($manifest->getMetadata()->isSelfManaged());
                static::assertSame('6.6.0.0-a1bcd', $manifest->getMetadata()->getVersion());
            });

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                static::callback(static function ($event) {
                    return $event instanceof ServiceInstalledEvent && $event->service === 'MyCoolService';
                }),
            );

        $lifecycle = new ServiceLifecycle(
            $this->serviceRegistryClient,
            $serviceClientFactory,
            $appLifecycle,
            $this->appRepo,
            $this->logger,
            $manifestFactory,
            $sourceResolver,
            $this->appState,
            $eventDispatcher,
            $this->requirementsValidator
        );

        static::assertTrue($lifecycle->install($this->entry, Context::createDefaultContext()));
    }

    public function testInstallUpgradesAppToService(): void
    {
        $context = Context::createDefaultContext();

        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setUniqueIdentifier(Uuid::randomHex());
        $app->assign(['name' => 'MyCoolService', 'version' => '1.0.0', 'aclRoleId' => Uuid::randomHex()]);
        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([
            static function (Criteria $criteria) use ($app) {
                static::assertCount(2, $criteria->getFilters());

                $filters = $criteria->getFilters();
                static::assertInstanceOf(EqualsFilter::class, $filters[0]);
                static::assertInstanceOf(EqualsFilter::class, $filters[1]);

                static::assertSame('name', $filters[0]->getField());
                static::assertSame('MyCoolService', $filters[0]->getValue());

                static::assertSame('selfManaged', $filters[1]->getField());
                static::assertFalse($filters[1]->getValue());

                return [$app];
            },
            static function (Criteria $criteria) use ($app) { // second load during update
                $app->setSelfManaged(true);

                return [$app];
            },
        ]);

        $tempDirectoryFactory = static::createStub(TemporaryDirectoryFactory::class);
        $tempDirectoryFactory->method('path')->willReturn('/tmp/path');

        $serviceClient = $this->createMock(ServiceClient::class);
        $serviceClient->expects($this->once())->method('latestAppInfo')->willReturn($this->appInfo);
        $serviceClientFactory = $this->createMock(ServiceClientFactory::class);
        $serviceClientFactory->expects($this->once())->method('newFor')->with($this->entry)->willReturn($serviceClient);
        $serviceRegistryClient = $this->createMock(ServiceRegistryClient::class);
        $serviceRegistryClient->expects($this->once())->method('get')->with('MyCoolService')->willReturn($this->entry);

        $sourceResolver = $this->createMock(ServiceSourceResolver::class);
        $sourceResolver->expects($this->once())
            ->method('filesystemForVersion')
            ->with($this->appInfo)
            ->willReturn(new StaticFilesystem());

        $manifest = $this->createManifest();
        $manifestFactory = $this->createMock(ManifestFactory::class);
        $manifestFactory
            ->expects($this->once())
            ->method('createFromXmlFile')
            ->with('/app-root/manifest.xml')
            ->willReturn($manifest);

        $appState = $this->createMock(AppStateService::class);
        $appState->expects($this->once())
            ->method('activateApp')
            ->with($app->getId(), $context);

        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $appLifecycle->expects($this->once())
            ->method('update')
            ->willReturnCallback(static function (Manifest $manifest): void {
                static::assertSame('https://example.com', $manifest->getPath());
                static::assertSame([
                    'version' => '6.6.0.0',
                    'hash' => 'a1bcd',
                    'revision' => '6.6.0.0-a1bcd',
                    'zip-url' => 'https://example.com/service/lifecycle/app-zip/6.6.0.0',
                    'hash-algorithm' => 'sha256',
                    'min-shop-supported-version' => '6.6.0.0',
                    'requirements' => ['service_consent'],
                ], $manifest->getSourceConfig());
                static::assertTrue($manifest->getMetadata()->isSelfManaged());
                static::assertSame('6.6.0.0-a1bcd', $manifest->getMetadata()->getVersion());
            });

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                static::callback(static function ($event) {
                    return $event instanceof ServiceUpdatedEvent && $event->service === 'MyCoolService';
                }),
            );

        $lifecycle = new ServiceLifecycle(
            $serviceRegistryClient,
            $serviceClientFactory,
            $appLifecycle,
            $appRepo,
            $this->logger,
            $manifestFactory,
            $sourceResolver,
            $appState,
            $eventDispatcher,
            $this->requirementsValidator
        );

        static::assertTrue($lifecycle->install($this->entry, $context));
        static::assertSame(
            [
                [
                    [
                        'id' => $app->getId(),
                        'selfManaged' => true,
                    ],
                ],
            ],
            $appRepo->updates
        );
    }

    public function testInstallDoesNotActivateIfRegistryEntrySpecifiesNotTo(): void
    {
        $entry = new ServiceEntry('MyCoolService', 'MyCoolService', 'https://example.com', '/service/lifecycle/choose-app', activateOnInstall: false);

        $tempDirectoryFactory = static::createStub(TemporaryDirectoryFactory::class);
        $tempDirectoryFactory->method('path')->willReturn('/tmp/path');

        $serviceClient = $this->createMock(ServiceClient::class);
        $serviceClient->expects($this->once())->method('latestAppInfo')->willReturn($this->appInfo);
        $serviceClientFactory = $this->createMock(ServiceClientFactory::class);
        $serviceClientFactory->expects($this->once())->method('newFor')->with($entry)->willReturn($serviceClient);

        $sourceResolver = $this->createMock(ServiceSourceResolver::class);
        $sourceResolver->expects($this->once())
            ->method('filesystemForVersion')
            ->with($this->appInfo)
            ->willReturn(new StaticFilesystem());

        $manifest = $this->createManifest();
        $manifestFactory = $this->createMock(ManifestFactory::class);
        $manifestFactory
            ->expects($this->once())
            ->method('createFromXmlFile')
            ->with('/app-root/manifest.xml')
            ->willReturn($manifest);

        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $appLifecycle->expects($this->once())
            ->method('install')
            ->willReturnCallback(static function (Manifest $manifest, AppInstallParameters $options): void {
                static::assertFalse($options->activate);
                static::assertSame('https://example.com', $manifest->getPath());
                static::assertSame([
                    'version' => '6.6.0.0',
                    'hash' => 'a1bcd',
                    'revision' => '6.6.0.0-a1bcd',
                    'zip-url' => 'https://example.com/service/lifecycle/app-zip/6.6.0.0',
                    'hash-algorithm' => 'sha256',
                    'min-shop-supported-version' => '6.6.0.0',
                    'requirements' => ['service_consent'],
                ], $manifest->getSourceConfig());
                static::assertTrue($manifest->getMetadata()->isSelfManaged());
                static::assertSame('6.6.0.0-a1bcd', $manifest->getMetadata()->getVersion());
            });

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                static::callback(static function ($event) {
                    return $event instanceof ServiceInstalledEvent && $event->service === 'MyCoolService';
                }),
            );

        $lifecycle = new ServiceLifecycle(
            $this->serviceRegistryClient,
            $serviceClientFactory,
            $appLifecycle,
            $this->buildAppRepository(),
            $this->logger,
            $manifestFactory,
            $sourceResolver,
            $this->appState,
            $eventDispatcher,
            $this->requirementsValidator
        );

        static::assertTrue($lifecycle->install($entry, Context::createDefaultContext()));
    }

    public function testUpdateThrowsExceptionWhenAppDoesNotExist(): void
    {
        static::expectExceptionObject(ServiceException::notFound('name', 'MyCoolService'));

        $serviceRegistryClient = $this->createMock(ServiceRegistryClient::class);
        $serviceClientFactory = static::createStub(ServiceClientFactory::class);
        $appLifecycle = static::createStub(AbstractAppLifecycle::class);
        $logger = static::createStub(LoggerInterface::class);
        $manifestFactory = static::createStub(ManifestFactory::class);

        $serviceRegistryClient->expects($this->once())->method('get')->with('MyCoolService')->willReturn($this->entry);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())->method('dispatch');

        $lifecycle = new ServiceLifecycle(
            $serviceRegistryClient,
            $serviceClientFactory,
            $appLifecycle,
            $this->buildAppRepository(),
            $logger,
            $manifestFactory,
            $this->sourceResolver,
            $this->appState,
            $eventDispatcher,
            $this->requirementsValidator
        );

        static::assertFalse($lifecycle->update('MyCoolService', Context::createDefaultContext()));
    }

    public function testUpdateLogsErrorIfAppCannotBeDownloaded(): void
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setUniqueIdentifier(Uuid::randomHex());
        $app->assign(['name' => 'MyCoolService']);

        $serviceClient = $this->createMock(ServiceClient::class);
        $serviceClient->expects($this->once())->method('latestAppInfo')->willThrowException(ServiceException::missingAppVersionInformation('app-version'));
        $serviceClientFactory = $this->createMock(ServiceClientFactory::class);
        $serviceClientFactory->expects($this->once())->method('newFor')->with($this->entry)->willReturn($serviceClient);

        $manifestFactory = $this->createMock(ManifestFactory::class);
        $manifestFactory->expects($this->never())->method('createFromXmlFile');

        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $appLifecycle->expects($this->never())->method('update');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('debug')
            ->with('Cannot update service "MyCoolService" because of error: "Error downloading app. The version information was missing: app-version"');

        $serviceRegistryClient = $this->createMock(ServiceRegistryClient::class);
        $serviceRegistryClient->expects($this->once())->method('get')->with('MyCoolService')->willReturn($this->entry);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())->method('dispatch');

        $lifecycle = new ServiceLifecycle(
            $serviceRegistryClient,
            $serviceClientFactory,
            $appLifecycle,
            $this->buildAppRepository([$app]),
            $logger,
            $manifestFactory,
            $this->sourceResolver,
            $this->appState,
            $eventDispatcher,
            $this->requirementsValidator
        );

        static::assertFalse($lifecycle->update('MyCoolService', Context::createDefaultContext()));
    }

    public function testUpdateDoesNotPerformUpdateIfNoNewVersionIsAvailable(): void
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setUniqueIdentifier(Uuid::randomHex());
        $app->assign(['name' => 'MyCoolService', 'version' => '6.6.0.0-a1bcd', 'aclRoleId' => Uuid::randomHex()]);

        $serviceClient = $this->createMock(ServiceClient::class);
        $serviceClient->expects($this->once())->method('latestAppInfo')->willReturn($this->appInfo);
        $serviceClientFactory = $this->createMock(ServiceClientFactory::class);
        $serviceClientFactory->expects($this->once())->method('newFor')->with($this->entry)->willReturn($serviceClient);
        $manifestFactory = $this->createMock(ManifestFactory::class);
        $manifestFactory->expects($this->never())->method('createFromXmlFile');
        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $appLifecycle->expects($this->never())->method('update');
        $serviceRegistryClient = $this->createMock(ServiceRegistryClient::class);
        $serviceRegistryClient->expects($this->once())->method('get')->with('MyCoolService')->willReturn($this->entry);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())->method('dispatch');

        $lifecycle = new ServiceLifecycle(
            $serviceRegistryClient,
            $serviceClientFactory,
            $appLifecycle,
            $this->buildAppRepository([$app]),
            $this->logger,
            $manifestFactory,
            $this->sourceResolver,
            $this->appState,
            $eventDispatcher,
            $this->requirementsValidator
        );

        static::assertTrue($lifecycle->update('MyCoolService', Context::createDefaultContext()));
    }

    public function testUpdateLogsErrorIfAppCannotBeUpdated(): void
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setUniqueIdentifier(Uuid::randomHex());
        $app->assign(['name' => 'MyCoolService', 'version' => '8.0.0', 'aclRoleId' => Uuid::randomHex()]);

        $serviceClient = $this->createMock(ServiceClient::class);
        $serviceClient->expects($this->once())->method('latestAppInfo')->willReturn($this->appInfo);
        $serviceClientFactory = $this->createMock(ServiceClientFactory::class);
        $serviceClientFactory->expects($this->once())->method('newFor')->with($this->entry)->willReturn($serviceClient);

        $sourceResolver = $this->createMock(ServiceSourceResolver::class);
        $sourceResolver->expects($this->once())
            ->method('filesystemForVersion')
            ->with($this->appInfo)
            ->willReturn(new StaticFilesystem());

        $manifest = $this->createManifest();
        $manifestFactory = $this->createMock(ManifestFactory::class);
        $manifestFactory
            ->expects($this->once())
            ->method('createFromXmlFile')
            ->with('/app-root/manifest.xml')
            ->willReturn($manifest);

        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $appLifecycle->expects($this->once())
            ->method('update')
            ->willThrowException(AppException::notCompatible('MyCoolService'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('debug')
            ->with('Cannot update service "MyCoolService" because of error: "App MyCoolService is not compatible with this Shopware version"');

        $serviceRegistryClient = $this->createMock(ServiceRegistryClient::class);
        $serviceRegistryClient->expects($this->once())->method('get')->with('MyCoolService')->willReturn($this->entry);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())->method('dispatch');

        $lifecycle = new ServiceLifecycle(
            $serviceRegistryClient,
            $serviceClientFactory,
            $appLifecycle,
            $this->buildAppRepository([$app]),
            $logger,
            $manifestFactory,
            $sourceResolver,
            $this->appState,
            $eventDispatcher,
            $this->requirementsValidator
        );

        static::assertFalse($lifecycle->update('MyCoolService', Context::createDefaultContext()));
    }

    public function testUpdate(): void
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setUniqueIdentifier(Uuid::randomHex());
        $app->assign(['name' => 'MyCoolService', 'version' => '6.0.0', 'aclRoleId' => Uuid::randomHex()]);

        $serviceClient = $this->createMock(ServiceClient::class);
        $serviceClient->expects($this->once())->method('latestAppInfo')->willReturn($this->appInfo);
        $serviceClientFactory = $this->createMock(ServiceClientFactory::class);
        $serviceClientFactory->expects($this->once())->method('newFor')->with($this->entry)->willReturn($serviceClient);

        $sourceResolver = $this->createMock(ServiceSourceResolver::class);
        $sourceResolver->expects($this->once())
            ->method('filesystemForVersion')
            ->with($this->appInfo)
            ->willReturn(new StaticFilesystem());

        $manifest = $this->createManifest();
        $manifestFactory = $this->createMock(ManifestFactory::class);
        $manifestFactory
            ->expects($this->once())
            ->method('createFromXmlFile')
            ->with('/app-root/manifest.xml')
            ->willReturn($this->createManifest());

        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $appLifecycle->expects($this->once())
            ->method('update')
            ->willReturnCallback(static function (Manifest $manifest): void {
                static::assertSame('https://example.com', $manifest->getPath());
                static::assertSame([
                    'version' => '6.6.0.0',
                    'hash' => 'a1bcd',
                    'revision' => '6.6.0.0-a1bcd',
                    'zip-url' => 'https://example.com/service/lifecycle/app-zip/6.6.0.0',
                    'hash-algorithm' => 'sha256',
                    'min-shop-supported-version' => '6.6.0.0',
                    'requirements' => ['service_consent'],
                ], $manifest->getSourceConfig());
                static::assertTrue($manifest->getMetadata()->isSelfManaged());
                static::assertSame('6.6.0.0-a1bcd', $manifest->getMetadata()->getVersion());
            });

        $serviceRegistryClient = $this->createMock(ServiceRegistryClient::class);
        $serviceRegistryClient->expects($this->once())->method('get')->with('MyCoolService')->willReturn($this->entry);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                static::callback(static function ($event) {
                    return $event instanceof ServiceUpdatedEvent && $event->service === 'MyCoolService';
                }),
            );

        $lifecycle = new ServiceLifecycle(
            $serviceRegistryClient,
            $serviceClientFactory,
            $appLifecycle,
            $this->buildAppRepository([$app]),
            $this->logger,
            $manifestFactory,
            $sourceResolver,
            $this->appState,
            $eventDispatcher,
            $this->requirementsValidator
        );

        static::assertTrue($lifecycle->update('MyCoolService', Context::createDefaultContext()));
    }

    public function testInstallReturnsFalseWhenRequirementsAreInvalid(): void
    {
        $invalidAppInfo = new AppInfo('MyCoolService', '6.6.0.0', 'a1bcd', '6.6.0.0-a1bcd', 'https://example.com/service/lifecycle/app-zip/6.6.0.0', ['invalid_requirement'], 'sha256', '6.6.0.0');

        $serviceClient = $this->createMock(ServiceClient::class);
        $serviceClient->expects($this->once())->method('latestAppInfo')->willReturn($invalidAppInfo);
        $serviceClientFactory = $this->createMock(ServiceClientFactory::class);
        $serviceClientFactory->expects($this->once())->method('newFor')->with($this->entry)->willReturn($serviceClient);

        $requirementsValidator = $this->createMock(RequirementsValidator::class);
        $requirementsValidator
            ->expects($this->once())
            ->method('isValidSet')
            ->with(['invalid_requirement'])
            ->willReturn(false);

        $sourceResolver = $this->createMock(ServiceSourceResolver::class);
        $sourceResolver->expects($this->never())->method('filesystemForVersion');
        $manifestFactory = $this->createMock(ManifestFactory::class);
        $manifestFactory->expects($this->never())->method('createFromXmlFile');
        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $appLifecycle->expects($this->never())->method('install');
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())->method('dispatch');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('debug')
            ->with('Cannot install service "MyCoolService" because of invalid requirements: "invalid_requirement"');

        $lifecycle = new ServiceLifecycle(
            $this->serviceRegistryClient,
            $serviceClientFactory,
            $appLifecycle,
            $this->buildAppRepository(),
            $logger,
            $manifestFactory,
            $sourceResolver,
            $this->appState,
            $eventDispatcher,
            $requirementsValidator
        );

        static::assertFalse($lifecycle->install($this->entry, Context::createDefaultContext()));
    }

    public function testUpdateReturnsFalseWhenRequirementsAreInvalid(): void
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setUniqueIdentifier(Uuid::randomHex());
        $app->assign(['name' => 'MyCoolService', 'version' => '6.0.0', 'aclRoleId' => Uuid::randomHex()]);

        $invalidAppInfo = new AppInfo('MyCoolService', '6.6.0.0', 'a1bcd', '6.6.0.0-a1bcd', 'https://example.com/service/lifecycle/app-zip/6.6.0.0', ['invalid_requirement'], 'sha256', '6.6.0.0');

        $serviceClient = $this->createMock(ServiceClient::class);
        $serviceClient->expects($this->once())->method('latestAppInfo')->willReturn($invalidAppInfo);
        $serviceClientFactory = $this->createMock(ServiceClientFactory::class);
        $serviceClientFactory->expects($this->once())->method('newFor')->with($this->entry)->willReturn($serviceClient);
        $serviceRegistryClient = $this->createMock(ServiceRegistryClient::class);
        $serviceRegistryClient->expects($this->once())->method('get')->with('MyCoolService')->willReturn($this->entry);

        $requirementsValidator = $this->createMock(RequirementsValidator::class);
        $requirementsValidator
            ->expects($this->once())
            ->method('isValidSet')
            ->with(['invalid_requirement'])
            ->willReturn(false);

        $sourceResolver = $this->createMock(ServiceSourceResolver::class);
        $sourceResolver->expects($this->never())->method('filesystemForVersion');
        $manifestFactory = $this->createMock(ManifestFactory::class);
        $manifestFactory->expects($this->never())->method('createFromXmlFile');
        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $appLifecycle->expects($this->never())->method('update');
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())->method('dispatch');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('debug')
            ->with('Cannot update service "MyCoolService" because of invalid requirements: "invalid_requirement"');

        $lifecycle = new ServiceLifecycle(
            $serviceRegistryClient,
            $serviceClientFactory,
            $appLifecycle,
            $this->buildAppRepository([$app]),
            $logger,
            $manifestFactory,
            $sourceResolver,
            $this->appState,
            $eventDispatcher,
            $requirementsValidator
        );

        static::assertFalse($lifecycle->update('MyCoolService', Context::createDefaultContext()));
    }

    /**
     * @param array<AppEntity> $apps
     *
     * @return StaticEntityRepository<AppCollection>
     */
    private function buildAppRepository(array $apps = []): StaticEntityRepository
    {
        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([
            new AppCollection($apps),
        ]);

        return $appRepository;
    }

    private function createManifest(): Manifest
    {
        return Manifest::createFromXml(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/platform/trunk/src/Core/Framework/App/Manifest/Schema/manifest-3.0.xsd">
    <meta>
        <name>MyCoolService</name>
        <label>My Cool Service</label>
        <description>My Cool Service</description>
        <author>Shopware</author>
        <copyright>(c) by Your Company Ltd.</copyright>
        <license>proprietary</license>
        <version>6.6.6.0</version>
    </meta>
</manifest>
XML);
    }
}
