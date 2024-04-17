<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Util;

use Composer\Autoload\ClassLoader;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Administration as ShopwareAdministration;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Filesystem\MemoryFilesystemAdapter;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader;
use Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Tests\Unit\Core\Framework\Plugin\_fixtures\ExampleBundle\ExampleBundle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
#[CoversClass(AssetService::class)]
class AssetServiceTest extends TestCase
{
    public function testCopyAssetsFromBundlePluginDoesNotExists(): void
    {
        $kernelMock = $this->createMock(KernelInterface::class);
        $kernelMock->expects(static::once())
            ->method('getBundle')
            ->with('bundleName')
            ->willThrowException(new \InvalidArgumentException());

        $filesystem = new Filesystem(new MemoryFilesystemAdapter());
        $assetService = new AssetService(
            $filesystem,
            $filesystem,
            $kernelMock,
            new StaticKernelPluginLoader($this->createMock(ClassLoader::class)),
            $this->createMock(CacheInvalidator::class),
            $this->createMock(AbstractAppLoader::class),
            new ParameterBag(['shopware.filesystem.asset.type' => 's3'])
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
            $filesystem,
            $kernel,
            new StaticKernelPluginLoader($this->createMock(ClassLoader::class)),
            $this->createMock(CacheInvalidator::class),
            $this->createMock(AbstractAppLoader::class),
            new ParameterBag(['shopware.filesystem.asset.type' => 's3'])
        );

        $assetService->copyAssetsFromBundle('ExampleBundle');

        static::assertTrue($filesystem->has('bundles/example'));
        static::assertTrue($filesystem->has('bundles/example/test.txt'));
        static::assertSame('TEST', trim($filesystem->read('bundles/example/test.txt')));
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
            $filesystem,
            $kernel,
            $pluginLoader,
            $this->createMock(CacheInvalidator::class),
            $this->createMock(AbstractAppLoader::class),
            new ParameterBag(['shopware.filesystem.asset.type' => 's3'])
        );

        $assetService->copyAssetsFromBundle(ExampleBundle::class);

        static::assertTrue($filesystem->has('bundles/example'));
        static::assertTrue($filesystem->has('bundles/example/test.txt'));
        static::assertSame('TEST', trim($filesystem->read('bundles/example/test.txt')));
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
            $filesystem,
            $kernel,
            new StaticKernelPluginLoader($this->createMock(ClassLoader::class)),
            $this->createMock(CacheInvalidator::class),
            $this->createMock(AbstractAppLoader::class),
            new ParameterBag(['shopware.filesystem.asset.type' => 's3'])
        );

        $filesystem->write('bundles/example/test.txt', 'TEST');
        $filesystem->write('bundles/featurea/test.txt', 'TEST');

        $assetService->removeAssetsOfBundle('ExampleBundle');

        static::assertFalse($filesystem->has('bundles/example'));
        static::assertFalse($filesystem->has('bundles/example/test.txt'));
        static::assertFalse($filesystem->has('bundles/featurea'));
    }

    public function testCopyAssetsClosesStreamItself(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel
            ->method('getBundle')
            ->with('ExampleBundle')
            ->willReturn($this->getBundle());

        $adapter = $this->createMock(FilesystemAdapter::class);
        $adapter->method('writeStream')
            ->willReturnCallback(function (string $path, $stream) {
                static::assertIsResource($stream);
                // Some flysystem adapters automatically close the stream e.g. google adapter
                fclose($stream);

                return true;
            });

        $filesystem = new Filesystem($adapter);
        $assetService = new AssetService(
            $filesystem,
            $filesystem,
            $kernel,
            new StaticKernelPluginLoader($this->createMock(ClassLoader::class)),
            $this->createMock(CacheInvalidator::class),
            $this->createMock(AbstractAppLoader::class),
            new ParameterBag(['shopware.filesystem.asset.type' => 's3'])
        );

        $assetService->copyAssetsFromBundle('ExampleBundle');
    }

    public function testCopyAssetsWithoutApp(): void
    {
        $filesystem = new Filesystem(new MemoryFilesystemAdapter());
        $assetService = new AssetService(
            $filesystem,
            $filesystem,
            $this->createMock(KernelInterface::class),
            $this->createMock(KernelPluginLoader::class),
            $this->createMock(CacheInvalidator::class),
            $this->createMock(AbstractAppLoader::class),
            new ParameterBag(['shopware.filesystem.asset.type' => 's3'])
        );

        $assetService->copyAssetsFromApp('TestApp', __DIR__ . '/foo');

        static::assertEmpty($filesystem->listContents('bundles')->toArray());
    }

    public function testCopyAssetsWithApp(): void
    {
        $filesystem = new Filesystem(new MemoryFilesystemAdapter());

        $appLoader = $this->createMock(AbstractAppLoader::class);
        $appLoader
            ->method('locatePath')
            ->with(__DIR__ . '/_fixtures/ExampleBundle', 'Resources/public')
            ->willReturn(__DIR__ . '/../_fixtures/ExampleBundle/Resources/public');

        $assetService = new AssetService(
            $filesystem,
            $filesystem,
            $this->createMock(KernelInterface::class),
            $this->createMock(KernelPluginLoader::class),
            $this->createMock(CacheInvalidator::class),
            $appLoader,
            new ParameterBag(['shopware.filesystem.asset.type' => 's3'])
        );

        $assetService->copyAssetsFromApp('ExampleBundle', __DIR__ . '/_fixtures/ExampleBundle');

        static::assertTrue($filesystem->has('bundles/example'));
        static::assertTrue($filesystem->has('bundles/example/test.txt'));
        static::assertSame('TEST', trim($filesystem->read('bundles/example/test.txt')));
    }

    /**
     * @return array<string, array{manifest: array<string, string>, expectedWrites: array<string, string>, expectedDeletes: array<string>}>
     */
    public static function adminFilesProvider(): array
    {
        return [
            'destination-empty' => [
                'manifest' => [],
                'expectedWrites' => [
                    'bundles/administration/static/js/app.js' => 'AdminBundle/Resources/public/static/js/app.js',
                    'bundles/administration/one.js' => 'AdminBundle/Resources/public/one.js',
                    'bundles/administration/two.js' => 'AdminBundle/Resources/public/two.js',
                    'bundles/administration/three.js' => 'AdminBundle/Resources/public/three.js',
                ],
                'expectedDeletes' => [],
            ],
            'destination-nothing-changed' => [
                'manifest' => [
                    'static/js/app.js' => '13b896d551a100401b0d3982e0729efc2e8d7aeb09a36c0a51e48ec2bd15ea8b',
                    'one.js' => '13b896d551a100401b0d3982e0729efc2e8d7aeb09a36c0a51e48ec2bd15ea8b',
                    'two.js' => '13b896d551a100401b0d3982e0729efc2e8d7aeb09a36c0a51e48ec2bd15ea8b',
                    'three.js' => '13b896d551a100401b0d3982e0729efc2e8d7aeb09a36c0a51e48ec2bd15ea8b',
                ],
                'expectedWrites' => [],
                'expectedDeletes' => [],
            ],
            'destination-new-and-removed' => [
                'manifest' => [
                    'static/js/app.js' => '13b896d551a100401b0d3982e0729efc2e8d7aeb09a36c0a51e48ec2bd15ea8b',
                    'one.js' => '13b896d551a100401b0d3982e0729efc2e8d7aeb09a36c0a51e48ec2bd15ea8b',
                    'two.js' => '13b896d551a100401b0d3982e0729efc2e8d7aeb09a36c0a51e48ec2bd15ea8b',
                    'four.js' => '13b896d551a100401b0d3982e0729efc2e8d7aeb09a36c0a51e48ec2bd15ea8b',
                ],
                'expectedWrites' => [
                    'bundles/administration/three.js' => 'AdminBundle/Resources/public/three.js',
                ],
                'expectedDeletes' => [
                    'bundles/administration/four.js',
                ],
            ],
            'destination-content-changed' => [
                'manifest' => [
                    'static/js/app.js' => '13b896d551a100401b0d3982e0729efc2e8d7aeb09a36c0a51e48ec2bd15ea8b',
                    'one.js' => 'xxx13b896d551a100401b0d3982e0729efc2e8d7aeb09a36c0a51e48ec2bd15ea8b', // incorrect hash to simulate content change
                    'two.js' => 'xxx13b896d551a100401b0d3982e0729efc2e8d7aeb09a36c0a51e48ec2bd15ea8b', // incorrect hash to simulate content change
                    'three.js' => '13b896d551a100401b0d3982e0729efc2e8d7aeb09a36c0a51e48ec2bd15ea8b',
                ],
                'expectedWrites' => [
                    'bundles/administration/one.js' => 'AdminBundle/Resources/public/one.js',
                    'bundles/administration/two.js' => 'AdminBundle/Resources/public/two.js',
                ],
                'expectedDeletes' => [],
            ],
        ];
    }

    /**
     * @param array<string, string> $manifest
     * @param array<string, string> $expectedWrites
     * @param array<string> $expectedDeletes
     */
    #[DataProvider('adminFilesProvider')]
    public function testCopyAssetsFromAdminBundle(array $manifest, array $expectedWrites, array $expectedDeletes): void
    {
        ksort($manifest);
        $bundle = new Administration();

        $kernel = $this->createMock(KernelInterface::class);
        $kernel
            ->method('getBundle')
            ->with('AdministrationBundle')
            ->willReturn($bundle);

        $filesystem = $this->createMock(FilesystemOperator::class);
        $privateFilesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $assetService = new AssetService(
            $filesystem,
            $privateFilesystem,
            $kernel,
            new StaticKernelPluginLoader($this->createMock(ClassLoader::class)),
            $this->createMock(CacheInvalidator::class),
            $this->createMock(AbstractAppLoader::class),
            new ParameterBag(['shopware.filesystem.asset.type' => 's3'])
        );

        $privateFilesystem->write('asset-manifest.json', (string) json_encode(['administration' => $manifest], \JSON_PRETTY_PRINT));

        $filesystem
            ->expects(static::exactly(\count($expectedWrites)))
            ->method('writeStream')
            ->willReturnCallback(function (string $path, $stream) use ($expectedWrites) {
                static::assertIsResource($stream);
                $meta = stream_get_meta_data($stream);

                $local = $expectedWrites[$path];
                unset($expectedWrites[$path]);

                static::assertEquals(__DIR__ . '/../_fixtures/' . $local, $meta['uri']);

                return true;
            });

        $filesystem
            ->expects(static::exactly(\count($expectedDeletes)))
            ->method('delete')
            ->with(static::callback(function (string $path) use ($expectedDeletes) {
                return $path === array_pop($expectedDeletes);
            }));

        $expectedManifestFiles = [
            'one.js' => '13b896d551a100401b0d3982e0729efc2e8d7aeb09a36c0a51e48ec2bd15ea8b',
            'static/js/app.js' => '13b896d551a100401b0d3982e0729efc2e8d7aeb09a36c0a51e48ec2bd15ea8b',
            'three.js' => '13b896d551a100401b0d3982e0729efc2e8d7aeb09a36c0a51e48ec2bd15ea8b',
            'two.js' => '13b896d551a100401b0d3982e0729efc2e8d7aeb09a36c0a51e48ec2bd15ea8b',
        ];
        ksort($expectedManifestFiles);

        $assetService->copyAssetsFromBundle('AdministrationBundle');

        static::assertSame(
            json_encode(['administration' => $expectedManifestFiles], \JSON_PRETTY_PRINT),
            $privateFilesystem->read('asset-manifest.json')
        );
    }

    public function testCopyDoesNotWriteManifestForLocalFilesystems(): void
    {
        $filesystem = new Filesystem(new MemoryFilesystemAdapter());

        $appLoader = $this->createMock(AbstractAppLoader::class);
        $appLoader
            ->method('locatePath')
            ->with(__DIR__ . '/_fixtures/ExampleBundle', 'Resources/public')
            ->willReturn(__DIR__ . '/../_fixtures/ExampleBundle/Resources/public');

        $mockFs = $this->createMock(FilesystemOperator::class);
        $mockFs
            ->expects(static::never())
            ->method('write');

        $mockFs
            ->expects(static::never())
            ->method('read');

        $assetService = new AssetService(
            $filesystem,
            $mockFs,
            $this->createMock(KernelInterface::class),
            $this->createMock(KernelPluginLoader::class),
            $this->createMock(CacheInvalidator::class),
            $appLoader,
            new ParameterBag(['shopware.filesystem.asset.type' => 'local'])
        );

        $assetService->copyAssetsFromApp('ExampleBundle', __DIR__ . '/_fixtures/ExampleBundle');

        static::assertTrue($filesystem->has('bundles/example'));
        static::assertTrue($filesystem->has('bundles/example/test.txt'));
        static::assertSame('TEST', trim($filesystem->read('bundles/example/test.txt')));
    }

    public function testCopyPerformsFullCopyWithForceFlag(): void
    {
        $bundle = new Administration();

        $kernel = $this->createMock(KernelInterface::class);
        $kernel
            ->method('getBundle')
            ->with('AdministrationBundle')
            ->willReturn($bundle);

        $appLoader = $this->createMock(AbstractAppLoader::class);
        $appLoader
            ->method('locatePath')
            ->with(__DIR__ . '/_fixtures/ExampleBundle', 'Resources/public')
            ->willReturn(__DIR__ . '/../_fixtures/ExampleBundle/Resources/public');

        $filesystem = $this->createMock(FilesystemOperator::class);

        $filesystem
            ->expects(static::never())
            ->method('read');

        $expectedWrites = [
            'bundles/administration/static/js/app.js' => 'AdminBundle/Resources/public/static/js/app.js',
            'bundles/administration/one.js' => 'AdminBundle/Resources/public/one.js',
            'bundles/administration/two.js' => 'AdminBundle/Resources/public/two.js',
            'bundles/administration/three.js' => 'AdminBundle/Resources/public/three.js',
            'bundles/example/test.txt' => 'ExampleBundle/Resources/public/test.txt',
        ];

        $filesystem
            ->expects(static::exactly(\count($expectedWrites)))
            ->method('writeStream')
            ->willReturnCallback(function (string $path, $stream) use ($expectedWrites) {
                static::assertIsResource($stream);
                $meta = stream_get_meta_data($stream);

                $local = $expectedWrites[$path];
                unset($expectedWrites[$path]);

                static::assertEquals(__DIR__ . '/../_fixtures/' . $local, $meta['uri']);

                return true;
            });

        $privateFilesystem = new Filesystem(new InMemoryFilesystemAdapter());

        $assetService = new AssetService(
            $filesystem,
            $privateFilesystem,
            $kernel,
            $this->createMock(KernelPluginLoader::class),
            $this->createMock(CacheInvalidator::class),
            $appLoader,
            new ParameterBag(['shopware.filesystem.asset.type' => 's3'])
        );

        $assetService->copyAssetsFromBundle('AdministrationBundle', true);
        $assetService->copyAssetsFromApp('ExampleBundle', __DIR__ . '/_fixtures/ExampleBundle', true);

        $expectedManifestFiles = [
            'administration' => [
                'one.js' => '13b896d551a100401b0d3982e0729efc2e8d7aeb09a36c0a51e48ec2bd15ea8b',
                'static/js/app.js' => '13b896d551a100401b0d3982e0729efc2e8d7aeb09a36c0a51e48ec2bd15ea8b',
                'three.js' => '13b896d551a100401b0d3982e0729efc2e8d7aeb09a36c0a51e48ec2bd15ea8b',
                'two.js' => '13b896d551a100401b0d3982e0729efc2e8d7aeb09a36c0a51e48ec2bd15ea8b',
            ],
            'examplebundle' => [
                'test.txt' => '13b896d551a100401b0d3982e0729efc2e8d7aeb09a36c0a51e48ec2bd15ea8b',
            ],
        ];

        ksort($expectedManifestFiles);

        static::assertSame(
            json_encode($expectedManifestFiles, \JSON_PRETTY_PRINT),
            $privateFilesystem->read('asset-manifest.json')
        );
    }

    private function getBundle(): ExampleBundle
    {
        return new ExampleBundle(true, __DIR__ . '/_fixtures/ExampleBundle');
    }
}

/**
 * @internal
 */
class Administration extends ShopwareAdministration
{
    public function getPath(): string
    {
        return __DIR__ . '/../_fixtures/AdminBundle';
    }
}
