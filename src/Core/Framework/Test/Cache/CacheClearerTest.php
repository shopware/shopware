<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Cache;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Kernel;
use Shopware\Core\System\Tax\TaxEntity;

class CacheClearerTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    public function testCleanupOldKernelDirectories(): void
    {
        $classLoader = clone KernelLifecycleManager::getClassLoader();
        KernelLifecycleManager::getClassLoader()->unregister();
        $classLoader->register();

        $original = KernelLifecycleManager::getKernel();

        $oldCacheDirs = [];
        for ($i = 0; $i < 2; ++$i) {
            $class = KernelLifecycleManager::getKernelClass();
            /** @var \Shopware\Development\Kernel $kernel */
            $kernel = new $class(
                'test',
                true,
                new StaticKernelPluginLoader($classLoader),
                Uuid::randomHex(),
                '1.0.0@' . $i . '1eec7b5ea3f0fdbc95d0dd47f3c5bc275da8a33',
                $original->getContainer()->get(Connection::class)
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

        $clearer = $this->getContainer()->get(CacheClearer::class);
        $clearer->clear();

        foreach ($oldCacheDirs as $oldCacheDir) {
            static::assertFileNotExists($oldCacheDir);
        }
    }

    public function testInvalidateByTag(): void
    {
        /** @var EntityRepositoryInterface $repo */
        $repo = $this->getContainer()->get('tax.repository');
        $generator = $this->getContainer()->get(EntityCacheKeyGenerator::class);

        $id = Uuid::randomHex();
        $criteria = new Criteria([$id]);

        $data = $repo->search($criteria, Context::createDefaultContext());
        static::assertFalse($data->has($id));

        $key = $generator->getEntityContextCacheKey($id, $repo->getDefinition(), Context::createDefaultContext(), $criteria);

        /** @var CacheItemPoolInterface $objectCache */
        $objectCache = $this->getContainer()->get('cache.object');
        static::assertTrue($objectCache->hasItem($key));
        static::assertEquals($id, $objectCache->getItem($key)->get());

        $repo->create([
            ['id' => $id, 'name' => 'test tax', 'taxRate' => 1],
        ], Context::createDefaultContext());

        static::assertFalse($objectCache->hasItem($key));

        $data = $repo->search($criteria, Context::createDefaultContext());
        static::assertTrue($data->has($id));
        static::assertTrue($objectCache->hasItem($key));

        $cacheItem = $objectCache->getItem($key)->get();
        static::assertNotEquals($id, $cacheItem);
        static::assertInstanceOf(TaxEntity::class, $cacheItem);
    }
}
