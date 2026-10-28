<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\App\AppDirectory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Event\PluginUploadedEvent;
use Shopware\Core\Framework\Plugin\ExtensionExtractor;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginException;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Plugin\PluginZipDetector;
use Shopware\Core\Framework\Store\Services\AbstractAppExtensionService;
use Shopware\Core\Framework\Store\Struct\PluginDownloadDataStruct;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PluginManagementService::class)]
class PluginManagementServiceTest extends TestCase
{
    public function testAppUploadIsDelegatedToAppExtensionService(): void
    {
        $context = Context::createDefaultContext();
        $file = new UploadedFile(__DIR__ . '/_fixtures/archives/App.zip', 'example.zip', null, null, true);

        $appExtensionService = $this->createMock(AbstractAppExtensionService::class);
        $appExtensionService->expects($this->once())
            ->method('upload')
            ->with($file, $context)
            ->willReturn('SwagApp');

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects($this->once())->method('remove')->with($file->getPathname());

        $service = new PluginManagementService(
            '',
            $this->createDetector(PluginManagementService::APP),
            static::createStub(ExtensionExtractor::class),
            static::createStub(PluginService::class),
            $filesystem,
            static::createStub(CacheClearer::class),
            $this->createClient([]),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(AppDirectory::class),
            $appExtensionService,
        );

        static::assertSame('SwagApp', $service->uploadPlugin($file, $context));
    }

    #[DataProvider('pluginUploadProvider')]
    public function testDispatchesUploadEventOnlyForSuccessfulPluginUploads(bool $fails, string $archive = 'SwagFashionTheme.zip', string $pluginName = 'SwagFashionTheme', ?string $pluginVersion = 'v1.0.0'): void
    {
        $context = Context::createDefaultContext();
        $file = $this->createUploadedFile($archive, 'example.zip');
        $extractor = $this->createMock(ExtensionExtractor::class);
        if ($fails) {
            $extractor->expects($this->once())->method('extract')->willThrowException(new \RuntimeException('Extraction failed'));
            $this->expectExceptionObject(new \RuntimeException('Extraction failed'));
        } else {
            $extractor->expects($this->once())->method('extract');
        }
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        if (!$fails) {
            $dispatcher->expects($this->once())->method('dispatch')->with(static::callback(static function (PluginUploadedEvent $event) use ($context, $pluginName, $pluginVersion): bool {
                static::assertSame('example.zip', $event->filename);
                static::assertSame($pluginName, $event->pluginName);
                static::assertSame($pluginVersion, $event->pluginVersion);
                static::assertSame($context, $event->context);

                return true;
            }));
        } else {
            $dispatcher->expects($this->never())->method('dispatch');
        }
        $pluginService = $this->createMock(PluginService::class);
        $pluginService->expects(!$fails ? $this->once() : $this->never())
            ->method('refreshPlugins');
        $service = new PluginManagementService(
            '',
            $this->createDetector(PluginManagementService::PLUGIN),
            $extractor,
            $pluginService,
            new Filesystem(),
            static::createStub(CacheClearer::class),
            $this->createClient([]),
            $dispatcher,
            static::createStub(AppDirectory::class),
            static::createStub(AbstractAppExtensionService::class),
        );

        $service->uploadPlugin($file, $context);
    }

    public static function pluginUploadProvider(): \Generator
    {
        yield 'successful plugin upload' => [false];
        yield 'plugin class differs from directory and uploaded filename' => [false, 'UploadedPlugin.zip', 'ActualPlugin', '2.0.0'];
        yield 'plugin version is optional' => [false, 'UploadedPluginWithoutVersion.zip', 'ActualPlugin', null];
        yield 'failed plugin extraction is not logged' => [true];
    }

    public function testInvalidComposerJsonFailsBeforeExtractionAndLogging(): void
    {
        $file = $this->createUploadedFile('UploadedPluginInvalidJson.zip', 'invalid.zip');
        $extractor = $this->createMock(ExtensionExtractor::class);
        $extractor->expects($this->never())->method('extract');
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');
        $service = new PluginManagementService(
            '',
            $this->createDetector(PluginManagementService::PLUGIN),
            $extractor,
            static::createStub(PluginService::class),
            static::createStub(Filesystem::class),
            static::createStub(CacheClearer::class),
            $this->createClient([]),
            $dispatcher,
            static::createStub(AppDirectory::class),
            static::createStub(AbstractAppExtensionService::class),
        );
        $this->expectExceptionObject(new \JsonException('Syntax error', \JSON_ERROR_SYNTAX));

        $service->uploadPlugin($file, Context::createDefaultContext());
    }

    public function testRefreshesPluginsAfterDownloadingFromStore(): void
    {
        $client = $this->createClient([new Response()]);

        $pluginService = $this->createMock(PluginService::class);
        $pluginService->expects($this->once())->method('refreshPlugins');

        $extractor = $this->createMock(ExtensionExtractor::class);
        $extractor->expects($this->once())
            ->method('extract');

        $pluginManagementService = new PluginManagementService(
            '',
            static::createStub(PluginZipDetector::class),
            $extractor,
            $pluginService,
            static::createStub(Filesystem::class),
            static::createStub(CacheClearer::class),
            $client,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(AppDirectory::class),
            static::createStub(AbstractAppExtensionService::class),
        );

        $pluginManagementService->downloadStorePlugin(
            $this->createPluginDownloadDataStruct(PluginManagementService::PLUGIN),
            Context::createDefaultContext()
        );
    }

    public function testExtractPluginWithDetectedPlugin(): void
    {
        $extractor = $this->createMock(ExtensionExtractor::class);
        $extractor->expects($this->once())
            ->method('extract')
            ->with('/some/zip/file.zip');

        $cacheClearer = $this->createMock(CacheClearer::class);
        $cacheClearer->expects($this->once())
            ->method('clearContainerCache');

        $pluginManagementService = new PluginManagementService(
            '',
            $this->createDetector(PluginManagementService::PLUGIN),
            $extractor,
            static::createStub(PluginService::class),
            static::createStub(Filesystem::class),
            $cacheClearer,
            $this->createClient([]),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(AppDirectory::class),
            static::createStub(AbstractAppExtensionService::class),
        );

        $pluginManagementService->extractPluginZip('/some/zip/file.zip');
    }

    public function testExtractPluginWithDetectedAppStoresApp(): void
    {
        $appDirectory = $this->createMock(AppDirectory::class);
        $appDirectory->expects($this->once())->method('store')->with('/some/zip/file.zip');

        $pluginManagementService = new PluginManagementService(
            '',
            $this->createDetector(PluginManagementService::APP),
            static::createStub(ExtensionExtractor::class),
            static::createStub(PluginService::class),
            static::createStub(Filesystem::class),
            static::createStub(CacheClearer::class),
            $this->createClient([]),
            static::createStub(EventDispatcherInterface::class),
            $appDirectory,
            static::createStub(AbstractAppExtensionService::class),
        );

        $pluginManagementService->extractPluginZip('/some/zip/file.zip');
    }

    public function testDoesNotRefreshPluginsAfterStoreDownloadIfTypeIsNotPlugin(): void
    {
        $pluginService = $this->createMock(PluginService::class);
        $pluginService->expects($this->never())
            ->method('refreshPlugins');

        $appDirectory = $this->createMock(AppDirectory::class);
        $appDirectory->expects($this->once())->method('store');

        $pluginManagementService = new PluginManagementService(
            '',
            static::createStub(PluginZipDetector::class),
            static::createStub(ExtensionExtractor::class),
            $pluginService,
            static::createStub(Filesystem::class),
            static::createStub(CacheClearer::class),
            $this->createClient([new Response()]),
            static::createStub(EventDispatcherInterface::class),
            $appDirectory,
            static::createStub(AbstractAppExtensionService::class),
        );

        $pluginManagementService->downloadStorePlugin(
            $this->createPluginDownloadDataStruct(PluginManagementService::APP),
            Context::createDefaultContext()
        );
    }

    public function testDeleteWhenManaged(): void
    {
        $fs = $this->createMock(Filesystem::class);
        $fs->expects($this->never())->method('remove');

        $pluginManagementService = $this->createService($fs);

        $plugin = new PluginEntity();
        $plugin->setManagedByComposer(true);
        $plugin->setPath('vendor/test');
        $plugin->setName('Test');

        $this->expectExceptionObject(PluginException::cannotDeleteManaged($plugin->getName()));
        $pluginManagementService->deletePlugin($plugin, Context::createDefaultContext());
    }

    public function testDeleteWhenManagedInStaticPlugins(): void
    {
        $fs = $this->createMock(Filesystem::class);
        $fs->expects($this->never())->method('remove');

        $pluginManagementService = $this->createService($fs);

        $plugin = new PluginEntity();
        $plugin->setManagedByComposer(true);
        $plugin->setPath('custom/static-plugins/test');
        $plugin->setName('Test');

        static::expectException(PluginException::class);
        $pluginManagementService->deletePlugin($plugin, Context::createDefaultContext());
    }

    public function testDeleteWhenManagedInCustomPluginsStillWorks(): void
    {
        $fs = $this->createMock(Filesystem::class);
        $fs->expects($this->once())->method('remove');

        $pluginManagementService = $this->createService($fs);

        $plugin = new PluginEntity();
        $plugin->setManagedByComposer(true);
        $plugin->setPath('custom/plugins//test');
        $plugin->setName('Test');

        $pluginManagementService->deletePlugin($plugin, Context::createDefaultContext());
    }

    private function createService(Filesystem $filesystem): PluginManagementService
    {
        return new PluginManagementService(
            '',
            static::createStub(PluginZipDetector::class),
            static::createStub(ExtensionExtractor::class),
            static::createStub(PluginService::class),
            $filesystem,
            static::createStub(CacheClearer::class),
            new Client(['handler' => new MockHandler()]),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(AppDirectory::class),
            static::createStub(AbstractAppExtensionService::class),
        );
    }

    private function createDetector(string $type): PluginZipDetector
    {
        $detector = static::createStub(PluginZipDetector::class);
        $detector->method('detect')->willReturn($type);

        return $detector;
    }

    private function createUploadedFile(string $archive, string $filename): UploadedFile
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalName')->willReturn($filename);
        $file->expects($this->once())->method('move')->willReturnCallback(static function (string $directory, string $name) use ($archive): File {
            // Remove the temporary file created by uploadPlugin; read the committed ZIP instead.
            (new Filesystem())->remove($directory . '/' . $name);

            return new File(__DIR__ . '/_fixtures/archives/' . $archive);
        });

        return $file;
    }

    /**
     * @param list<Response> $responses
     */
    private function createClient(array $responses = []): Client
    {
        $mockHandler = new MockHandler($responses);

        return new Client(['handler' => $mockHandler]);
    }

    private function createPluginDownloadDataStruct(string $type): PluginDownloadDataStruct
    {
        $pluginDownloadData = new PluginDownloadDataStruct();
        $pluginDownloadData->assign([
            'location' => 'location',
            'type' => $type,
        ]);

        return $pluginDownloadData;
    }
}
