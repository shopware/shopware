<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;

/**
 * @internal
 */
#[Package('framework')]
#[Group('slow')]
class CacheDirectoryCleanupTest extends TestCase
{
    use KernelTestBehaviour;

    private Filesystem $filesystem;

    /**
     * @var list<string>
     */
    private array $createdDirectories = [];

    protected function setUp(): void
    {
        $this->filesystem = static::getContainer()->get(Filesystem::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdDirectories as $dir) {
            if ($this->filesystem->exists($dir)) {
                $this->filesystem->remove($dir);
            }
        }

        $this->createdDirectories = [];

        parent::tearDown();
    }

    #[TestDox('Cache clearer removes sibling cache directories with different kernel hashes')]
    public function testCacheClearerRemovesOtherHashDirectories(): void
    {
        $cacheDir = static::getContainer()->getParameter('kernel.cache_dir');
        static::assertIsString($cacheDir);

        $parentDir = \dirname($cacheDir);
        $environment = static::getContainer()->getParameter('kernel.environment');
        static::assertIsString($environment);

        // Create sibling directories simulating other hash variants (same environment)
        $oldHashDir1 = $parentDir . '/' . $environment . '_hOLDHASH123';
        $oldHashDir2 = $parentDir . '/' . $environment . '_hOLDHASH456';

        $this->createTestDirectory($oldHashDir1);
        $this->createTestDirectory($oldHashDir2);

        $cacheClearer = static::getContainer()->get('cache_clearer');
        static::assertInstanceOf(CacheClearerInterface::class, $cacheClearer);

        $cacheClearer->clear($cacheDir);

        static::assertDirectoryDoesNotExist($oldHashDir1);
        static::assertDirectoryDoesNotExist($oldHashDir2);
        static::assertDirectoryExists($cacheDir, 'Current cache directory should be preserved');
    }

    #[TestDox('Cache clearer preserves cache directories from other environments')]
    public function testCacheClearerPreservesDifferentEnvironmentDirectories(): void
    {
        $cacheDir = static::getContainer()->getParameter('kernel.cache_dir');
        static::assertIsString($cacheDir);

        $parentDir = \dirname($cacheDir);

        // Create directory for different environment (should NOT be deleted)
        $prodDir = $parentDir . '/prod_hSOMEHASH';
        $devDir = $parentDir . '/dev_hANOTHERHASH';

        $this->createTestDirectory($prodDir);
        $this->createTestDirectory($devDir);

        $cacheClearer = static::getContainer()->get('cache_clearer');
        static::assertInstanceOf(CacheClearerInterface::class, $cacheClearer);

        $cacheClearer->clear($cacheDir);

        static::assertDirectoryExists($prodDir);
        static::assertDirectoryExists($devDir);
    }

    #[TestDox('Cache clearer removes orphaned cache directories created by different plugin loaders')]
    public function testCacheClearerRemovesWebServerOrphanedContainerCache(): void
    {
        $cacheDir = static::getContainer()->getParameter('kernel.cache_dir');
        static::assertIsString($cacheDir);

        $parentDir = \dirname($cacheDir);
        $environment = static::getContainer()->getParameter('kernel.environment');
        static::assertIsString($environment);

        // Simulate orphaned cache from web server using different plugin loader
        $webServerCacheDir = $parentDir . '/' . $environment . '_hComposerLoaderHash';

        $this->filesystem->mkdir($webServerCacheDir);
        $this->createdDirectories[] = $webServerCacheDir;

        $this->filesystem->dumpFile(
            $webServerCacheDir . '/Shopware_Core_KernelTestDebugContainer.php',
            '<?php // Compiled container'
        );
        $this->filesystem->dumpFile(
            $webServerCacheDir . '/ContainerBuilder.php',
            '<?php // Container builder cache'
        );
        $this->filesystem->mkdir($webServerCacheDir . '/pools');
        $this->filesystem->touch($webServerCacheDir . '/pools/some_cache_item');

        $cacheClearer = static::getContainer()->get('cache_clearer');
        static::assertInstanceOf(CacheClearerInterface::class, $cacheClearer);

        $cacheClearer->clear($cacheDir);

        static::assertDirectoryDoesNotExist($webServerCacheDir);
    }

    private function createTestDirectory(string $path): void
    {
        $this->filesystem->mkdir($path);
        $this->filesystem->touch($path . '/Container.php');
        $this->createdDirectories[] = $path;
    }
}
