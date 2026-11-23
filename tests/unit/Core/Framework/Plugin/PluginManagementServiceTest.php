<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\App\Event\AppUploadedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Event\PluginUploadedEvent;
use Shopware\Core\Framework\Plugin\ExtensionExtractor;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginException;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Plugin\PluginZipDetector;
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
    #[DataProvider('uploadProvider')]
    public function testDispatchesUploadEventOnlyForSuccessfulPluginUploads(string $type, bool $fails, string $archive = 'SwagFashionTheme.zip', string $pluginName = 'SwagFashionTheme', ?string $pluginVersion = 'v1.0.0'): void
    {
        $context = Context::createDefaultContext();
        $file = $this->createUploadedFile($archive, 'example.zip');
        $detector = static::createStub(PluginZipDetector::class);
        $detector->method('detect')->willReturn($type);
        $extractor = $this->createMock(ExtensionExtractor::class);
        if ($fails) {
            $extractor->expects($this->once())->method('extract')->willThrowException(new \RuntimeException('Extraction failed'));
            $this->expectExceptionObject(new \RuntimeException('Extraction failed'));
        } else {
            $extractor->expects($this->once())->method('extract');
        }
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        if (!$fails) {
            $dispatcher->expects($this->once())->method('dispatch')->with(static::callback(static function (PluginUploadedEvent|AppUploadedEvent $event) use ($context, $pluginName, $pluginVersion, $type): bool {
                static::assertSame('example.zip', $event->filename);
                if ($type === PluginManagementService::PLUGIN) {
                    static::assertInstanceOf(PluginUploadedEvent::class, $event);
                    static::assertSame($pluginName, $event->pluginName);
                    static::assertSame($pluginVersion, $event->pluginVersion);
                } else {
                    static::assertInstanceOf(AppUploadedEvent::class, $event);
                    static::assertSame($pluginName, $event->appName);
                    static::assertSame($pluginVersion, $event->appVersion);
                }
                static::assertSame($context, $event->context);

                return true;
            }));
        } else {
            $dispatcher->expects($this->never())->method('dispatch');
        }
        $pluginService = $this->createMock(PluginService::class);
        $pluginService->expects($type === PluginManagementService::PLUGIN && !$fails ? $this->once() : $this->never())
            ->method('refreshPlugins');
        $service = new PluginManagementService(
            '',
            $detector,
            $extractor,
            $pluginService,
            new Filesystem(),
            static::createStub(CacheClearer::class),
            $this->createClient([]),
            $dispatcher
        );

        $service->uploadPlugin($file, $context);
    }

    public function testInvalidComposerJsonFailsBeforeExtractionAndLogging(): void
    {
        $file = $this->createUploadedFile('UploadedPluginInvalidJson.zip', 'invalid.zip');
        $detector = static::createStub(PluginZipDetector::class);
        $detector->method('detect')->willReturn(PluginManagementService::PLUGIN);
        $extractor = $this->createMock(ExtensionExtractor::class);
        $extractor->expects($this->never())->method('extract');
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');
        $service = new PluginManagementService(
            '',
            $detector,
            $extractor,
            static::createStub(PluginService::class),
            static::createStub(Filesystem::class),
            static::createStub(CacheClearer::class),
            $this->createClient([]),
            $dispatcher
        );
        $this->expectExceptionObject(new \JsonException('Syntax error', \JSON_ERROR_SYNTAX));

        $service->uploadPlugin($file, Context::createDefaultContext());
    }

    public static function uploadProvider(): \Generator
    {
        yield 'successful plugin upload' => [PluginManagementService::PLUGIN, false];
        yield 'plugin class differs from directory and uploaded filename' => [PluginManagementService::PLUGIN, false, 'UploadedPlugin.zip', 'ActualPlugin', '2.0.0'];
        yield 'plugin version is optional' => [PluginManagementService::PLUGIN, false, 'UploadedPluginWithoutVersion.zip', 'ActualPlugin', null];
        yield 'app upload logs manifest metadata instead of directory name' => [PluginManagementService::APP, false, 'App.zip', 'SwagApp', '1.0.0'];
        yield 'failed app extraction is not logged' => [PluginManagementService::APP, true, 'App.zip', 'SwagApp', '1.0.0'];
        yield 'failed plugin extraction is not logged' => [PluginManagementService::PLUGIN, true];
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
            static::createStub(EventDispatcherInterface::class)
        );

        $pluginManagementService->downloadStorePlugin(
            $this->createPluginDownloadDataStruct(PluginManagementService::PLUGIN),
            Context::createDefaultContext()
        );
    }

    public function testExtractPluginWithDetectedPlugin(): void
    {
        $client = $this->createClient([new Response()]);

        $pluginService = static::createStub(PluginService::class);

        $pluginZipDetector = $this->createMock(PluginZipDetector::class);
        $pluginZipDetector->expects($this->once())
            ->method('detect')
            ->with('/some/zip/file.zip')
            ->willReturn(PluginManagementService::PLUGIN);

        $extractor = $this->createMock(ExtensionExtractor::class);
        $extractor->expects($this->once())
            ->method('extract')
            ->with('/some/zip/file.zip');

        $cacheClearer = $this->createMock(CacheClearer::class);
        $cacheClearer->expects($this->once())
            ->method('clearContainerCache');

        $pluginManagementService = new PluginManagementService(
            '',
            $pluginZipDetector,
            $extractor,
            $pluginService,
            static::createStub(Filesystem::class),
            $cacheClearer,
            $client,
            static::createStub(EventDispatcherInterface::class)
        );

        $pluginManagementService->extractPluginZip(
            '/some/zip/file.zip',
        );
    }

    public function testExtractPluginWithDetectedApp(): void
    {
        $client = $this->createClient([new Response()]);

        $pluginService = static::createStub(PluginService::class);

        $pluginZipDetector = $this->createMock(PluginZipDetector::class);
        $pluginZipDetector->expects($this->once())
            ->method('detect')
            ->with('/some/zip/file.zip')
            ->willReturn(PluginManagementService::APP);

        $extractor = $this->createMock(ExtensionExtractor::class);
        $extractor->expects($this->once())
            ->method('extract')
            ->with('/some/zip/file.zip');

        $pluginManagementService = new PluginManagementService(
            '',
            $pluginZipDetector,
            $extractor,
            $pluginService,
            static::createStub(Filesystem::class),
            static::createStub(CacheClearer::class),
            $client,
            static::createStub(EventDispatcherInterface::class)
        );

        $pluginManagementService->extractPluginZip(
            '/some/zip/file.zip',
        );
    }

    public function testDoesNotRefreshPluginsAfterStoreDownloadIfTypeIsNotPlugin(): void
    {
        $client = $this->createClient([new Response()]);

        $pluginService = $this->createMock(PluginService::class);
        $pluginService->expects($this->never())
            ->method('refreshPlugins');

        $pluginManagementService = new PluginManagementService(
            '',
            static::createStub(PluginZipDetector::class),
            static::createStub(ExtensionExtractor::class),
            $pluginService,
            static::createStub(Filesystem::class),
            static::createStub(CacheClearer::class),
            $client,
            static::createStub(EventDispatcherInterface::class)
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

        $pluginManagementService = new PluginManagementService(
            '',
            static::createStub(PluginZipDetector::class),
            static::createStub(ExtensionExtractor::class),
            static::createStub(PluginService::class),
            $fs,
            static::createStub(CacheClearer::class),
            new Client(['handler' => new MockHandler()]),
            static::createStub(EventDispatcherInterface::class)
        );

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

        $pluginManagementService = new PluginManagementService(
            '',
            static::createStub(PluginZipDetector::class),
            static::createStub(ExtensionExtractor::class),
            static::createStub(PluginService::class),
            $fs,
            static::createStub(CacheClearer::class),
            new Client(['handler' => new MockHandler()]),
            static::createStub(EventDispatcherInterface::class)
        );

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

        $pluginManagementService = new PluginManagementService(
            '',
            static::createStub(PluginZipDetector::class),
            static::createStub(ExtensionExtractor::class),
            static::createStub(PluginService::class),
            $fs,
            static::createStub(CacheClearer::class),
            new Client(['handler' => new MockHandler()]),
            static::createStub(EventDispatcherInterface::class)
        );

        $plugin = new PluginEntity();
        $plugin->setManagedByComposer(true);
        $plugin->setPath('custom/plugins//test');
        $plugin->setName('Test');

        $pluginManagementService->deletePlugin($plugin, Context::createDefaultContext());
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
