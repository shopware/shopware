<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppExtractor;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Meta\Metadata;
use Shopware\Core\Framework\App\Source\TemporaryDirectoryFactory;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Services\AppInfo;
use Shopware\Core\Services\Event\ServiceOutdatedEvent;
use Shopware\Core\Services\ServiceClient;
use Shopware\Core\Services\ServiceClientFactory;
use Shopware\Core\Services\ServiceSourceResolver;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(ServiceSourceResolver::class)]
class ServiceSourceResolverTest extends TestCase
{
    public function testName(): void
    {
        $source = new ServiceSourceResolver(
            new TemporaryDirectoryFactory(),
            $this->createMock(ServiceClientFactory::class),
            $this->createMock(AppExtractor::class),
            $this->createMock(Filesystem::class),
            $this->createMock(EventDispatcherInterface::class)
        );
        static::assertEquals('service', $source->name());
    }

    public function testSupportsOnlyConsidersServiceTypes(): void
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setSourceType('service');

        $source = new ServiceSourceResolver(
            new TemporaryDirectoryFactory(),
            $this->createMock(ServiceClientFactory::class),
            $this->createMock(AppExtractor::class),
            $this->createMock(Filesystem::class),
            $this->createMock(EventDispatcherInterface::class)
        );

        static::assertTrue($source->supports($app));

        $app->setSourceType('not-supported');

        static::assertFalse($source->supports($app));
    }

    public function testSupportSelfManagedManifestsWithHttpUrls(): void
    {
        $manifest = static::createMock(Manifest::class);
        $manifest->method('getPath')->willReturn('https://myservice.com');

        $metadata = Metadata::fromArray([
            'name' => 'TestApp',
            'label' => [],
            'author' => 'Shopware',
            'copyright' => 'Shopware',
            'license' => 'Shopware',
            'version' => '1.0',
        ]);

        $metadata->setSelfManaged(true);

        $manifest->method('getMetadata')->willReturn($metadata);

        $source = new ServiceSourceResolver(
            new TemporaryDirectoryFactory(),
            $this->createMock(ServiceClientFactory::class),
            $this->createMock(AppExtractor::class),
            $this->createMock(Filesystem::class),
            $this->createMock(EventDispatcherInterface::class)
        );

        static::assertTrue($source->supports($manifest));
    }

    public static function appProvider(): \Generator
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName('MyCoolService');
        $app->setSourceType('service');
        $app->setSourceConfig([
            'version' => '6.7.0.0',
            'revision' => '6.7.0.0-abcd',
            'zip-url' => 'https://mycoolservice.com/service/lifecycle/app-zip/6.7.0.0',
        ]);

        yield 'app' => [$app];

        $manifest = static::createStub(Manifest::class);

        $manifest->method('getSourceConfig')->willReturn([
            'version' => '6.7.0.0',
            'revision' => '6.7.0.0-abcd',
            'zip-url' => 'https://mycoolservice.com/service/lifecycle/app-zip/6.7.0.0',
        ]);

        $metadata = Metadata::fromArray([
            'name' => 'MyCoolService',
            'label' => [],
            'author' => 'Shopware',
            'copyright' => 'Shopware',
            'license' => 'Shopware',
            'version' => '6.7.0.0',
        ]);

        $manifest->method('getMetadata')->willReturn($metadata);

        yield 'manifest' => [$manifest];
    }

    #[DataProvider('appProvider')]
    public function testAppIsDownloadedIfItDoesNotExistOnFilesystem(AppEntity|Manifest $app): void
    {
        $serviceClientFactory = $this->createMock(ServiceClientFactory::class);

        $appExtractor = $this->createMock(AppExtractor::class);
        $filesystem = $this->createMock(Filesystem::class);

        $appInfo = new AppInfo('MyCoolService', '6.7.0.0', 'abcd', '6.7.0.0-abcd', 'https://mycoolservice.com/service/lifecycle/app-zip/6.7.0.0');

        $serviceClient = $this->createMock(ServiceClient::class);
        $serviceClient->expects(static::once())->method('latestAppInfo')->willReturn($appInfo);
        $serviceClient->expects(static::once())
            ->method('downloadAppZipForVersion')
            ->with('https://mycoolservice.com/service/lifecycle/app-zip/6.7.0.0', '/some/tmp/path/MyCoolService/MyCoolService.zip')
            ->willReturn($appInfo);
        $serviceClientFactory->expects(static::once())->method('fromName')->with('MyCoolService')->willReturn($serviceClient);

        $temporaryDirectoryFactory = $this->createMock(TemporaryDirectoryFactory::class);
        $temporaryDirectoryFactory->expects(static::any())->method('path')->willReturn('/some/tmp/path');

        $source = new ServiceSourceResolver(
            $temporaryDirectoryFactory,
            $serviceClientFactory,
            $appExtractor,
            $filesystem,
            $this->createMock(EventDispatcherInterface::class)
        );

        $fs = $source->filesystem($app);

        static::assertEquals('/some/tmp/path/MyCoolService', $fs->location);
    }

    #[DataProvider('appProvider')]
    public function testAppIsNotDownloadedIfItExistsOnFilesystem(AppEntity|Manifest $app): void
    {
        $serviceClientFactory = $this->createMock(ServiceClientFactory::class);

        $appExtractor = $this->createMock(AppExtractor::class);
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects(static::once())->method('exists')->with('/some/tmp/path/MyCoolService')->willReturn(true);

        $appInfo = new AppInfo('MyCoolService', '6.7.0.0', 'abcd', '6.7.0.0-abcd', 'https://mycoolservice.com/service/lifecycle/app-zip/6.7.0.0');

        $serviceClient = $this->createMock(ServiceClient::class);
        $serviceClient->expects(static::never())->method('latestAppInfo')->willReturn($appInfo);
        $serviceClient->expects(static::never())->method('downloadAppZipForVersion');

        $temporaryDirectoryFactory = $this->createMock(TemporaryDirectoryFactory::class);
        $temporaryDirectoryFactory->expects(static::any())->method('path')->willReturn('/some/tmp/path');

        $source = new ServiceSourceResolver(
            $temporaryDirectoryFactory,
            $serviceClientFactory,
            $appExtractor,
            $filesystem,
            $this->createMock(EventDispatcherInterface::class)
        );

        $fs = $source->filesystem($app);

        static::assertEquals('/some/tmp/path/MyCoolService', $fs->location);
    }

    public function testFilesystemForAppDownloadsServiceUsingClient(): void
    {
        $serviceClientFactory = $this->createMock(ServiceClientFactory::class);

        $appExtractor = $this->createMock(AppExtractor::class);
        $filesystem = $this->createMock(Filesystem::class);

        $appInfo = new AppInfo('MyCoolService', '6.7.0.0', 'abcd', '6.7.0.0-abcd', 'https://mycoolservice.com/service/lifecycle/app-zip/6.7.0.0');

        $serviceClient = $this->createMock(ServiceClient::class);
        $serviceClient->expects(static::once())->method('latestAppInfo')->willReturn($appInfo);
        $serviceClient->expects(static::once())
            ->method('downloadAppZipForVersion')
            ->with('https://mycoolservice.com/service/lifecycle/app-zip/6.7.0.0', '/some/tmp/path/MyCoolService/MyCoolService.zip')
            ->willReturn($appInfo);
        $serviceClientFactory->expects(static::once())->method('fromName')->with('MyCoolService')->willReturn($serviceClient);

        $temporaryDirectoryFactory = $this->createMock(TemporaryDirectoryFactory::class);
        $temporaryDirectoryFactory->expects(static::any())->method('path')->willReturn('/some/tmp/path');

        $source = new ServiceSourceResolver(
            $temporaryDirectoryFactory,
            $serviceClientFactory,
            $appExtractor,
            $filesystem,
            $this->createMock(EventDispatcherInterface::class)
        );

        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName('MyCoolService');
        $app->setSourceType('service');
        $app->setSourceConfig([
            'version' => '6.7.0.0',
            'revision' => '6.7.0.0-abcd',
            'zip-url' => 'https://mycoolservice.com/service/lifecycle/app-zip/6.7.0.0',
        ]);

        $fs = $source->filesystem($app);

        static::assertEquals('/some/tmp/path/MyCoolService', $fs->location);
    }

    public function testIfLatestVersionIsNotInstalledServiceIsUpdatedFirst(): void
    {
        $serviceClientFactory = $this->createMock(ServiceClientFactory::class);

        $appExtractor = $this->createMock(AppExtractor::class);
        $filesystem = $this->createMock(Filesystem::class);

        $appInfo = new AppInfo('MyCoolService', '6.7.0.0', 'abcd', '6.7.0.0-abcd', 'https://mycoolservice.com/service/lifecycle/app-zip/6.7.0.0');

        $serviceClient = $this->createMock(ServiceClient::class);
        $serviceClient->expects(static::once())->method('latestAppInfo')->willReturn($appInfo);
        $serviceClient->expects(static::never())->method('downloadAppZipForVersion');
        $serviceClientFactory->expects(static::once())->method('fromName')->with('MyCoolService')->willReturn($serviceClient);

        $temporaryDirectoryFactory = $this->createMock(TemporaryDirectoryFactory::class);
        $temporaryDirectoryFactory->expects(static::any())->method('path')->willReturn('/some/tmp/path');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(static::once())->method('dispatch')->with(static::isInstanceOf(ServiceOutdatedEvent::class));

        $source = new ServiceSourceResolver(
            $temporaryDirectoryFactory,
            $serviceClientFactory,
            $appExtractor,
            $filesystem,
            $eventDispatcher
        );

        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName('MyCoolService');
        $app->setSourceType('service');
        $app->setSourceConfig([
            'revision' => '6.6.0.0-abbb',
        ]);

        $fs = $source->filesystem($app);

        static::assertEquals('/some/tmp/path/MyCoolService', $fs->location);
    }
}
