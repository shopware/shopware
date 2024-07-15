<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\ManifestFactory;
use Shopware\Core\Framework\App\Source\AbstractTemporaryDirectoryFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Services\AppInfo;
use Shopware\Core\Services\ServiceClient;
use Shopware\Core\Services\ServiceClientFactory;
use Shopware\Core\Services\ServiceLifecycle;
use Shopware\Core\Services\ServiceRegistryClient;
use Shopware\Core\Services\ServiceRegistryEntry;
use Shopware\Core\Services\ServicesException;
use Shopware\Core\Services\ServiceSourceResolver;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;

/**
 * @internal
 */
#[CoversClass(ServiceLifecycle::class)]
class ServiceLifecycleTest extends TestCase
{
    private AbstractAppLifecycle&MockObject $appLifecycle;

    private ServiceRegistryEntry $entry;

    private LoggerInterface&MockObject $logger;

    private ManifestFactory&MockObject $manifestFactory;

    private ServiceClient&MockObject $serviceClient;

    private ServiceClientFactory&MockObject $serviceClientFactory;

    private ServiceRegistryClient&MockObject $serviceRegistryClient;

    private ServiceSourceResolver&MockObject $sourceResolver;

    private AppInfo $appInfo;

    protected function setUp(): void
    {
        $this->appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $this->entry = new ServiceRegistryEntry('MyCoolService', 'MyCoolService', 'https://mycoolservice.com', '/service/lifecycle/choose-app');
        $this->appInfo = new AppInfo('MyCoolService', '6.6.0.0', 'a1bcd', '6.6.0.0-a1bcd', 'https://mycoolservice.com/service/lifecycle/app-zip/6.6.0.0');
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->manifestFactory = $this->createMock(ManifestFactory::class);
        $this->serviceClient = $this->createMock(ServiceClient::class);
        $this->serviceClientFactory = $this->createMock(ServiceClientFactory::class);
        $this->serviceRegistryClient = $this->createMock(ServiceRegistryClient::class);
        $this->sourceResolver = $this->createMock(ServiceSourceResolver::class);
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

    public function testInstallLogsErrorIfAppCannotBeDownloaded(): void
    {
        $this->serviceClient->expects(static::once())->method('latestAppInfo')->willThrowException(ServicesException::missingAppVersionInfo());
        $this->serviceClientFactory->expects(static::once())->method('newFor')->with($this->entry)->willReturn($this->serviceClient);

        $this->manifestFactory->expects(static::never())->method('createFromXmlFile');

        $this->appLifecycle->expects(static::never())->method('install');

        $this->logger
            ->expects(static::once())
            ->method('error')
            ->with('Cannot install service "MyCoolService" because of error: "Error downloading app. The version information was missing."');

        $lifecycle = new ServiceLifecycle(
            $this->serviceRegistryClient,
            $this->serviceClientFactory,
            $this->appLifecycle,
            $this->buildAppRepository(),
            $this->logger,
            $this->manifestFactory,
            $this->sourceResolver
        );

        $lifecycle->install($this->entry);
    }

    public function testInstallLogsErrorIfAppCannotBeInstalled(): void
    {
        $tempDirectoryFactory = $this->createMock(AbstractTemporaryDirectoryFactory::class);
        $tempDirectoryFactory->method('path')->willReturn('/tmp/path');

        $this->serviceClient->expects(static::once())->method('latestAppInfo')->willReturn($this->appInfo);
        $this->serviceClientFactory->expects(static::once())->method('newFor')->with($this->entry)->willReturn($this->serviceClient);

        $this->sourceResolver->expects(static::once())
            ->method('filesystemForVersion')
            ->with($this->appInfo)
            ->willReturn(new StaticFilesystem());

        $manifest = $this->createManifest();
        $manifestFactory = $this->createMock(ManifestFactory::class);
        $manifestFactory
            ->expects(static::once())
            ->method('createFromXmlFile')
            ->with('/app-root/manifest.xml')
            ->willReturn($manifest);

        $this->appLifecycle->expects(static::once())
            ->method('install')
            ->willThrowException(AppException::notCompatible('MyCoolService'));

        $this->logger
            ->expects(static::once())
            ->method('error')
            ->with('Cannot install service "MyCoolService" because of error: "App MyCoolService is not compatible with this Shopware version"');

        $lifecycle = new ServiceLifecycle(
            $this->serviceRegistryClient,
            $this->serviceClientFactory,
            $this->appLifecycle,
            $this->buildAppRepository(),
            $this->logger,
            $manifestFactory,
            $this->sourceResolver
        );

        static::assertFalse($lifecycle->install($this->entry));
    }

    public function testInstall(): void
    {
        $tempDirectoryFactory = $this->createMock(AbstractTemporaryDirectoryFactory::class);

        $tempDirectoryFactory->method('path')->willReturn('/tmp/path');

        $this->serviceClient->expects(static::once())->method('latestAppInfo')->willReturn($this->appInfo);
        $this->serviceClientFactory->expects(static::once())->method('newFor')->with($this->entry)->willReturn($this->serviceClient);

        $this->sourceResolver->expects(static::once())
            ->method('filesystemForVersion')
            ->with($this->appInfo)
            ->willReturn(new StaticFilesystem());

        $manifest = $this->createManifest();
        $this->manifestFactory
            ->expects(static::once())
            ->method('createFromXmlFile')
            ->with('/app-root/manifest.xml')
            ->willReturn($manifest);

        $this->appLifecycle->expects(static::once())
            ->method('install')
            ->willReturnCallback(function (Manifest $manifest, bool $activate): void {
                static::assertTrue($activate);
                static::assertSame('https://mycoolservice.com', $manifest->getPath());
                static::assertSame([
                    'version' => '6.6.0.0',
                    'hash' => 'a1bcd',
                    'revision' => '6.6.0.0-a1bcd',
                    'zip-url' => 'https://mycoolservice.com/service/lifecycle/app-zip/6.6.0.0',
                ], $manifest->getSourceConfig());
                static::assertTrue($manifest->getMetadata()->isSelfManaged());
                static::assertSame('6.6.0.0-a1bcd', $manifest->getMetadata()->getVersion());
            });

        $lifecycle = new ServiceLifecycle(
            $this->serviceRegistryClient,
            $this->serviceClientFactory,
            $this->appLifecycle,
            $this->buildAppRepository(),
            $this->logger,
            $this->manifestFactory,
            $this->sourceResolver
        );

        static::assertTrue($lifecycle->install($this->entry));
    }

    public function testInstallDoesNotActivateIfRegistryEntrySpecifiesNotTo(): void
    {
        $entry = new ServiceRegistryEntry('MyCoolService', 'MyCoolService', 'https://mycoolservice.com', '/service/lifecycle/choose-app', activateOnInstall: false);

        $tempDirectoryFactory = $this->createMock(AbstractTemporaryDirectoryFactory::class);
        $tempDirectoryFactory->method('path')->willReturn('/tmp/path');

        $this->serviceClient->expects(static::once())->method('latestAppInfo')->willReturn($this->appInfo);
        $this->serviceClientFactory->expects(static::once())->method('newFor')->with($entry)->willReturn($this->serviceClient);

        $this->sourceResolver->expects(static::once())
            ->method('filesystemForVersion')
            ->with($this->appInfo)
            ->willReturn(new StaticFilesystem());

        $manifest = $this->createManifest();
        $this->manifestFactory
            ->expects(static::once())
            ->method('createFromXmlFile')
            ->with('/app-root/manifest.xml')
            ->willReturn($manifest);

        $this->appLifecycle->expects(static::once())
            ->method('install')
            ->willReturnCallback(function (Manifest $manifest, bool $activate): void {
                static::assertFalse($activate);
                static::assertSame('https://mycoolservice.com', $manifest->getPath());
                static::assertSame([
                    'version' => '6.6.0.0',
                    'hash' => 'a1bcd',
                    'revision' => '6.6.0.0-a1bcd',
                    'zip-url' => 'https://mycoolservice.com/service/lifecycle/app-zip/6.6.0.0',
                ], $manifest->getSourceConfig());
                static::assertTrue($manifest->getMetadata()->isSelfManaged());
                static::assertSame('6.6.0.0-a1bcd', $manifest->getMetadata()->getVersion());
            });

        $lifecycle = new ServiceLifecycle(
            $this->serviceRegistryClient,
            $this->serviceClientFactory,
            $this->appLifecycle,
            $this->buildAppRepository(),
            $this->logger,
            $this->manifestFactory,
            $this->sourceResolver
        );

        static::assertTrue($lifecycle->install($entry));
    }

    public function testUpdateThrowsExceptionWhenAppDoesNotExist(): void
    {
        static::expectExceptionObject(ServicesException::notFound('name', 'MyCoolService'));

        $this->serviceRegistryClient->expects(static::once())->method('get')->with('MyCoolService')->willReturn($this->entry);

        $lifecycle = new ServiceLifecycle(
            $this->serviceRegistryClient,
            $this->serviceClientFactory,
            $this->appLifecycle,
            $this->buildAppRepository(),
            $this->logger,
            $this->manifestFactory,
            $this->sourceResolver
        );

        static::assertFalse($lifecycle->update('MyCoolService', Context::createDefaultContext()));
    }

    public function testUpdateLogsErrorIfAppCannotBeDownloaded(): void
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setUniqueIdentifier(Uuid::randomHex());
        $app->assign(['name' => 'MyCoolService']);

        $this->serviceClient->expects(static::once())->method('latestAppInfo')->willThrowException(ServicesException::missingAppVersionInfo());
        $this->serviceClientFactory->expects(static::once())->method('newFor')->with($this->entry)->willReturn($this->serviceClient);

        $this->manifestFactory->expects(static::never())->method('createFromXmlFile');

        $this->appLifecycle->expects(static::never())->method('update');

        $this->logger
            ->expects(static::once())
            ->method('error')
            ->with('Cannot update service "MyCoolService" because of error: "Error downloading app. The version information was missing."');

        $this->serviceRegistryClient->expects(static::once())->method('get')->with('MyCoolService')->willReturn($this->entry);

        $lifecycle = new ServiceLifecycle(
            $this->serviceRegistryClient,
            $this->serviceClientFactory,
            $this->appLifecycle,
            $this->buildAppRepository([$app]),
            $this->logger,
            $this->manifestFactory,
            $this->sourceResolver
        );

        static::assertFalse($lifecycle->update('MyCoolService', Context::createDefaultContext()));
    }

    public function testUpdateDoesNotPerformUpdateIfNoNewVersionIsAvailable(): void
    {
        $appInfo = new AppInfo('MyCoolService', '6.0.0.0', 'a1bcd', '6.0.0.0-a1bcd', 'https://mycoolservice.com/service/lifecycle/app-zip/6.6.0.0');

        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setUniqueIdentifier(Uuid::randomHex());
        $app->assign(['name' => 'MyCoolService', 'version' => '6.0.0.0-a1bcd', 'aclRoleId' => Uuid::randomHex()]);

        $this->serviceClient->expects(static::once())->method('latestAppInfo')->willReturn($appInfo);
        $this->serviceClientFactory->expects(static::once())->method('newFor')->with($this->entry)->willReturn($this->serviceClient);

        $this->manifestFactory->expects(static::never())->method('createFromXmlFile');

        $this->appLifecycle->expects(static::never())->method('update');

        $this->serviceRegistryClient->expects(static::once())->method('get')->with('MyCoolService')->willReturn($this->entry);

        $lifecycle = new ServiceLifecycle(
            $this->serviceRegistryClient,
            $this->serviceClientFactory,
            $this->appLifecycle,
            $this->buildAppRepository([$app]),
            $this->logger,
            $this->manifestFactory,
            $this->sourceResolver
        );

        static::assertTrue($lifecycle->update('MyCoolService', Context::createDefaultContext()));
    }

    public function testUpdateLogsErrorIfAppCannotBeUpdated(): void
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setUniqueIdentifier(Uuid::randomHex());
        $app->assign(['name' => 'MyCoolService', 'version' => '8.0.0', 'aclRoleId' => Uuid::randomHex()]);

        $this->serviceClient->expects(static::once())->method('latestAppInfo')->willReturn($this->appInfo);
        $this->serviceClientFactory->expects(static::once())->method('newFor')->with($this->entry)->willReturn($this->serviceClient);

        $this->sourceResolver->expects(static::once())
            ->method('filesystemForVersion')
            ->with($this->appInfo)
            ->willReturn(new StaticFilesystem());

        $manifest = $this->createManifest();
        $this->manifestFactory
            ->expects(static::once())
            ->method('createFromXmlFile')
            ->with('/app-root/manifest.xml')
            ->willReturn($manifest);

        $this->appLifecycle->expects(static::once())
            ->method('update')
            ->willThrowException(AppException::notCompatible('MyCoolService'));

        $this->logger
            ->expects(static::once())
            ->method('error')
            ->with('Cannot update service "MyCoolService" because of error: "App MyCoolService is not compatible with this Shopware version"');

        $this->serviceRegistryClient->expects(static::once())->method('get')->with('MyCoolService')->willReturn($this->entry);

        $lifecycle = new ServiceLifecycle(
            $this->serviceRegistryClient,
            $this->serviceClientFactory,
            $this->appLifecycle,
            $this->buildAppRepository([$app]),
            $this->logger,
            $this->manifestFactory,
            $this->sourceResolver
        );

        static::assertFalse($lifecycle->update('MyCoolService', Context::createDefaultContext()));
    }

    public function testUpdate(): void
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setUniqueIdentifier(Uuid::randomHex());
        $app->assign(['name' => 'MyCoolService', 'version' => '6.0.0', 'aclRoleId' => Uuid::randomHex()]);

        $this->serviceClient->expects(static::once())->method('latestAppInfo')->willReturn($this->appInfo);
        $this->serviceClientFactory->expects(static::once())->method('newFor')->with($this->entry)->willReturn($this->serviceClient);

        $this->sourceResolver->expects(static::once())
            ->method('filesystemForVersion')
            ->with($this->appInfo)
            ->willReturn(new StaticFilesystem());

        $manifest = $this->createManifest();
        $this->manifestFactory
            ->expects(static::once())
            ->method('createFromXmlFile')
            ->with('/app-root/manifest.xml')
            ->willReturn($manifest);

        $this->appLifecycle->expects(static::once())
            ->method('update')
            ->willReturnCallback(function (Manifest $manifest): void {
                static::assertSame('https://mycoolservice.com', $manifest->getPath());
                static::assertSame([
                    'version' => '6.6.0.0',
                    'hash' => 'a1bcd',
                    'revision' => '6.6.0.0-a1bcd',
                    'zip-url' => 'https://mycoolservice.com/service/lifecycle/app-zip/6.6.0.0',
                ], $manifest->getSourceConfig());
                static::assertTrue($manifest->getMetadata()->isSelfManaged());
                static::assertSame('6.6.0.0-a1bcd', $manifest->getMetadata()->getVersion());
            });

        $this->serviceRegistryClient->expects(static::once())->method('get')->with('MyCoolService')->willReturn($this->entry);

        $lifecycle = new ServiceLifecycle(
            $this->serviceRegistryClient,
            $this->serviceClientFactory,
            $this->appLifecycle,
            $this->buildAppRepository([$app]),
            $this->logger,
            $this->manifestFactory,
            $this->sourceResolver
        );

        static::assertTrue($lifecycle->update('MyCoolService', Context::createDefaultContext()));
    }

    private function createManifest(): Manifest
    {
        return Manifest::createFromXml(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/platform/trunk/src/Core/Framework/App/Manifest/Schema/manifest-2.0.xsd">
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
