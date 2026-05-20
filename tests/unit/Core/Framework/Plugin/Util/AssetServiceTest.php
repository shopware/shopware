<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Util;

use Composer\Autoload\ClassLoader;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\Visibility;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Administration as ShopwareAdministration;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInput;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\WriteBatchInterface;
use Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Util\Filesystem as ThemeFilesystem;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\App\StaticSourceResolver;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;
use Shopware\Tests\Unit\Core\Framework\Plugin\_fixtures\ExampleBundle\ExampleBundle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
#[CoversClass(AssetService::class)]
class AssetServiceTest extends TestCase
{
    use EnvTestBehaviour;

    public function testCopyAssetsFromBundlePluginDoesNotExists(): void
    {
        $kernelMock = $this->createMock(KernelInterface::class);
        $kernelMock->expects($this->once())
            ->method('getBundle')
            ->with('bundleName')
            ->willThrowException(new \InvalidArgumentException());

        $assetService = $this->createAssetService($this->createFilesystem(), kernel: $kernelMock);

        $this->expectException(PluginNotFoundException::class);
        $assetService->copyAssetsFromBundle('bundleName');
    }

    public function testCopyAssetsFromBundlePlugin(): void
    {
        $filesystem = $this->createFilesystem();

        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects($this->exactly(2))->method('invalidate');

        $assetService = $this->createAssetService($filesystem, cacheInvalidator: $cacheInvalidator);

        $assetService->copyAssetsFromBundle('ExampleBundle');

        static::assertTrue($filesystem->has('bundles/example'));
        static::assertTrue($filesystem->has('bundles/example/test.txt'));
        static::assertSame('TEST', trim($filesystem->read('bundles/example/test.txt')));
        static::assertTrue($filesystem->has('bundles/featurea'));
    }

    public function testCopyAssetsFromBundlePluginWithoutInvalidation(): void
    {
        $this->setEnvVars(['SHOPWARE_SKIP_ASSET_INSTALL_CACHE_INVALIDATION' => '1']);

        $filesystem = $this->createFilesystem();

        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects($this->never())->method('invalidate');

        $assetService = $this->createAssetService($filesystem, cacheInvalidator: $cacheInvalidator);

        $assetService->copyAssetsFromBundle('ExampleBundle');

        static::assertTrue($filesystem->has('bundles/example'));
        static::assertTrue($filesystem->has('bundles/example/test.txt'));
        static::assertSame('TEST', trim($filesystem->read('bundles/example/test.txt')));
        static::assertTrue($filesystem->has('bundles/featurea'));
    }

    public function testCopyAssetsUsesTopLevelAssetFilesystemVisibility(): void
    {
        $adapter = new CapturingWriteBatchAdapter();
        $filesystem = new Filesystem($adapter);

        $assetService = $this->createAssetService(
            $filesystem,
            parameterBag: new ParameterBag([
                'shopware.filesystem.asset.type' => 's3',
                'shopware.filesystem.asset.visibility' => Visibility::PRIVATE,
                'shopware.filesystem.asset.config' => [
                    'visibility' => Visibility::PUBLIC,
                ],
            ])
        );

        $assetService->copyAssetsFromBundle('ExampleBundle');

        static::assertNotEmpty($adapter->visibilities);
        static::assertSame([Visibility::PRIVATE], array_values(array_unique($adapter->visibilities)));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testCopyAssetsUsesDeprecatedAssetFilesystemConfigVisibilityBeforeNextMajor(): void
    {
        $adapter = new CapturingWriteBatchAdapter();
        $filesystem = new Filesystem($adapter);

        $assetService = $this->createAssetService(
            $filesystem,
            parameterBag: new ParameterBag([
                'shopware.filesystem.asset.type' => 's3',
                'shopware.filesystem.asset.visibility' => Visibility::PUBLIC,
                'shopware.filesystem.asset.config' => [
                    'visibility' => Visibility::PRIVATE,
                ],
            ])
        );

        $assetService->copyAssetsFromBundle('ExampleBundle');

        static::assertNotEmpty($adapter->visibilities);
        static::assertSame([Visibility::PRIVATE], array_values(array_unique($adapter->visibilities)));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testCopyAssetsUsesTopLevelAssetFilesystemVisibilityWhenDeprecatedConfigVisibilityIsUnset(): void
    {
        $adapter = new CapturingWriteBatchAdapter();
        $filesystem = new Filesystem($adapter);

        $assetService = $this->createAssetService(
            $filesystem,
            parameterBag: new ParameterBag([
                'shopware.filesystem.asset.type' => 's3',
                'shopware.filesystem.asset.visibility' => Visibility::PRIVATE,
                'shopware.filesystem.asset.config' => [],
            ])
        );

        $assetService->copyAssetsFromBundle('ExampleBundle');

        static::assertNotEmpty($adapter->visibilities);
        static::assertSame([Visibility::PRIVATE], array_values(array_unique($adapter->visibilities)));
    }

    public function testCopyAssetsFallsBackToPublicAssetFilesystemVisibility(): void
    {
        $adapter = new CapturingWriteBatchAdapter();
        $filesystem = new Filesystem($adapter);

        $assetService = $this->createAssetService(
            $filesystem,
            parameterBag: new ParameterBag([
                'shopware.filesystem.asset.type' => 's3',
                'shopware.filesystem.asset.config' => [],
            ])
        );

        $assetService->copyAssetsFromBundle('ExampleBundle');

        static::assertNotEmpty($adapter->visibilities);
        static::assertSame([Visibility::PUBLIC], array_values(array_unique($adapter->visibilities)));
    }

    public function testCopyAssetsFromBundlePluginInactivePlugin(): void
    {
        $filesystem = $this->createFilesystem();

        $classLoader = $this->createMock(ClassLoader::class);
        $classLoader->method('findFile')->willReturn(__FILE__);
        $pluginLoader = new StaticKernelPluginLoader(
            $classLoader,
            null,
            [
                [
                    'name' => 'ExampleBundle',
                    'version' => '1.0.0',
                    'baseClass' => ExampleBundle::class,
                    'path' => __DIR__ . '/_fixtures/ExampleBundle',
                    'active' => true,
                    'managedByComposer' => false,
                    'composerName' => 'Swag\ExampleBundle',
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
            ->willThrowException(new \InvalidArgumentException('foo'));

        $assetService = $this->createAssetService($filesystem, kernel: $kernel, pluginLoader: $pluginLoader);

        $assetService->copyAssetsFromBundle(ExampleBundle::class);

        static::assertTrue($filesystem->has('bundles/example'));
        static::assertTrue($filesystem->has('bundles/example/test.txt'));
        static::assertSame('TEST', trim($filesystem->read('bundles/example/test.txt')));
    }

    public function testBundleDeletion(): void
    {
        $filesystem = $this->createFilesystem();
        $assetService = $this->createAssetService($filesystem);

        $filesystem->write('bundles/example/test.txt', 'TEST');
        $filesystem->write('bundles/featurea/test.txt', 'TEST');

        $assetService->removeAssetsOfBundle('ExampleBundle');

        static::assertFalse($filesystem->has('bundles/example'));
        static::assertFalse($filesystem->has('bundles/example/test.txt'));
        static::assertFalse($filesystem->has('bundles/featurea'));
    }

    public function testRemoveAssetsOfBundleThrowsException(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel
            ->method('getBundle')
            ->with('ExampleBundle')
            ->willThrowException(new \InvalidArgumentException());

        $assetFilesystem = $this->createMock(Filesystem::class);
        $assetFilesystem->expects($this->once())->method('deleteDirectory');

        $assetService = $this->createAssetService($assetFilesystem, $this->createFilesystem(), $kernel);

        $assetService->removeAssetsOfBundle('ExampleBundle');
    }

    public function testCopyAssetsClosesStreamItself(): void
    {
        $adapter = $this->createMock(FilesystemAdapter::class);
        $adapter->method('writeStream')
            ->willReturnCallback(static function (string $path, $stream) {
                static::assertIsResource($stream);
                // Some flysystem adapters automatically close the stream e.g. google adapter
                fclose($stream);

                return true;
            });
        $adapter->method('read')->willReturn(json_encode([], \JSON_THROW_ON_ERROR));

        $assetService = $this->createAssetService($this->createFilesystem());

        $assetService->copyAssetsFromBundle('ExampleBundle');
    }

    public function testCopyAssetsWithoutApp(): void
    {
        $filesystem = $this->createFilesystem();
        $assetService = $this->createAssetService(
            $filesystem,
            staticSourceResolver: new StaticSourceResolver([
                'TestApp' => new StaticFilesystem(),
            ]),
        );

        $assetService->copyAssetsFromApp('TestApp', __DIR__ . '/foo');

        static::assertEmpty($filesystem->listContents('bundles')->toArray());
    }

    public function testCopyAssetsWithApp(): void
    {
        $filesystem = $this->createFilesystem();
        $assetService = $this->createAssetService(
            $filesystem,
            staticSourceResolver: new StaticSourceResolver([
                'ExampleBundle' => new ThemeFilesystem(__DIR__ . '/../_fixtures/ExampleBundle'),
            ]),
        );

        $assetService->copyAssetsFromApp('ExampleBundle', __DIR__ . '/_fixtures/ExampleBundle');

        static::assertTrue($filesystem->has('bundles/example'));
        static::assertTrue($filesystem->has('bundles/example/test.txt'));
        static::assertSame('TEST', trim($filesystem->read('bundles/example/test.txt')));
    }

    /**
     * @return iterable<string, array{manifest: array<string, string>, expectedWrites: array<string, string>, expectedDeletes: array<string>}>
     */
    public static function adminFilesProvider(): iterable
    {
        yield 'destination-empty' => [
            'manifest' => [],
            'expectedWrites' => [
                'bundles/administration/static/js/app.js' => 'AdminBundle/Resources/public/static/js/app.js',
                'bundles/administration/one.js' => 'AdminBundle/Resources/public/one.js',
                'bundles/administration/two.js' => 'AdminBundle/Resources/public/two.js',
                'bundles/administration/three.js' => 'AdminBundle/Resources/public/three.js',
            ],
            'expectedDeletes' => [],
        ];
        yield 'destination-nothing-changed' => [
            'manifest' => [
                'static/js/app.js' => '9b88085012a490e232336863bf269917',
                'one.js' => '9b88085012a490e232336863bf269917',
                'two.js' => '9b88085012a490e232336863bf269917',
                'three.js' => '9b88085012a490e232336863bf269917',
            ],
            'expectedWrites' => [],
            'expectedDeletes' => [],
        ];
        yield 'destination-new-and-removed' => [
            'manifest' => [
                'static/js/app.js' => '9b88085012a490e232336863bf269917',
                'one.js' => '9b88085012a490e232336863bf269917',
                'two.js' => '9b88085012a490e232336863bf269917',
                'four.js' => '9b88085012a490e232336863bf269917',
            ],
            'expectedWrites' => [
                'bundles/administration/three.js' => 'AdminBundle/Resources/public/three.js',
            ],
            'expectedDeletes' => [
                'bundles/administration/four.js',
            ],
        ];
        yield 'destination-content-changed' => [
            'manifest' => [
                'static/js/app.js' => '9b88085012a490e232336863bf269917',
                'one.js' => 'xxx9b88085012a490e232336863bf269917', // incorrect hash to simulate content change
                'two.js' => 'xxx9b88085012a490e232336863bf269917', // incorrect hash to simulate content change
                'three.js' => '9b88085012a490e232336863bf269917',
            ],
            'expectedWrites' => [
                'bundles/administration/one.js' => 'AdminBundle/Resources/public/one.js',
                'bundles/administration/two.js' => 'AdminBundle/Resources/public/two.js',
            ],
            'expectedDeletes' => [],
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
        $kernel = $this->createMock(KernelInterface::class);
        $kernel
            ->method('getBundle')
            ->with('AdministrationBundle')
            ->willReturn(new Administration());

        $filesystem = $this->createMock(FilesystemOperator::class);
        $privateFilesystem = $this->createFilesystem();
        $assetService = $this->createAssetService($filesystem, $privateFilesystem, $kernel);

        $privateFilesystem->write('asset-manifest.json', json_encode(['administration' => $manifest], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT));

        $filesystem
            ->expects($this->exactly(\count($expectedWrites)))
            ->method('writeStream')
            ->willReturnCallback(static function (string $path, $stream) use ($expectedWrites) {
                static::assertIsResource($stream);
                $meta = stream_get_meta_data($stream);

                $local = $expectedWrites[$path];
                unset($expectedWrites[$path]);

                static::assertSame(Path::join(__DIR__, '/../_fixtures/', $local), $meta['uri'] ?? '');

                return true;
            });

        $filesystem
            ->expects($this->exactly(\count($expectedDeletes)))
            ->method('delete')
            ->with(static::callback(static function (string $path) use ($expectedDeletes) {
                return $path === array_pop($expectedDeletes);
            }));

        $expectedManifestFiles = [
            'one.js' => '9b88085012a490e232336863bf269917',
            'static/js/app.js' => '9b88085012a490e232336863bf269917',
            'three.js' => '9b88085012a490e232336863bf269917',
            'two.js' => '9b88085012a490e232336863bf269917',
        ];
        ksort($expectedManifestFiles);

        $assetService->copyAssetsFromBundle('AdministrationBundle');

        static::assertSame(
            json_encode(['administration' => $expectedManifestFiles], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT),
            $privateFilesystem->read('asset-manifest.json')
        );
    }

    public function testCopyDoesNotWriteManifestForLocalFilesystems(): void
    {
        $filesystem = $this->createFilesystem();

        $mockFs = $this->createMock(FilesystemOperator::class);
        $mockFs
            ->expects($this->never())
            ->method('write');

        $mockFs
            ->expects($this->never())
            ->method('read');

        $assetService = $this->createAssetService(
            $filesystem,
            $mockFs,
            staticSourceResolver: new StaticSourceResolver([
                'ExampleBundle' => new ThemeFilesystem(__DIR__ . '/../_fixtures/ExampleBundle'),
            ]),
            parameterBag: new ParameterBag([
                'shopware.filesystem.asset.type' => 'local',
                'shopware.filesystem.asset.visibility' => Visibility::PUBLIC,
                'shopware.filesystem.asset.config' => [],
            ])
        );

        $assetService->copyAssetsFromApp('ExampleBundle', __DIR__ . '/_fixtures/ExampleBundle');

        static::assertTrue($filesystem->has('bundles/example'));
        static::assertTrue($filesystem->has('bundles/example/test.txt'));
        static::assertSame('TEST', trim($filesystem->read('bundles/example/test.txt')));
    }

    public function testCopyPerformsFullCopyWithForceFlag(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel
            ->method('getBundle')
            ->with('AdministrationBundle')
            ->willReturn(new Administration());

        $filesystem = $this->createMock(FilesystemOperator::class);

        $filesystem
            ->expects($this->never())
            ->method('read');

        $expectedWrites = [
            'bundles/administration/static/js/app.js' => 'AdminBundle/Resources/public/static/js/app.js',
            'bundles/administration/one.js' => 'AdminBundle/Resources/public/one.js',
            'bundles/administration/two.js' => 'AdminBundle/Resources/public/two.js',
            'bundles/administration/three.js' => 'AdminBundle/Resources/public/three.js',
            'bundles/example/test.txt' => 'ExampleBundle/Resources/public/test.txt',
        ];

        $filesystem
            ->expects($this->exactly(\count($expectedWrites)))
            ->method('writeStream')
            ->willReturnCallback(static function (string $path, $stream) use ($expectedWrites) {
                static::assertIsResource($stream);
                $meta = stream_get_meta_data($stream);

                $local = $expectedWrites[$path];
                unset($expectedWrites[$path]);

                static::assertSame(
                    realpath(__DIR__ . '/../_fixtures/' . $local),
                    isset($meta['uri']) ? realpath($meta['uri']) : ''
                );

                return true;
            });

        $privateFilesystem = $this->createFilesystem();

        $assetService = $this->createAssetService(
            $filesystem,
            $privateFilesystem,
            $kernel,
            staticSourceResolver: new StaticSourceResolver([
                'ExampleBundle' => new ThemeFilesystem(__DIR__ . '/../_fixtures/ExampleBundle'),
            ])
        );

        $assetService->copyAssetsFromBundle('AdministrationBundle', true);
        $assetService->copyAssetsFromApp('ExampleBundle', __DIR__ . '/_fixtures/ExampleBundle', true);

        $expectedManifestFiles = [
            'administration' => [
                'one.js' => '9b88085012a490e232336863bf269917',
                'static/js/app.js' => '9b88085012a490e232336863bf269917',
                'three.js' => '9b88085012a490e232336863bf269917',
                'two.js' => '9b88085012a490e232336863bf269917',
            ],
            'examplebundle' => [
                'test.txt' => '9b88085012a490e232336863bf269917',
            ],
        ];

        ksort($expectedManifestFiles);

        static::assertSame(
            json_encode($expectedManifestFiles, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT),
            $privateFilesystem->read('asset-manifest.json')
        );
    }

    private function getBundle(): ExampleBundle
    {
        return new ExampleBundle(true, __DIR__ . '/_fixtures/ExampleBundle');
    }

    private function createAssetService(
        FilesystemOperator $assetFilesystem,
        ?FilesystemOperator $privateFilesystem = null,
        ?KernelInterface $kernel = null,
        ?CacheInvalidator $cacheInvalidator = null,
        ?KernelPluginLoader $pluginLoader = null,
        ?StaticSourceResolver $staticSourceResolver = null,
        ?ParameterBag $parameterBag = null,
    ): AssetService {
        if ($kernel === null) {
            $kernel = $this->createMock(KernelInterface::class);
            $kernel->method('getBundle')
                ->with('ExampleBundle')
                ->willReturn($this->getBundle());
        }

        return new AssetService(
            $assetFilesystem,
            $privateFilesystem ?? $assetFilesystem,
            $kernel,
            $pluginLoader ?? new StaticKernelPluginLoader($this->createMock(ClassLoader::class)),
            $cacheInvalidator ?? $this->createMock(CacheInvalidator::class),
            $staticSourceResolver ?? new StaticSourceResolver(),
            $parameterBag ?? new ParameterBag([
                'shopware.filesystem.asset.type' => 's3',
                'shopware.filesystem.asset.visibility' => Visibility::PUBLIC,
                'shopware.filesystem.asset.config' => [],
            ]),
            new EventDispatcher(),
        );
    }

    private function createFilesystem(): Filesystem
    {
        return new Filesystem(new InMemoryFilesystemAdapter());
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

/**
 * @internal
 */
class CapturingWriteBatchAdapter extends InMemoryFilesystemAdapter implements WriteBatchInterface
{
    /**
     * @var list<string>
     */
    public array $visibilities = [];

    public function writeBatch(CopyBatchInput ...$files): void
    {
        foreach ($files as $file) {
            $this->visibilities[] = $file->visibility;

            $sourceFile = $file->getSourceFile();
            $content = \is_string($sourceFile) ? file_get_contents($sourceFile) : stream_get_contents($sourceFile);

            \assert(\is_string($content));

            foreach ($file->getTargetFiles() as $targetFile) {
                $this->write($targetFile, $content, new Config());
            }
        }
    }
}
