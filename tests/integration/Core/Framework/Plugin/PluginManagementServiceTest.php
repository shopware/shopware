<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Plugin;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\StaticAnalyzeKernel;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopware\Core\Framework\App\Event\AppUploadedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Event\PluginUploadedEvent;
use Shopware\Core\Framework\Plugin\ExtensionExtractor;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Plugin\PluginZipDetector;
use Shopware\Core\Framework\Plugin\Util\PluginFinder;
use Shopware\Core\Framework\Test\Plugin\PluginTestsHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Kernel;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
class PluginManagementServiceTest extends TestCase
{
    use KernelTestBehaviour;
    use PluginTestsHelper;

    private const TEST_PLUGIN_ZIP_NAME = 'SwagFashionTheme.zip';
    private const TEST_APP_ZIP_NAME = 'App.zip';
    private const FIXTURE_PATH = __DIR__ . '/../../../../../tests/integration/Core/Framework/Plugin/_fixtures/';
    private const PLUGIN_ZIP_FIXTURE_PATH = self::FIXTURE_PATH . self::TEST_PLUGIN_ZIP_NAME;
    private const APP_ZIP_FIXTURE_PATH = self::FIXTURE_PATH . self::TEST_APP_ZIP_NAME;
    private const PLUGINS_PATH = self::FIXTURE_PATH . 'plugins';
    private const APPS_PATH = self::FIXTURE_PATH . 'apps';
    private const PLUGIN_FASHION_THEME_PATH = self::PLUGINS_PATH . '/SwagFashionTheme';
    private const PLUGIN_FASHION_THEME_BASE_CLASS_PATH = self::PLUGIN_FASHION_THEME_PATH . '/SwagFashionTheme.php';

    private Filesystem $filesystem;

    private string $cacheDir;

    protected function setUp(): void
    {
        $this->filesystem = static::getContainer()->get(Filesystem::class);

        $this->cacheDir = $this->createTestCacheDirectory();

        $this->filesystem->copy(
            self::FIXTURE_PATH . 'archives/' . self::TEST_PLUGIN_ZIP_NAME,
            self::PLUGIN_ZIP_FIXTURE_PATH
        );
        $this->filesystem->copy(
            self::FIXTURE_PATH . 'archives/' . self::TEST_APP_ZIP_NAME,
            self::APP_ZIP_FIXTURE_PATH
        );
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove(self::PLUGIN_FASHION_THEME_PATH);
        $this->filesystem->remove(self::PLUGIN_ZIP_FIXTURE_PATH);
        $this->filesystem->remove(self::APP_ZIP_FIXTURE_PATH);
        // App.zip extracts into the shared fixture dir; its root must never collide with a
        // committed fixture app (it used to be `plugin/`, silently overwriting apps/plugin)
        $this->filesystem->remove(self::APPS_PATH . '/SwagApp');
        $this->filesystem->remove($this->cacheDir);

        Kernel::getConnection()->executeStatement('DELETE FROM plugin');
    }

    public function testUploadPlugin(): void
    {
        $pluginFile = $this->createUploadedFile();
        $this->getPluginManagementService()->uploadPlugin($pluginFile, Context::createDefaultContext());

        static::assertFileExists(self::PLUGIN_FASHION_THEME_PATH);
        static::assertFileExists(self::PLUGIN_FASHION_THEME_BASE_CLASS_PATH);
    }

    #[DataProvider('uploadProvider')]
    public function testDispatchesUploadEventOnlyForSuccessfulPluginUploads(string $type, bool $fails, string $archive = 'SwagFashionTheme.zip', string $pluginName = 'SwagFashionTheme', ?string $pluginVersion = 'v1.0.0'): void
    {
        $context = Context::createDefaultContext();
        $file = $this->createUploadedFileMock($archive, 'example.zip');
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
            new Client(['handler' => new MockHandler()]),
            $dispatcher
        );

        $service->uploadPlugin($file, $context);
    }

    public function testInvalidComposerJsonFailsBeforeExtractionAndLogging(): void
    {
        $file = $this->createUploadedFileMock('UploadedPluginInvalidJson.zip', 'invalid.zip');
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
            new Client(['handler' => new MockHandler()]),
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
        yield 'app upload logs manifest metadata instead of directory name' => [PluginManagementService::APP, false, 'UploadedApp.zip', 'SwagApp', '1.0.0'];
        yield 'failed app extraction is not logged' => [PluginManagementService::APP, true, 'UploadedApp.zip', 'SwagApp', '1.0.0'];
        yield 'failed plugin extraction is not logged' => [PluginManagementService::PLUGIN, true];
    }

    public function testExtractPluginZip(): void
    {
        $this->getPluginManagementService()->extractPluginZip(self::PLUGIN_ZIP_FIXTURE_PATH);

        $extractedPlugin = $this->filesystem->exists(self::PLUGIN_FASHION_THEME_PATH);
        $extractedPluginBaseClass = $this->filesystem->exists(self::PLUGIN_FASHION_THEME_BASE_CLASS_PATH);
        $pluginZipExists = $this->filesystem->exists(self::PLUGIN_ZIP_FIXTURE_PATH);
        static::assertTrue($extractedPlugin);
        static::assertTrue($extractedPluginBaseClass);
        static::assertFalse($pluginZipExists);
    }

    public function testExtractPluginZipWithoutDeletion(): void
    {
        $this->getPluginManagementService()->extractPluginZip(self::PLUGIN_ZIP_FIXTURE_PATH, false);

        $extractedPlugin = $this->filesystem->exists(self::PLUGIN_FASHION_THEME_PATH);
        $extractedPluginBaseClass = $this->filesystem->exists(self::PLUGIN_FASHION_THEME_BASE_CLASS_PATH);
        $pluginZipExists = $this->filesystem->exists(self::PLUGIN_ZIP_FIXTURE_PATH);
        static::assertTrue($extractedPlugin);
        static::assertTrue($extractedPluginBaseClass);
        static::assertTrue($pluginZipExists);
    }

    public function testClearContainerCacheWhenStoreTypeIsPlugin(): void
    {
        $this->getPluginManagementService()->extractPluginZip(self::PLUGIN_ZIP_FIXTURE_PATH, true, PluginManagementService::PLUGIN);

        static::assertFalse($this->containerCacheExists());
    }

    public function testDoNotClearContainerCacheWhenStoreTypeIsNotPlugin(): void
    {
        $this->getPluginManagementService()->extractPluginZip(self::APP_ZIP_FIXTURE_PATH, true, PluginManagementService::APP);

        static::assertTrue($this->containerCacheExists());
    }

    public function testClearContainerCacheWhenPluginZipIsGiven(): void
    {
        $this->getPluginManagementService()->extractPluginZip(self::PLUGIN_ZIP_FIXTURE_PATH);

        static::assertFalse($this->containerCacheExists());
    }

    public function testDoNotClearContainerCacheWhenAppZipIsGiven(): void
    {
        $this->getPluginManagementService()->extractPluginZip(self::APP_ZIP_FIXTURE_PATH);

        static::assertTrue($this->containerCacheExists());
    }

    private function createTestCacheDirectory(): string
    {
        $previousKernelClass = KernelFactory::$kernelClass;

        // We need a new fixed cache dir, therefore, we reuse the StaticAnalyzeKernel class
        KernelFactory::$kernelClass = StaticAnalyzeKernel::class;

        $newTestKernel = KernelFactory::create(
            'test',
            true,
            KernelLifecycleManager::getClassLoader(),
            new StaticKernelPluginLoader(KernelLifecycleManager::getClassLoader()),
            static::getContainer()->get(Connection::class)
        );
        static::assertInstanceOf(Kernel::class, $newTestKernel);
        // reset the kernel class for further tests
        KernelFactory::$kernelClass = $previousKernelClass;
        $newTestKernel->boot();
        $cacheDir = $newTestKernel->getCacheDir();
        $newTestKernel->shutdown();

        return $cacheDir;
    }

    private function createUploadedFile(): UploadedFile
    {
        return new UploadedFile(self::PLUGIN_ZIP_FIXTURE_PATH, self::TEST_PLUGIN_ZIP_NAME, null, null, true);
    }

    private function createUploadedFileMock(string $archive, string $filename): UploadedFile
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalName')->willReturn($filename);
        $file->expects($this->once())->method('move')->willReturnCallback(static function (string $directory, string $name) use ($archive): File {
            // Remove the temporary file created by uploadPlugin; read the committed ZIP instead.
            (new Filesystem())->remove($directory . '/' . $name);

            return new File(self::FIXTURE_PATH . 'archives/' . $archive);
        });

        return $file;
    }

    private function getPluginManagementService(): PluginManagementService
    {
        return new PluginManagementService(
            self::PLUGINS_PATH,
            new PluginZipDetector(),
            new ExtensionExtractor([
                PluginManagementService::PLUGIN => self::PLUGINS_PATH,
                PluginManagementService::APP => self::APPS_PATH,
            ], $this->filesystem),
            $this->getPluginService(),
            $this->filesystem,
            $this->getCacheClearer(),
            static::getContainer()->get('shopware.store_download_client'),
            static::createStub(EventDispatcherInterface::class)
        );
    }

    private function getPluginService(): PluginService
    {
        return $this->createPluginService(
            self::FIXTURE_PATH . 'plugins',
            static::getContainer()->getParameter('kernel.project_dir'),
            static::getContainer()->get('plugin.repository'),
            static::getContainer()->get('language.repository'),
            static::getContainer()->get(PluginFinder::class)
        );
    }

    private function getCacheClearer(): CacheClearer
    {
        return new CacheClearer(
            [],
            static::getContainer()->get('cache_clearer'),
            null,
            static::getContainer()->get(CacheInvalidator::class),
            $this->filesystem,
            $this->cacheDir,
            'test',
            false,
            false,
            static::getContainer()->get('messenger.default_bus'),
            static::getContainer()->get('logger'),
            static::getContainer()->get('lock.factory')
        );
    }

    private function containerCacheExists(): bool
    {
        return (new Finder())->in($this->cacheDir)->name('*Container*')->depth(0)->count() !== 0;
    }
}
