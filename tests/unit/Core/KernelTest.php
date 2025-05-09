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
    private string $tmpProjectDir;

    protected function setUp(): void
    {
        $this->tmpProjectDir = __DIR__ . '/tmpToBeRemoved';
    }

    protected function tearDown(): void
    {
        (new SymfonyFilesystem())->remove($this->tmpProjectDir);
    }

    public function testGetCacheDir(): void
    {
        static::assertStringStartsWith($this->tmpProjectDir . '/var/cache/fooBar_h', $this->createKernel()->getCacheDir());
    }

    public function testDumpContainerDumpsPreloadFile(): void
    {
        $fileSystem = new Filesystem(new InMemoryFilesystemAdapter());

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->setParameter('kernel.cache_dir', $this->tmpProjectDir . '/var/cache/fooBar_h123abc');
        $containerBuilder->compile();

        ReflectionHelper::getMethod(Kernel::class, 'dumpContainer')->invoke(
            $this->createKernel($fileSystem),
            new ConfigCache($this->tmpProjectDir . '/cache-file', true),
            $containerBuilder,
            'Shopware_Core_KernelDevDebugContainer',
            'Container',
        );

        static::assertTrue($fileSystem->fileExists('CACHEDIR.TAG'));
        static::assertTrue($fileSystem->fileExists('opcache-preload.php'));
    }

    public function testDumpContainerDumpsPreloadFileWithoutGivenFileSystem(): void
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->setParameter('kernel.cache_dir', $this->tmpProjectDir . '/var/cache/fooBar_h123abc');
        $containerBuilder->compile();

        ReflectionHelper::getMethod(Kernel::class, 'dumpContainer')->invoke(
            $this->createKernel(),
            new ConfigCache($this->tmpProjectDir . '/cache-file', true),
            $containerBuilder,
            'Shopware_Core_KernelDevDebugContainer',
            'Container',
        );

        $fileSystem = new SymfonyFilesystem();

        static::assertTrue($fileSystem->exists($this->tmpProjectDir . '/var/cache/CACHEDIR.TAG'));
        static::assertTrue($fileSystem->exists($this->tmpProjectDir . '/var/cache/opcache-preload.php'));
    }

    public function testDumpContainerDoesNotDumpPreloadFileIfWarmupCacheDirIsGiven(): void
    {
        $fileSystem = new Filesystem(new InMemoryFilesystemAdapter());

        $containerBuilder = new ContainerBuilder();
        // An underscore at the end indicates a warmup cache directory
        $containerBuilder->setParameter('kernel.cache_dir', $this->tmpProjectDir . '/var/cache/fooBar_h123abc_');
        $containerBuilder->compile();

        ReflectionHelper::getMethod(Kernel::class, 'dumpContainer')->invoke(
            $this->createKernel($fileSystem),
            new ConfigCache($this->tmpProjectDir . '/cache', true),
            $containerBuilder,
            'Shopware_Core_KernelDevDebugContainer',
            'Container',
        );

        static::assertTrue($fileSystem->fileExists('CACHEDIR.TAG'));

        // Do not create the preload file in warmup cache
        static::assertFalse($fileSystem->fileExists('opcache-preload.php'));
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
            $this->tmpProjectDir,
            $filesystem,
        );
    }
}
