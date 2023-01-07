<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Util;

use Composer\Autoload\ClassLoader;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Filesystem\MemoryFilesystemAdapter;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader;
use Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Core\Kernel;
use Shopware\Tests\Unit\Core\Framework\Plugin\_fixtures\ExampleBundle\ExampleBundle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Plugin\Util\AssetService
 */
class AssetServiceTest extends TestCase
{
    public function testCopyAssetsFromBundlePluginDoesNotExists(): void
    {
        $kernelMock = $this->createMock(KernelInterface::class);
        $kernelMock->expects(static::once())
            ->method('getBundle')
            ->with('bundleName')
            ->willThrowException(new \InvalidArgumentException());

        $assetService = new AssetService(
            new Filesystem(new MemoryFilesystemAdapter()),
            $kernelMock,
            new StaticKernelPluginLoader($this->createMock(ClassLoader::class)),
            $this->createMock(CacheInvalidator::class),
            $this->createMock(AbstractAppLoader::class),
            'coreDir',
            new ParameterBag()
        );

        static::expectException(PluginNotFoundException::class);
        $assetService->copyAssetsFromBundle('bundleName');
    }

    public function testCopyAssetsFromBundlePlugin(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel
            ->method('getBundle')
            ->with('ExampleBundle')
            ->willReturn($this->getBundle());

        $filesystem = new Filesystem(new MemoryFilesystemAdapter());
        $assetService = new AssetService(
            $filesystem,
            $kernel,
            new StaticKernelPluginLoader($this->createMock(ClassLoader::class)),
            $this->createMock(CacheInvalidator::class),
            $this->createMock(AbstractAppLoader::class),
            'coreDir',
            new ParameterBag()
        );

        $assetService->copyAssetsFromBundle('ExampleBundle');

        static::assertTrue($filesystem->has('bundles/example'));
        static::assertTrue($filesystem->has('bundles/example/test.txt'));
        static::assertSame('TEST', trim((string) $filesystem->read('bundles/example/test.txt')));
        static::assertTrue($filesystem->has('bundles/featurea'));
    }

    public function testCopyAssetsFromBundlePluginInactivePlugin(): void
    {
        $filesystem = new Filesystem(new MemoryFilesystemAdapter());

        $classLoader = $this->createMock(ClassLoader::class);
        $classLoader->method('findFile')->willReturn(__FILE__);
        $pluginLoader = new StaticKernelPluginLoader(
            $classLoader,
            null,
            [
                [
                    'name' => 'ExampleBundle',
                    'baseClass' => ExampleBundle::class,
                    'path' => __DIR__ . '/_fixtures/ExampleBundle',
                    'active' => true,
                    'managedByComposer' => false,
                    'autoload' => [
                        'psr-4' => [
                            'ExampleBundle' => '',
                        ],
                    ],
                ],
            ]
        );

        $pluginLoader->initializePlugins(__DIR__);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel
            ->method('getBundle')
            ->willThrowException(new \InvalidArgumentException('asd'));

        $assetService = new AssetService(
            $filesystem,
            $kernel,
            $pluginLoader,
            $this->createMock(CacheInvalidator::class),
            $this->createMock(AbstractAppLoader::class),
            'coreDir',
            new ParameterBag()
        );

        $assetService->copyAssetsFromBundle('Shopware\Tests\Unit\Core\Framework\Plugin\_fixtures\ExampleBundle\ExampleBundle');

        static::assertTrue($filesystem->has('bundles/example'));
        static::assertTrue($filesystem->has('bundles/example/test.txt'));
        static::assertSame('TEST', trim((string) $filesystem->read('bundles/example/test.txt')));
    }

    public function testBundleDeletion(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel
            ->method('getBundle')
            ->with('ExampleBundle')
            ->willReturn($this->getBundle());

        $filesystem = new Filesystem(new MemoryFilesystemAdapter());
        $assetService = new AssetService(
            $filesystem,
            $kernel,
            new StaticKernelPluginLoader($this->createMock(ClassLoader::class)),
            $this->createMock(CacheInvalidator::class),
            $this->createMock(AbstractAppLoader::class),
            'coreDir',
            new ParameterBag()
        );

        $filesystem->write('bundles/example/test.txt', 'TEST');
        $filesystem->write('bundles/featurea/test.txt', 'TEST');

        $assetService->removeAssetsOfBundle('ExampleBundle');

        static::assertFalse($filesystem->has('bundles/example'));
        static::assertFalse($filesystem->has('bundles/example/test.txt'));
        static::assertFalse($filesystem->has('bundles/featurea'));
    }

    public function testCopyRecoveryFiles(): void
    {
        $filesystem = new Filesystem(new MemoryFilesystemAdapter());
        $assetService = new AssetService(
            $filesystem,
            $this->createMock(KernelInterface::class),
            $this->createMock(KernelPluginLoader::class),
            $this->createMock(CacheInvalidator::class),
            $this->createMock(AbstractAppLoader::class),
            \dirname((string) (new \ReflectionClass(Kernel::class))->getFileName()),
            new ParameterBag()
        );

        $assetService->copyRecoveryAssets();

        static::assertTrue($filesystem->has('recovery/assets'));
    }

    public function testCopyAssetsWithoutApp(): void
    {
        $filesystem = new Filesystem(new MemoryFilesystemAdapter());
        $assetService = new AssetService(
            $filesystem,
            $this->createMock(KernelInterface::class),
            $this->createMock(KernelPluginLoader::class),
            $this->createMock(CacheInvalidator::class),
            $this->createMock(AbstractAppLoader::class),
            \dirname((string) (new \ReflectionClass(Kernel::class))->getFileName()),
            new ParameterBag()
        );

        $assetService->copyAssetsFromApp('TestApp', __DIR__ . '/foo');

        static::assertEmpty($filesystem->listContents('bundles')->toArray());
    }

    public function testCopyAssetsWithApp(): void
    {
        $filesystem = new Filesystem(new MemoryFilesystemAdapter());

        $appLoader = $this->createMock(AbstractAppLoader::class);
        $appLoader
            ->method('getAssetPathForAppPath')
            ->with(__DIR__ . '/_fixtures/ExampleBundle')
            ->willReturn(__DIR__ . '/../_fixtures/ExampleBundle/Resources/public');

        $assetService = new AssetService(
            $filesystem,
            $this->createMock(KernelInterface::class),
            $this->createMock(KernelPluginLoader::class),
            $this->createMock(CacheInvalidator::class),
            $appLoader,
            \dirname((string) (new \ReflectionClass(Kernel::class))->getFileName()),
            new ParameterBag()
        );

        $assetService->copyAssetsFromApp('ExampleBundle', __DIR__ . '/_fixtures/ExampleBundle');

        static::assertTrue($filesystem->has('bundles/example'));
        static::assertTrue($filesystem->has('bundles/example/test.txt'));
        static::assertSame('TEST', trim((string) $filesystem->read('bundles/example/test.txt')));
    }

    private function getBundle(): ExampleBundle
    {
        return new ExampleBundle(true, __DIR__ . '/_fixtures/ExampleBundle');
    }
}
