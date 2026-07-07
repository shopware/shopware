<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\AppManager;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppUpdateParameters;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\ManifestFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Service\AppInfo;
use Shopware\Core\Service\Event\ServiceInstalledEvent;
use Shopware\Core\Service\Event\ServiceUpdatedEvent;
use Shopware\Core\Service\Requirement\RequirementsValidator;
use Shopware\Core\Service\ServiceClient;
use Shopware\Core\Service\ServiceClientFactory;
use Shopware\Core\Service\ServiceException;
use Shopware\Core\Service\ServiceLifecycle;
use Shopware\Core\Service\ServiceRegistry\Client;
use Shopware\Core\Service\ServiceRegistry\ServiceEntry;
use Shopware\Core\Service\ServiceSourceResolver;
use Shopware\Core\Service\ServiceStorage;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(ServiceLifecycle::class)]
class ServiceLifecycleTest extends TestCase
{
    private AppManager&MockObject $appManager;

    private ServiceEntry $entry;

    private LoggerInterface&MockObject $logger;

    private ManifestFactory&MockObject $manifestFactory;

    private ServiceSourceResolver&MockObject $sourceResolver;

    private AppInfo $appInfo;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private RequirementsValidator&MockObject $requirementsValidator;

    private Client&MockObject $registryClient;

    private ServiceClient&MockObject $serviceClient;

    private ServiceClientFactory&MockObject $serviceClientFactory;

    protected function setUp(): void
    {
        $this->appManager = $this->createMock(AppManager::class);
        $this->entry = new ServiceEntry('MyCoolService', 'MyCoolService', 'https://example.com', '/service/lifecycle/choose-app');
        $this->appInfo = new AppInfo('MyCoolService', '6.6.0.0', 'a1bcd', '6.6.0.0-a1bcd', 'https://example.com/service/lifecycle/app-zip/6.6.0.0', ['service_consent'], 'sha256', '6.6.0.0');
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->manifestFactory = $this->createMock(ManifestFactory::class);
        $this->sourceResolver = $this->createMock(ServiceSourceResolver::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->requirementsValidator = $this->createMock(RequirementsValidator::class);
        $this->registryClient = $this->createMock(Client::class);
        $this->serviceClient = $this->createMock(ServiceClient::class);
        $this->serviceClientFactory = $this->createMock(ServiceClientFactory::class);
    }

    public function testInstallInstallsWhenInstallationRequirementsAreMet(): void
    {
        $this->fetchReturnsAppInfo();
        $this->requirementsMet(true);

        $this->sourceResolver->expects($this->once())
            ->method('filesystemForVersion')
            ->with($this->appInfo)
            ->willReturn(new StaticFilesystem());

        $this->manifestFactory
            ->expects($this->once())
            ->method('createFromXmlFile')
            ->with('/app-root/manifest.xml')
            ->willReturn($this->createManifest());

        $this->appManager->expects($this->once())
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

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(static fn ($event) => $event instanceof ServiceInstalledEvent && $event->service === 'MyCoolService'));

        static::assertTrue($this->createLifecycle($this->buildAppRepository())->install($this->entry, Context::createDefaultContext()));
    }

    public function testInstallSkipsWhenInstallationRequirementsAreNotMet(): void
    {
        $this->fetchReturnsAppInfo();
        $this->requirementsMet(false);

        $this->appManager->expects($this->never())->method('install');
        $this->eventDispatcher->expects($this->never())->method('dispatch');

        static::assertFalse($this->createLifecycle($this->buildAppRepository())->install($this->entry, Context::createDefaultContext()));
    }

    public function testInstallReturnsFalseWhenAppInfoCannotBeFetched(): void
    {
        $client = static::createStub(ServiceClient::class);
        $client->method('latestAppInfo')->willThrowException(ServiceException::missingAppVersionInformation('app-version'));
        $factory = $this->createMock(ServiceClientFactory::class);
        $factory->method('newFor')->willReturn($client);

        $this->requirementsValidator->expects($this->never())->method('isSatisfied');
        $this->appManager->expects($this->never())->method('install');

        static::assertFalse($this->createLifecycle($this->buildAppRepository(), $factory)->install($this->entry, Context::createDefaultContext()));
    }

    public function testInstallLogsErrorIfAppCannotBeInstalled(): void
    {
        $this->fetchReturnsAppInfo();
        $this->requirementsMet(true);

        $this->sourceResolver->expects($this->once())
            ->method('filesystemForVersion')
            ->with($this->appInfo)
            ->willReturn(new StaticFilesystem());

        $this->manifestFactory
            ->expects($this->once())
            ->method('createFromXmlFile')
            ->with('/app-root/manifest.xml')
            ->willReturn($this->createManifest());

        $this->appManager->expects($this->once())
            ->method('install')
            ->willThrowException(AppException::notCompatible('MyCoolService'));

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('Cannot install service "MyCoolService" because of error: "App MyCoolService is not compatible with this Shopware version"');

        $this->eventDispatcher->expects($this->never())->method('dispatch');

        static::assertFalse($this->createLifecycle($this->buildAppRepository())->install($this->entry, Context::createDefaultContext()));
    }

    public function testInstallUpgradesAppToService(): void
    {
        $context = Context::createDefaultContext();
        $this->fetchReturnsAppInfo();
        $this->requirementsMet(true);

        $app = AppFixture::createAppEntity(name: 'MyCoolService');
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
            static function (Criteria $criteria) use ($app) { // load service for activation
                $app->setSelfManaged(true);

                return [$app];
            },
            static function (Criteria $criteria) use ($app) { // load service during update
                $app->setSelfManaged(true);

                return [$app];
            },
        ]);

        $this->sourceResolver->expects($this->once())
            ->method('filesystemForVersion')
            ->with($this->appInfo)
            ->willReturn(new StaticFilesystem());

        $this->manifestFactory
            ->expects($this->once())
            ->method('createFromXmlFile')
            ->with('/app-root/manifest.xml')
            ->willReturn($this->createManifest());

        $this->appManager->expects($this->once())->method('activate')->with($app, $context);

        $this->appManager->expects($this->once())
            ->method('update')
            ->willReturnCallback(static function (Manifest $manifest, AppUpdateParameters $parameters, AppEntity $service) use ($app): void {
                static::assertSame($app, $service);
                static::assertSame('https://example.com', $manifest->getPath());
                static::assertSame('6.6.0.0-a1bcd', $manifest->getMetadata()->getVersion());
            });

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(static fn ($event) => $event instanceof ServiceUpdatedEvent && $event->service === 'MyCoolService'));

        static::assertTrue($this->createLifecycle($appRepo)->install($this->entry, $context));
        static::assertSame([[['id' => $app->getId(), 'selfManaged' => true]]], $appRepo->updates);
    }

    public function testInstallDoesNotActivateIfRegistryEntrySpecifiesNotTo(): void
    {
        $entry = new ServiceEntry('MyCoolService', 'MyCoolService', 'https://example.com', '/service/lifecycle/choose-app', activateOnInstall: false);
        $this->fetchReturnsAppInfo();
        $this->requirementsMet(true);

        $this->sourceResolver->expects($this->once())
            ->method('filesystemForVersion')
            ->with($this->appInfo)
            ->willReturn(new StaticFilesystem());

        $this->manifestFactory
            ->expects($this->once())
            ->method('createFromXmlFile')
            ->with('/app-root/manifest.xml')
            ->willReturn($this->createManifest());

        $this->appManager->expects($this->once())
            ->method('install')
            ->willReturnCallback(static function (Manifest $manifest, AppInstallParameters $options): void {
                static::assertFalse($options->activate);
            });

        static::assertTrue($this->createLifecycle($this->buildAppRepository())->install($entry, Context::createDefaultContext()));
    }

    public function testUpdateUpdatesWhenInstallationRequirementsAreMet(): void
    {
        $app = AppFixture::createAppEntity(name: 'MyCoolService')->assign(['version' => '6.0.0']);
        $this->registryReturnsEntry();
        $this->fetchReturnsAppInfo();
        $this->requirementsMet(true);

        $this->sourceResolver->expects($this->once())
            ->method('filesystemForVersion')
            ->with($this->appInfo)
            ->willReturn(new StaticFilesystem());

        $this->manifestFactory
            ->expects($this->once())
            ->method('createFromXmlFile')
            ->with('/app-root/manifest.xml')
            ->willReturn($this->createManifest());

        $this->appManager->expects($this->once())
            ->method('update')
            ->willReturnCallback(static function (Manifest $manifest, AppUpdateParameters $parameters, AppEntity $service) use ($app): void {
                static::assertSame($app, $service);
                static::assertSame('6.6.0.0-a1bcd', $manifest->getMetadata()->getVersion());
            });

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(static fn ($event) => $event instanceof ServiceUpdatedEvent && $event->service === 'MyCoolService'));

        $this->createLifecycle($this->buildAppRepository([$app]))->update('MyCoolService', Context::createDefaultContext());
    }

    public function testUpdateUninstallsWhenInstallationRequirementsAreNotMet(): void
    {
        $app = AppFixture::createAppEntity(name: 'MyCoolService');
        $context = Context::createDefaultContext();
        $this->registryReturnsEntry();
        $this->fetchReturnsAppInfo();
        $this->requirementsMet(false);

        $this->appManager->expects($this->never())->method('update');
        $this->appManager->expects($this->once())->method('uninstall')->with($app, $context);

        $this->createLifecycle($this->buildAppRepository([$app]))->update('MyCoolService', $context);
    }

    public function testUpdateDoesNothingWhenTheServiceCannotBeFetched(): void
    {
        $this->registryClient->method('get')->willThrowException(ServiceException::notFound('name', 'MyCoolService'));

        $this->requirementsValidator->expects($this->never())->method('isSatisfied');
        $this->appManager->expects($this->never())->method('update');
        $this->appManager->expects($this->never())->method('uninstall');

        $this->createLifecycle($this->buildAppRepository())->update('MyCoolService', Context::createDefaultContext());
    }

    public function testUpdateDoesNotPerformUpdateIfNoNewVersionIsAvailable(): void
    {
        $app = AppFixture::createAppEntity(name: 'MyCoolService')->assign(['version' => '6.6.0.0-a1bcd']);
        $this->registryReturnsEntry();
        $this->fetchReturnsAppInfo();
        $this->requirementsMet(true);

        $this->manifestFactory->expects($this->never())->method('createFromXmlFile');
        $this->appManager->expects($this->never())->method('update');
        $this->eventDispatcher->expects($this->never())->method('dispatch');

        $this->createLifecycle($this->buildAppRepository([$app]))->update('MyCoolService', Context::createDefaultContext());
    }

    public function testUpdateLogsErrorIfAppCannotBeDownloaded(): void
    {
        $app = AppFixture::createAppEntity(name: 'MyCoolService')->assign(['version' => '6.0.0']);
        $this->registryReturnsEntry();
        $this->fetchReturnsAppInfo();
        $this->requirementsMet(true);

        $this->sourceResolver->expects($this->once())
            ->method('filesystemForVersion')
            ->with($this->appInfo)
            ->willThrowException(AppException::notCompatible('MyCoolService'));

        $this->manifestFactory->expects($this->never())->method('createFromXmlFile');
        $this->appManager->expects($this->never())->method('update');

        $this->logger
            ->expects($this->once())
            ->method('debug')
            ->with('Cannot update service "MyCoolService" because of error: "App MyCoolService is not compatible with this Shopware version"');

        $this->createLifecycle($this->buildAppRepository([$app]))->update('MyCoolService', Context::createDefaultContext());
    }

    public function testUpdateLogsErrorIfAppCannotBeUpdated(): void
    {
        $app = AppFixture::createAppEntity(name: 'MyCoolService')->assign(['version' => '8.0.0']);
        $this->registryReturnsEntry();
        $this->fetchReturnsAppInfo();
        $this->requirementsMet(true);

        $this->sourceResolver->expects($this->once())
            ->method('filesystemForVersion')
            ->with($this->appInfo)
            ->willReturn(new StaticFilesystem());

        $this->manifestFactory
            ->expects($this->once())
            ->method('createFromXmlFile')
            ->with('/app-root/manifest.xml')
            ->willReturn($this->createManifest());

        $this->appManager->expects($this->once())
            ->method('update')
            ->willThrowException(AppException::notCompatible('MyCoolService'));

        $this->logger
            ->expects($this->once())
            ->method('debug')
            ->with('Cannot update service "MyCoolService" because of error: "App MyCoolService is not compatible with this Shopware version"');

        $this->createLifecycle($this->buildAppRepository([$app]))->update('MyCoolService', Context::createDefaultContext());
    }

    public function testReevaluateInstalledUninstallsServicesWhoseInstallationRequirementsAreNoLongerMet(): void
    {
        $app = AppFixture::createAppEntity(name: 'MyCoolService');
        $context = Context::createDefaultContext();
        $this->requirementsMet(false);

        $this->appManager->expects($this->once())->method('uninstall')->with($app, $context);

        // findAll() walks the installed services, then uninstall() looks the service up again by name
        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([new AppCollection([$app]), new AppCollection([$app])]);

        $this->createLifecycle($appRepo)->reevaluateInstalled($context);
    }

    public function testReevaluateInstalledLeavesServicesWhoseInstallationRequirementsAreStillMet(): void
    {
        $app = AppFixture::createAppEntity(name: 'MyCoolService');
        $this->requirementsMet(true);

        $this->appManager->expects($this->never())->method('uninstall');

        $this->createLifecycle($this->buildAppRepository([$app]))->reevaluateInstalled(Context::createDefaultContext());
    }

    public function testActivate(): void
    {
        $context = Context::createDefaultContext();
        $app = AppFixture::createAppEntity(name: 'MyCoolService');

        $this->appManager->expects($this->once())->method('activate')->with($app, $context);

        $this->createLifecycle($this->buildAppRepository([$app]))->activate('MyCoolService', $context);
    }

    public function testActivateThrowsExceptionWhenServiceDoesNotExist(): void
    {
        static::expectExceptionObject(ServiceException::notFound('name', 'MyCoolService'));

        $this->appManager->expects($this->never())->method('activate');

        $this->createLifecycle($this->buildAppRepository())->activate('MyCoolService', Context::createDefaultContext());
    }

    public function testDeactivate(): void
    {
        $context = Context::createDefaultContext();
        $app = AppFixture::createAppEntity(name: 'MyCoolService');

        $this->appManager->expects($this->once())->method('deactivate')->with($app, $context);

        $this->createLifecycle($this->buildAppRepository([$app]))->deactivate('MyCoolService', $context);
    }

    public function testDeactivateThrowsExceptionWhenServiceDoesNotExist(): void
    {
        static::expectExceptionObject(ServiceException::notFound('name', 'MyCoolService'));

        $this->appManager->expects($this->never())->method('deactivate');

        $this->createLifecycle($this->buildAppRepository())->deactivate('MyCoolService', Context::createDefaultContext());
    }

    public function testUninstall(): void
    {
        $context = Context::createDefaultContext();
        $app = AppFixture::createAppEntity(name: 'MyCoolService');

        $this->appManager->expects($this->once())
            ->method('uninstall')
            ->with($app, $context, true);

        $this->createLifecycle($this->buildAppRepository([$app]))->uninstall('MyCoolService', $context);
    }

    public function testUninstallThrowsExceptionWhenServiceDoesNotExist(): void
    {
        static::expectExceptionObject(ServiceException::notFound('name', 'MyCoolService'));

        $this->appManager->expects($this->never())->method('uninstall');

        $this->createLifecycle($this->buildAppRepository())->uninstall('MyCoolService', Context::createDefaultContext());
    }

    private function fetchReturnsAppInfo(): void
    {
        $this->serviceClient->method('latestAppInfo')->willReturn($this->appInfo);
        $this->serviceClientFactory->method('newFor')->willReturn($this->serviceClient);
    }

    private function registryReturnsEntry(): void
    {
        $this->registryClient->method('get')->willReturn($this->entry);
    }

    private function requirementsMet(bool $met): void
    {
        $this->requirementsValidator->method('isSatisfied')->willReturn($met);
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

    /**
     * @param StaticEntityRepository<AppCollection> $appRepository
     */
    private function createLifecycle(StaticEntityRepository $appRepository, ?ServiceClientFactory $serviceClientFactory = null): ServiceLifecycle
    {
        return new ServiceLifecycle(
            $this->appManager,
            $appRepository,
            new ServiceStorage($appRepository),
            $this->logger,
            $this->manifestFactory,
            $this->sourceResolver,
            $this->eventDispatcher,
            $this->requirementsValidator,
            $this->registryClient,
            $serviceClientFactory ?? $this->serviceClientFactory,
        );
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
