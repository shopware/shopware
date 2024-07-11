<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Services;

use PHPUnit\Framework\Attributes\CoversClass;
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
    public function testInstallLogsErrorIfAppCannotBeDownloaded(): void
    {
        $entry = new ServiceRegistryEntry('MyCoolService', 'MyCoolService', 'https://mycoolservice.com', '/service/lifecycle/choose-app');

        $serviceRegistryClient = $this->createMock(ServiceRegistryClient::class);
        $serviceClientFactory = $this->createMock(ServiceClientFactory::class);
        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([]);

        $serviceClient = $this->createMock(ServiceClient::class);
        $serviceClient->expects(static::once())->method('latestAppInfo')->willThrowException(ServicesException::missingAppVersionInfo());
        $serviceClientFactory->expects(static::once())->method('newFor')->with($entry)->willReturn($serviceClient);

        $sourceResolver = $this->createMock(ServiceSourceResolver::class);

        $manifestFactory = $this->createMock(ManifestFactory::class);
        $manifestFactory->expects(static::never())->method('createFromXmlFile');

        $appLifecycle->expects(static::never())->method('install');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(static::once())
            ->method('error')
            ->with('Cannot install service "MyCoolService" because of error: "Error downloading app. The version information was missing."');

        $lifecycle = new ServiceLifecycle(
            $serviceRegistryClient,
            $serviceClientFactory,
            $appLifecycle,
            $appRepo,
            $logger,
            $manifestFactory,
            $sourceResolver
        );

        $lifecycle->install($entry);
    }

    public function testInstallLogsErrorIfAppCannotBeInstalled(): void
    {
        $entry = new ServiceRegistryEntry('MyCoolService', 'MyCoolService', 'https://mycoolservice.com', '/service/lifecycle/choose-app');
        $appInfo = new AppInfo('MyCoolService', '6.6.0.0', 'a1bcd', '6.6.0.0-a1bcd', 'https://mycoolservice.com/service/lifecycle/app-zip/6.6.0.0');

        $serviceRegistryClient = $this->createMock(ServiceRegistryClient::class);
        $serviceClientFactory = $this->createMock(ServiceClientFactory::class);
        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([]);
        $tempDirectoryFactory = $this->createMock(AbstractTemporaryDirectoryFactory::class);
        $logger = $this->createMock(LoggerInterface::class);

        $tempDirectoryFactory->method('path')->willReturn('/tmp/path');

        $serviceClient = $this->createMock(ServiceClient::class);
        $serviceClient->expects(static::once())->method('latestAppInfo')->willReturn($appInfo);
        $serviceClientFactory->expects(static::once())->method('newFor')->with($entry)->willReturn($serviceClient);

        $sourceResolver = $this->createMock(ServiceSourceResolver::class);
        $sourceResolver->expects(static::once())
            ->method('filesystemForVersion')
            ->with($appInfo)
            ->willReturn(new StaticFilesystem());

        $manifest = $this->createManifest();
        $manifestFactory = $this->createMock(ManifestFactory::class);
        $manifestFactory
            ->expects(static::once())
            ->method('createFromXmlFile')
            ->with('/app-root/manifest.xml')
            ->willReturn($manifest);

        $appLifecycle->expects(static::once())
            ->method('install')
            ->willThrowException(AppException::notCompatible('MyCoolService'));

        $logger
            ->expects(static::once())
            ->method('error')
            ->with('Cannot install service "MyCoolService" because of error: "App MyCoolService is not compatible with this Shopware version"');

        $lifecycle = new ServiceLifecycle(
            $serviceRegistryClient,
            $serviceClientFactory,
            $appLifecycle,
            $appRepo,
            $logger,
            $manifestFactory,
            $sourceResolver
        );

        static::assertFalse($lifecycle->install($entry));
    }

    public function testInstall(): void
    {
        $entry = new ServiceRegistryEntry('MyCoolService', 'MyCoolService', 'https://mycoolservice.com', '/service/lifecycle/choose-app');
        $appInfo = new AppInfo('MyCoolService', '6.6.0.0', 'a1bcd', '6.6.0.0-a1bcd', 'https://mycoolservice.com/service/lifecycle/app-zip/6.6.0.0');

        $serviceRegistryClient = $this->createMock(ServiceRegistryClient::class);
        $serviceClientFactory = $this->createMock(ServiceClientFactory::class);
        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([]);
        $tempDirectoryFactory = $this->createMock(AbstractTemporaryDirectoryFactory::class);
        $logger = $this->createMock(LoggerInterface::class);

        $tempDirectoryFactory->method('path')->willReturn('/tmp/path');

        $serviceClient = $this->createMock(ServiceClient::class);
        $serviceClient->expects(static::once())->method('latestAppInfo')->willReturn($appInfo);
        $serviceClientFactory->expects(static::once())->method('newFor')->with($entry)->willReturn($serviceClient);

        $sourceResolver = $this->createMock(ServiceSourceResolver::class);
        $sourceResolver->expects(static::once())
            ->method('filesystemForVersion')
            ->with($appInfo)
            ->willReturn(new StaticFilesystem());

        $manifest = $this->createManifest();
        $manifestFactory = $this->createMock(ManifestFactory::class);
        $manifestFactory
            ->expects(static::once())
            ->method('createFromXmlFile')
            ->with('/app-root/manifest.xml')
            ->willReturn($manifest);

        $appLifecycle->expects(static::once())
            ->method('install')
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

        $lifecycle = new ServiceLifecycle(
            $serviceRegistryClient,
            $serviceClientFactory,
            $appLifecycle,
            $appRepo,
            $logger,
            $manifestFactory,
            $sourceResolver
        );

        static::assertTrue($lifecycle->install($entry));
    }

    public function testUpdateThrowsExceptionWhenAppDoesNotExist(): void
    {
        static::expectExceptionObject(ServicesException::notFound('name', 'MyCoolService'));

        $entry = new ServiceRegistryEntry('MyCoolService', 'MyCoolService', 'https://mycoolservice.com', '/service/lifecycle/choose-app');

        $serviceRegistryClient = $this->createMock(ServiceRegistryClient::class);
        $serviceClientFactory = $this->createMock(ServiceClientFactory::class);
        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([[]]);
        $logger = $this->createMock(LoggerInterface::class);
        $manifestFactory = $this->createMock(ManifestFactory::class);

        $serviceRegistryClient->expects(static::once())->method('get')->with('MyCoolService')->willReturn($entry);

        $lifecycle = new ServiceLifecycle(
            $serviceRegistryClient,
            $serviceClientFactory,
            $appLifecycle,
            $appRepo,
            $logger,
            $manifestFactory,
            $this->createMock(ServiceSourceResolver::class)
        );

        static::assertFalse($lifecycle->update('MyCoolService', Context::createDefaultContext()));
    }

    public function testUpdateLogsErrorIfAppCannotBeDownloaded(): void
    {
        $entry = new ServiceRegistryEntry('MyCoolService', 'MyCoolService', 'https://mycoolservice.com', '/service/lifecycle/choose-app');

        $serviceRegistryClient = $this->createMock(ServiceRegistryClient::class);
        $serviceClientFactory = $this->createMock(ServiceClientFactory::class);
        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setUniqueIdentifier(Uuid::randomHex());
        $app->assign(['name' => 'MyCoolService']);
        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([[$app]]);
        $logger = $this->createMock(LoggerInterface::class);

        $serviceClient = $this->createMock(ServiceClient::class);
        $serviceClient->expects(static::once())->method('latestAppInfo')->willThrowException(ServicesException::missingAppVersionInfo());
        $serviceClientFactory->expects(static::once())->method('newFor')->with($entry)->willReturn($serviceClient);

        $sourceResolver = $this->createMock(ServiceSourceResolver::class);

        $manifestFactory = $this->createMock(ManifestFactory::class);
        $manifestFactory->expects(static::never())->method('createFromXmlFile');

        $appLifecycle->expects(static::never())->method('update');

        $logger
            ->expects(static::once())
            ->method('error')
            ->with('Cannot update service "MyCoolService" because of error: "Error downloading app. The version information was missing."');

        $serviceRegistryClient->expects(static::once())->method('get')->with('MyCoolService')->willReturn($entry);

        $lifecycle = new ServiceLifecycle(
            $serviceRegistryClient,
            $serviceClientFactory,
            $appLifecycle,
            $appRepo,
            $logger,
            $manifestFactory,
            $sourceResolver
        );

        static::assertFalse($lifecycle->update('MyCoolService', Context::createDefaultContext()));
    }

    public function testUpdateDoesNotPerformUpdateIfNoNewVersionIsAvailable(): void
    {
        $entry = new ServiceRegistryEntry('MyCoolService', 'MyCoolService', 'https://mycoolservice.com', '/service/lifecycle/choose-app');
        $appInfo = new AppInfo('MyCoolService', '6.0.0.0', 'a1bcd', '6.0.0.0-a1bcd', 'https://mycoolservice.com/service/lifecycle/app-zip/6.6.0.0');

        $serviceRegistryClient = $this->createMock(ServiceRegistryClient::class);
        $serviceClientFactory = $this->createMock(ServiceClientFactory::class);
        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setUniqueIdentifier(Uuid::randomHex());
        $app->assign(['name' => 'MyCoolService', 'version' => '6.0.0.0-a1bcd', 'aclRoleId' => Uuid::randomHex()]);
        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([[$app]]);
        $logger = $this->createMock(LoggerInterface::class);

        $serviceClient = $this->createMock(ServiceClient::class);
        $serviceClient->expects(static::once())->method('latestAppInfo')->willReturn($appInfo);
        $serviceClientFactory->expects(static::once())->method('newFor')->with($entry)->willReturn($serviceClient);

        $sourceResolver = $this->createMock(ServiceSourceResolver::class);

        $manifestFactory = $this->createMock(ManifestFactory::class);
        $manifestFactory->expects(static::never())->method('createFromXmlFile');

        $appLifecycle->expects(static::never())->method('update');

        $serviceRegistryClient->expects(static::once())->method('get')->with('MyCoolService')->willReturn($entry);

        $lifecycle = new ServiceLifecycle(
            $serviceRegistryClient,
            $serviceClientFactory,
            $appLifecycle,
            $appRepo,
            $logger,
            $manifestFactory,
            $sourceResolver
        );

        static::assertTrue($lifecycle->update('MyCoolService', Context::createDefaultContext()));
    }

    public function testUpdateLogsErrorIfAppCannotBeUpdated(): void
    {
        $entry = new ServiceRegistryEntry('MyCoolService', 'MyCoolService', 'https://mycoolservice.com', '/service/lifecycle/choose-app');
        $appInfo = new AppInfo('MyCoolService', '6.6.0.0', 'a1bcd', '6.6.0.0-a1bcd', 'https://mycoolservice.com/service/lifecycle/app-zip/6.6.0.0');

        $serviceRegistryClient = $this->createMock(ServiceRegistryClient::class);
        $serviceClientFactory = $this->createMock(ServiceClientFactory::class);
        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setUniqueIdentifier(Uuid::randomHex());
        $app->assign(['name' => 'MyCoolService', 'version' => '8.0.0', 'aclRoleId' => Uuid::randomHex()]);
        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([[$app]]);
        $logger = $this->createMock(LoggerInterface::class);

        $serviceClient = $this->createMock(ServiceClient::class);
        $serviceClient->expects(static::once())->method('latestAppInfo')->willReturn($appInfo);
        $serviceClientFactory->expects(static::once())->method('newFor')->with($entry)->willReturn($serviceClient);

        $sourceResolver = $this->createMock(ServiceSourceResolver::class);
        $sourceResolver->expects(static::once())
            ->method('filesystemForVersion')
            ->with($appInfo)
            ->willReturn(new StaticFilesystem());

        $manifest = $this->createManifest();
        $manifestFactory = $this->createMock(ManifestFactory::class);
        $manifestFactory
            ->expects(static::once())
            ->method('createFromXmlFile')
            ->with('/app-root/manifest.xml')
            ->willReturn($manifest);

        $appLifecycle->expects(static::once())
            ->method('update')
            ->willThrowException(AppException::notCompatible('MyCoolService'));

        $logger
            ->expects(static::once())
            ->method('error')
            ->with('Cannot update service "MyCoolService" because of error: "App MyCoolService is not compatible with this Shopware version"');

        $serviceRegistryClient->expects(static::once())->method('get')->with('MyCoolService')->willReturn($entry);

        $lifecycle = new ServiceLifecycle(
            $serviceRegistryClient,
            $serviceClientFactory,
            $appLifecycle,
            $appRepo,
            $logger,
            $manifestFactory,
            $sourceResolver
        );

        static::assertFalse($lifecycle->update('MyCoolService', Context::createDefaultContext()));
    }

    public function testUpdate(): void
    {
        $entry = new ServiceRegistryEntry('MyCoolService', 'MyCoolService', 'https://mycoolservice.com', '/service/lifecycle/choose-app');
        $appInfo = new AppInfo('MyCoolService', '6.6.0.0', 'a1bcd', '6.6.0.0-a1bcd', 'https://mycoolservice.com/service/lifecycle/app-zip/6.6.0.0');

        $serviceRegistryClient = $this->createMock(ServiceRegistryClient::class);
        $serviceClientFactory = $this->createMock(ServiceClientFactory::class);
        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setUniqueIdentifier(Uuid::randomHex());
        $app->assign(['name' => 'MyCoolService', 'version' => '6.0.0', 'aclRoleId' => Uuid::randomHex()]);
        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([[$app]]);
        $logger = $this->createMock(LoggerInterface::class);

        $serviceClient = $this->createMock(ServiceClient::class);
        $serviceClient->expects(static::once())->method('latestAppInfo')->willReturn($appInfo);
        $serviceClientFactory->expects(static::once())->method('newFor')->with($entry)->willReturn($serviceClient);

        $sourceResolver = $this->createMock(ServiceSourceResolver::class);
        $sourceResolver->expects(static::once())
            ->method('filesystemForVersion')
            ->with($appInfo)
            ->willReturn(new StaticFilesystem());

        $manifest = $this->createManifest();
        $manifestFactory = $this->createMock(ManifestFactory::class);
        $manifestFactory
            ->expects(static::once())
            ->method('createFromXmlFile')
            ->with('/app-root/manifest.xml')
            ->willReturn($manifest);

        $appLifecycle->expects(static::once())
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

        $serviceRegistryClient->expects(static::once())->method('get')->with('MyCoolService')->willReturn($entry);

        $lifecycle = new ServiceLifecycle(
            $serviceRegistryClient,
            $serviceClientFactory,
            $appLifecycle,
            $appRepo,
            $logger,
            $manifestFactory,
            $sourceResolver
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
