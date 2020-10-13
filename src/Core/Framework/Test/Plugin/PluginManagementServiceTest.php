<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Framework\Plugin\PluginExtractor;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Plugin\PluginZipDetector;
use Shopware\Core\Framework\Plugin\Util\PluginFinder;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Development\Kernel;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @group slow
 */
class PluginManagementServiceTest extends TestCase
{
    use KernelTestBehaviour;
    use PluginTestsHelper;

    private const TEST_ZIP_NAME = 'SwagFashionTheme.zip';
    private const FIXTURE_PATH = __DIR__ . '/_fixture/';
    private const PLUGIN_ZIP_FIXTURE_PATH = self::FIXTURE_PATH . self::TEST_ZIP_NAME;
    private const PLUGINS_PATH = self::FIXTURE_PATH . 'plugins';
    private const PLUGIN_FASHION_THEME_PATH = self::PLUGINS_PATH . '/SwagFashionTheme';

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var string
     */
    private $cacheDir;

    protected function setUp(): void
    {
        $this->filesystem = $this->getContainer()->get(Filesystem::class);

        $this->cacheDir = $this->createTestCacheDirectory();

        $this->filesystem->copy(
            self::FIXTURE_PATH . 'archives/' . self::TEST_ZIP_NAME,
            self::PLUGIN_ZIP_FIXTURE_PATH
        );
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove(self::PLUGIN_FASHION_THEME_PATH);
        $this->filesystem->remove($this->cacheDir);

        \Shopware\Core\Kernel::getConnection()->executeUpdate('DELETE FROM plugin');
    }

    public function testUploadPlugin(): void
    {
        $pluginFile = $this->createUploadedFile();
        $this->getPluginManagementService()->uploadPlugin($pluginFile, Context::createDefaultContext());

        static::assertFileExists(self::PLUGIN_FASHION_THEME_PATH);
        static::assertFileExists(self::PLUGIN_FASHION_THEME_PATH . '/SwagFashionTheme.php');
    }

    public function testExtractPluginZip(): void
    {
        $this->getPluginManagementService()->extractPluginZip(__DIR__ . '/_fixture/SwagFashionTheme.zip', true);

        $extractedPlugin = $this->filesystem->exists(__DIR__ . '/_fixture/plugins/SwagFashionTheme');
        $extractedPluginBaseClass = $this->filesystem->exists(__DIR__ . '/_fixture/plugins/SwagFashionTheme/SwagFashionTheme.php');
        $pluginZipExists = $this->filesystem->exists(__DIR__ . '/_fixture/SwagFashionTheme.zip');
        static::assertTrue($extractedPlugin);
        static::assertTrue($extractedPluginBaseClass);
        static::assertFalse($pluginZipExists);
    }

    public function testExtractPluginZipWithoutDeletion(): void
    {
        $this->getPluginManagementService()->extractPluginZip(__DIR__ . '/_fixture/SwagFashionTheme.zip', false);

        $extractedPlugin = $this->filesystem->exists(__DIR__ . '/_fixture/plugins/SwagFashionTheme');
        $extractedPluginBaseClass = $this->filesystem->exists(__DIR__ . '/_fixture/plugins/SwagFashionTheme/SwagFashionTheme.php');
        $pluginZipExists = $this->filesystem->exists(__DIR__ . '/_fixture/SwagFashionTheme.zip');
        static::assertTrue($extractedPlugin);
        static::assertTrue($extractedPluginBaseClass);
        static::assertTrue($pluginZipExists);
    }

    private function createTestCacheDirectory(): string
    {
        $kernelClass = KernelLifecycleManager::getKernelClass();
        /** @var Kernel $newTestKernel */
        $newTestKernel = new $kernelClass(
            'test',
            true,
            new StaticKernelPluginLoader(KernelLifecycleManager::getClassLoader()),
            Uuid::randomHex(),
            '2.2.2',
            $this->getContainer()->get(Connection::class)
        );

        $newTestKernel->boot();
        $cacheDir = $newTestKernel->getCacheDir();
        $newTestKernel->shutdown();

        return $cacheDir;
    }

    private function createUploadedFile(): UploadedFile
    {
        return new UploadedFile(self::PLUGIN_ZIP_FIXTURE_PATH, self::TEST_ZIP_NAME, null, null, true);
    }

    private function getPluginManagementService(): PluginManagementService
    {
        return new PluginManagementService(
            self::PLUGINS_PATH,
            new PluginZipDetector(),
            new PluginExtractor(self::PLUGINS_PATH, $this->filesystem),
            $this->getPluginService(),
            $this->filesystem,
            $this->getCacheClearer()
        );
    }

    private function getPluginService(): PluginService
    {
        return $this->createPluginService(
            $this->getContainer()->get('plugin.repository'),
            $this->getContainer()->get('language.repository'),
            $this->getContainer()->getParameter('kernel.project_dir'),
            $this->getContainer()->get(PluginFinder::class)
        );
    }

    private function getCacheClearer(): CacheClearer
    {
        return new CacheClearer(
            [],
            $this->getContainer()->get('cache_clearer'),
            $this->filesystem,
            $this->cacheDir,
            'test',
            $this->getContainer()->get(EntityCacheKeyGenerator::class),
            $this->getContainer()->get('messenger.bus.shopware')
        );
    }
}
