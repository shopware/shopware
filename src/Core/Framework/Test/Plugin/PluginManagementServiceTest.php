<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\StaticAnalyzeKernel;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Framework\Plugin\PluginExtractor;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Plugin\PluginZipDetector;
use Shopware\Core\Framework\Plugin\Util\PluginFinder;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Kernel;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @internal
 */
#[Group('slow')]
#[Group('skip-paratest')]
class PluginManagementServiceTest extends TestCase
{
    use KernelTestBehaviour;
    use PluginTestsHelper;

    private const TEST_PLUGIN_ZIP_NAME = 'SwagFashionTheme.zip';
    private const TEST_APP_ZIP_NAME = 'App.zip';
    private const FIXTURE_PATH = __DIR__ . '/_fixture/';
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
        $this->filesystem = $this->getContainer()->get(Filesystem::class);

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
        $this->getPluginManagementService()->extractPluginZip(self::PLUGIN_ZIP_FIXTURE_PATH, true);

        static::assertFalse($this->containerCacheExists());
    }

    public function testDoNotClearContainerCacheWhenAppZipIsGiven(): void
    {
        $this->getPluginManagementService()->extractPluginZip(self::APP_ZIP_FIXTURE_PATH, true);

        static::assertTrue($this->containerCacheExists());
    }

    private function createTestCacheDirectory(): string
    {
        $previousKernelClass = KernelFactory::$kernelClass;

        // We need a new fixed cache dir, therefore we reuse the StaticAnalyzeKernel class
        KernelFactory::$kernelClass = StaticAnalyzeKernel::class;

        /** @var Kernel $newTestKernel */
        $newTestKernel = KernelFactory::create(
            'test',
            true,
            KernelLifecycleManager::getClassLoader(),
            new StaticKernelPluginLoader(KernelLifecycleManager::getClassLoader()),
            $this->getContainer()->get(Connection::class)
        );
        // reset kernel class for further tests
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

    private function getPluginManagementService(): PluginManagementService
    {
        return new PluginManagementService(
            self::PLUGINS_PATH,
            new PluginZipDetector(),
            new PluginExtractor([
                'plugin' => self::PLUGINS_PATH,
                'app' => self::APPS_PATH,
            ], $this->filesystem),
            $this->getPluginService(),
            $this->filesystem,
            $this->getCacheClearer(),
            $this->getContainer()->get('shopware.store_download_client')
        );
    }

    private function getPluginService(): PluginService
    {
        return $this->createPluginService(
            __DIR__ . '/_fixture/plugins',
            $this->getContainer()->getParameter('kernel.project_dir'),
            $this->getContainer()->get('plugin.repository'),
            $this->getContainer()->get('language.repository'),
            $this->getContainer()->get(PluginFinder::class)
        );
    }

    private function getCacheClearer(): CacheClearer
    {
        return new CacheClearer(
            [],
            $this->getContainer()->get('cache_clearer'),
            $this->getContainer()->get(CacheInvalidator::class),
            $this->filesystem,
            $this->cacheDir,
            'test',
            false,
            $this->getContainer()->get('messenger.bus.shopware'),
            $this->getContainer()->get('logger')
        );
    }

    private function containerCacheExists(): bool
    {
        return (new Finder())->in($this->cacheDir)->name('*Container*')->depth(0)->count() !== 0;
    }
}
