<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core;

use Doctrine\DBAL\Connection;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Kernel;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

/**
 * @internal
 */
#[CoversClass(Kernel::class)]
class KernelTest extends TestCase
{
    public function testGetCacheDir(): void
    {
        static::assertStringStartsWith('www/shopware/var/cache/fooBar_h', $this->createKernel()->getCacheDir());
    }

    public function testDumpContainerDumpsPreloadFile(): void
    {
        $fileSystem = new Filesystem(new InMemoryFilesystemAdapter());
        $kernel = $this->createKernel($fileSystem);

        $tmpDir = __DIR__ . '/tmpToBeRemoved';

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->setParameter('kernel.cache_dir', 'www/shopware/var/cache/fooBar_h123abc');
        $containerBuilder->compile();

        ReflectionHelper::getMethod(Kernel::class, 'dumpContainer')->invoke(
            $kernel,
            new ConfigCache($tmpDir . '/cache', true),
            $containerBuilder,
            'Shopware_Core_KernelDevDebugContainer',
            'Container',
        );

        static::assertTrue($fileSystem->fileExists('CACHEDIR.TAG'));
        static::assertTrue($fileSystem->fileExists('opcache-preload.php'));

        (new SymfonyFilesystem())->remove($tmpDir);
    }

    public function testDumpContainerDoesNotDumpPreloadFileIfWarmupCacheDirIsGiven(): void
    {
        $fileSystem = new Filesystem(new InMemoryFilesystemAdapter());
        $kernel = $this->createKernel($fileSystem);

        $tmpDir = __DIR__ . '/tmpToBeRemoved';

        $containerBuilder = new ContainerBuilder();
        // An underscore at the end indicates a warmup cache directory
        $containerBuilder->setParameter('kernel.cache_dir', 'www/shopware/var/cache/fooBar_h123abc_');
        $containerBuilder->compile();

        ReflectionHelper::getMethod(Kernel::class, 'dumpContainer')->invoke(
            $kernel,
            new ConfigCache($tmpDir . '/cache', true),
            $containerBuilder,
            'Shopware_Core_KernelDevDebugContainer',
            'Container',
        );

        static::assertTrue($fileSystem->fileExists('CACHEDIR.TAG'));
        // Do not create the preload file in warmup cache
        static::assertFalse($fileSystem->fileExists('opcache-preload.php'));

        (new SymfonyFilesystem())->remove($tmpDir);
    }

    private function createKernel(?FilesystemOperator $filesystem = null): Kernel
    {
        return new Kernel(
            'fooBar',
            true,
            $this->createMock(StaticKernelPluginLoader::class),
            'cacheId',
            '6.6.6',
            $this->createMock(Connection::class),
            'www/shopware',
            $filesystem,
        );
    }
}
