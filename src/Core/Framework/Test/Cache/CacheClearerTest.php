<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Cache;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\DevOps\StaticAnalyze\StaticAnalyzeKernel;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Kernel;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
#[Group('skip-paratest')]
#[Group('slow')]
class CacheClearerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testCleanupOldKernelDirectories(): void
    {
        $classLoader = clone KernelLifecycleManager::getClassLoader();
        KernelLifecycleManager::getClassLoader()->unregister();
        $classLoader->register();

        $original = KernelLifecycleManager::getKernel();

        $oldCacheDirs = [];
        for ($i = 0; $i < 2; ++$i) {
            $class = KernelLifecycleManager::getKernelClass();
            /** @var Kernel $kernel */
            $kernel = new $class(
                'test',
                true,
                new StaticKernelPluginLoader($classLoader),
                Uuid::randomHex(),
                '1.0.0@' . $i . '1eec7b5ea3f0fdbc95d0dd47f3c5bc275da8a33',
                $original->getContainer()->get(Connection::class),
                EnvironmentHelper::getVariable('PROJECT_ROOT')
            );

            $kernel->boot();
            $oldCacheDir = $kernel->getCacheDir();
            static::assertFileExists($oldCacheDir);
            $kernel->shutdown();
            $oldCacheDirs[] = $oldCacheDir;
        }
        $oldCacheDirs = array_unique($oldCacheDirs);

        static::assertCount(2, $oldCacheDirs);

        $second = KernelLifecycleManager::getKernel();
        $second->boot();
        static::assertFileExists($second->getCacheDir());

        static::assertNotContains($second->getCacheDir(), $oldCacheDirs);

        $this->getContainer()->get(CacheClearer::class)->clear();

        foreach ($oldCacheDirs as $oldCacheDir) {
            static::assertFileDoesNotExist($oldCacheDir);
        }
    }

    public function testClearContainerCache(): void
    {
        $previousKernelClass = KernelFactory::$kernelClass;

        // We need a new cache dir, therefore we reuse the StaticAnalyzeKernel class
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

        $finder = (new Finder())->in($cacheDir)->directories()->name('Container*');
        $containerCaches = [];

        foreach ($finder->getIterator() as $containerPaths) {
            $containerCaches[] = $containerPaths->getRealPath();
        }

        static::assertCount(1, $containerCaches);

        $filesystem = $this->getContainer()->get('filesystem');
        $cacheClearer = new CacheClearer(
            [],
            $this->getContainer()->get('cache_clearer'),
            $filesystem,
            $cacheDir,
            'test',
            false,
            $this->getContainer()->get('messenger.bus.shopware')
        );

        $cacheClearer->clearContainerCache();

        foreach ($containerCaches as $containerCache) {
            static::assertFileDoesNotExist($containerCache);
        }

        $filesystem->remove($cacheDir);
    }

    public function testUrlGeneratorCacheGetsCleared(): void
    {
        $cacheClearer = $this->getContainer()->get(CacheClearer::class);

        touch(sprintf('%s%sUrlGenerator.php', $this->getKernel()->getCacheDir(), \DIRECTORY_SEPARATOR));
        touch(sprintf('%s%sUrlGenerator.php.meta', $this->getKernel()->getCacheDir(), \DIRECTORY_SEPARATOR));

        $urlGeneratorCacheFileFinder = (new Finder())->in($this->getKernel()->getCacheDir())->files()->name('UrlGenerator.php*');

        static::assertCount(2, $urlGeneratorCacheFileFinder);

        $cacheClearer->clear();

        foreach ($urlGeneratorCacheFileFinder->getIterator() as $generatorFile) {
            static::assertFileDoesNotExist($generatorFile->getRealPath());
        }
    }

    public function testUrlGeneratorCacheGetsNotClearedInClusterMode(): void
    {
        $cacheClearer = new CacheClearer(
            [],
            $this->getContainer()->get('cache_clearer'),
            $this->getContainer()->get('filesystem'),
            $this->getKernel()->getCacheDir(),
            'test',
            true,
            $this->getContainer()->get('messenger.bus.shopware')
        );

        touch(sprintf('%s%sUrlGenerator.php', $this->getKernel()->getCacheDir(), \DIRECTORY_SEPARATOR));
        touch(sprintf('%s%sUrlGenerator.php.meta', $this->getKernel()->getCacheDir(), \DIRECTORY_SEPARATOR));

        $urlGeneratorCacheFileFinder = (new Finder())->in($this->getKernel()->getCacheDir())->files()->name('UrlGenerator.php*');

        static::assertCount(2, $urlGeneratorCacheFileFinder);

        $cacheClearer->clear();

        foreach ($urlGeneratorCacheFileFinder->getIterator() as $generatorFile) {
            static::assertFileExists($generatorFile->getRealPath());
        }
    }
}
