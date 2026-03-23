<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInputFactory;
use Shopware\Storefront\Theme\MD5ThemePathBuilder;
use Shopware\Storefront\Theme\ScssPhpCompiler;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\ThemeCompiler;
use Shopware\Storefront\Theme\ThemeFileResolver;
use Shopware\Storefront\Theme\ThemeFilesystemResolver;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(ThemeCompiler::class)]
class ThemeCompilerImportMapTest extends TestCase
{
    public static ?FilesystemOperator $buildMetaFilesystemForFetch = null;

    private Filesystem $tempFilesystem;

    protected function setUp(): void
    {
        $this->tempFilesystem = new Filesystem(new InMemoryFilesystemAdapter());
    }

    public function testReadBundleComponentManifestReadsFromBundleAssetsDirectory(): void
    {
        $this->writeJson(
            'bundles/storefront/storefront/components/.vite/build-meta.json',
            [
                'manifest' => [
                    'Example/Component.ts' => ['file' => 'Example/Component-HASH.js', 'name' => 'Example/Component', 'isEntry' => true],
                    'Example/Component.scss' => ['file' => 'Example/Component-HASH.css', 'name' => 'Example/Component.scss', 'isEntry' => true],
                ],
                'vendorMap' => [],
            ]
        );

        $compiler = $this->createCompilerForBundleBuildMeta();
        $result = $this->callPrivate($compiler, 'readBundleComponentManifest', ['Storefront']);

        static::assertSame(
            [
                'Example:Component' => [
                    'bundle' => 'Storefront',
                    'js' => '/bundles/storefront/storefront/components/Example/Component-HASH.js',
                    'css' => ['/bundles/storefront/storefront/components/Example/Component-HASH.css'],
                ],
            ],
            $result
        );
    }

    public function testReadBundleBuildMetaContainsVendorMap(): void
    {
        $this->writeJson(
            'bundles/myextension/storefront/components/.vite/build-meta.json',
            ['manifest' => [], 'vendorMap' => ['@vendor/chunk' => 'vendor/chunk-HASH.js']]
        );

        $compiler = $this->createCompilerForBundleBuildMeta();
        $result = $this->callPrivate($compiler, 'readBundleBuildMeta', ['MyExtension']);

        static::assertIsArray($result);
        static::assertSame(['@vendor/chunk' => 'vendor/chunk-HASH.js'], $result['vendorMap']);
    }

    public function testReadBundleBuildMetaReturnsNullOnInvalidJson(): void
    {
        $path = 'bundles/brokenextension/storefront/components/.vite/build-meta.json';
        $this->tempFilesystem->createDirectory('bundles/brokenextension/storefront/components/.vite');
        $this->tempFilesystem->write($path, '{invalid json');

        $compiler = $this->createCompilerForBundleBuildMeta();
        $result = $this->callPrivate($compiler, 'readBundleBuildMeta', ['BrokenExtension']);

        static::assertNull($result);
    }

    public function testReadBundleBuildMetaFallsBackToEmptyArraysForInvalidStructure(): void
    {
        $this->writeJson(
            'bundles/invalidmeta/storefront/components/.vite/build-meta.json',
            ['manifest' => 'invalid', 'vendorMap' => 'invalid']
        );

        $compiler = $this->createCompilerForBundleBuildMeta();
        $result = $this->callPrivate($compiler, 'readBundleBuildMeta', ['InvalidMeta']);

        static::assertIsArray($result);
        static::assertSame([], $result['manifest']);
        static::assertSame([], $result['vendorMap']);
    }

    public function testReadBundleBuildMetaUsesCache(): void
    {
        $metaPath = 'bundles/cachedextension/storefront/components/.vite/build-meta.json';
        $this->writeJson(
            $metaPath,
            ['manifest' => [], 'vendorMap' => ['@cached/chunk' => 'vendor/chunk-one.js']]
        );

        $compiler = $this->createCompilerForBundleBuildMeta();
        $firstResult = $this->callPrivate($compiler, 'readBundleBuildMeta', ['CachedExtension']);

        $this->writeJson(
            $metaPath,
            ['manifest' => [], 'vendorMap' => ['@cached/chunk' => 'vendor/chunk-two.js']]
        );
        $secondResult = $this->callPrivate($compiler, 'readBundleBuildMeta', ['CachedExtension']);

        static::assertIsArray($firstResult);
        static::assertIsArray($secondResult);
        static::assertSame('vendor/chunk-one.js', $firstResult['vendorMap']['@cached/chunk']);
        static::assertSame('vendor/chunk-one.js', $secondResult['vendorMap']['@cached/chunk']);
    }

    public function testCollectComponentManifestEntriesCollectsOnlyProvidedBundles(): void
    {
        $this->writeJson(
            'bundles/storefront/storefront/components/.vite/build-meta.json',
            [
                'manifest' => ['Core/Button.ts' => ['file' => 'Core/Button-HASH.js', 'name' => 'Core/Button', 'isEntry' => true]],
                'vendorMap' => [],
            ]
        );
        $this->writeJson(
            'bundles/myextension/storefront/components/.vite/build-meta.json',
            [
                'manifest' => ['MyExtension/Card.ts' => ['file' => 'MyExtension/Card-HASH.js', 'name' => 'MyExtension/Card', 'isEntry' => true]],
                'vendorMap' => [],
            ]
        );

        $compiler = $this->createCompilerForBundleBuildMeta();
        $result = $this->callPrivate($compiler, 'collectComponentManifestEntries', [['Storefront', 'MyExtension']]);

        static::assertSame(
            [
                'Core:Button' => [
                    'bundle' => 'Storefront',
                    'js' => '/bundles/storefront/storefront/components/Core/Button-HASH.js',
                ],
                'MyExtension:Card' => [
                    'bundle' => 'MyExtension',
                    'js' => '/bundles/myextension/storefront/components/MyExtension/Card-HASH.js',
                ],
            ],
            $result
        );
    }

    public function testBuildComponentImportMapIgnoresBundlesOutsideConfigurationCollection(): void
    {
        $this->writeJson(
            'bundles/storefront/storefront/components/.vite/build-meta.json',
            [
                'manifest' => ['Core/Button.ts' => ['file' => 'Core/Button-HASH.js', 'name' => 'Core/Button', 'isEntry' => true]],
                'vendorMap' => [],
            ]
        );
        $this->writeJson(
            'bundles/inactiveapp/storefront/components/.vite/build-meta.json',
            [
                'manifest' => ['InactiveApp/Card.ts' => ['file' => 'InactiveApp/Card-HASH.js', 'name' => 'InactiveApp/Card', 'isEntry' => true]],
                'vendorMap' => [],
            ]
        );

        $collection = new StorefrontPluginConfigurationCollection([
            new StorefrontPluginConfiguration('Storefront'),
        ]);

        $compiler = $this->createCompilerForBundleBuildMeta();
        $result = $compiler->buildComponentImportMap($collection);

        static::assertIsArray($result);
        static::assertArrayHasKey('imports', $result);
        static::assertSame('https://cdn.example.com/bundles/storefront/storefront/shopware/shopware.js', $result['imports']['shopware']);
        static::assertArrayHasKey('Core:Button', $result['imports']);
        static::assertArrayNotHasKey('InactiveApp:Card', $result['imports']);
    }

    public function testReadBundleComponentManifestDeduplicatesCssAndSkipsInvalidEntries(): void
    {
        $this->writeJson(
            'bundles/myextension/storefront/components/.vite/build-meta.json',
            [
                'manifest' => [
                    'MyExtension/Card.ts' => [
                        'file' => 'MyExtension/Card-HASH.js',
                        'name' => 'MyExtension/Card',
                        'isEntry' => true,
                        'css' => ['MyExtension/Card-HASH.css', 'MyExtension/Card-HASH.css'],
                    ],
                    'MyExtension/Card.scss' => [
                        'file' => 'MyExtension/Card-HASH.css',
                        'name' => 'MyExtension/Card.scss',
                        'isEntry' => true,
                    ],
                    'MyExtension/NoName.ts' => [
                        'file' => 'MyExtension/NoName-HASH.js',
                        'isEntry' => true,
                    ],
                    'MyExtension/NotEntry.ts' => [
                        'file' => 'MyExtension/NotEntry-HASH.js',
                        'name' => 'MyExtension/NotEntry',
                        'isEntry' => false,
                    ],
                ],
                'vendorMap' => [],
            ]
        );

        $compiler = $this->createCompilerForBundleBuildMeta();
        $result = $this->callPrivate($compiler, 'readBundleComponentManifest', ['MyExtension']);

        static::assertIsArray($result);
        static::assertSame(
            [
                'MyExtension:Card' => [
                    'bundle' => 'MyExtension',
                    'js' => '/bundles/myextension/storefront/components/MyExtension/Card-HASH.js',
                    'css' => ['/bundles/myextension/storefront/components/MyExtension/Card-HASH.css'],
                ],
            ],
            $result
        );
    }

    public function testBuildComponentImportMapUsesPublicPackageBaseUrlAndCreatesScopesAndStyles(): void
    {
        $this->writeJson(
            'bundles/storefront/storefront/components/.vite/build-meta.json',
            [
                'manifest' => [
                    'Core/Button.ts' => [
                        'file' => 'Core/Button-HASH.js',
                        'name' => 'Core/Button',
                        'isEntry' => true,
                        'css' => ['Core/Button-HASH.css'],
                    ],
                    'Core/Button.scss' => [
                        'file' => 'Core/Button-HASH.css',
                        'name' => 'Core/Button.scss',
                        'isEntry' => true,
                    ],
                ],
                'vendorMap' => ['@core/vendor' => 'vendor/core-HASH.js'],
            ]
        );
        $this->writeJson(
            'bundles/myextension/storefront/components/.vite/build-meta.json',
            [
                'manifest' => [
                    'MyExtension/Card.ts' => [
                        'file' => 'MyExtension/Card-HASH.js',
                        'name' => 'MyExtension/Card',
                        'isEntry' => true,
                    ],
                ],
                'vendorMap' => ['@ext/vendor' => 'vendor/ext-HASH.js'],
            ]
        );

        $collection = new StorefrontPluginConfigurationCollection([
            new StorefrontPluginConfiguration('Storefront'),
            new StorefrontPluginConfiguration('MyExtension'),
        ]);

        $compiler = $this->createCompilerForBundleBuildMeta([
            'public' => new UrlPackage('https://cdn.example.com', new EmptyVersionStrategy()),
        ]);
        $result = $compiler->buildComponentImportMap($collection);

        static::assertIsArray($result);
        static::assertSame(
            'https://cdn.example.com/bundles/storefront/storefront/shopware/shopware.js',
            $result['imports']['shopware']
        );
        static::assertSame(
            'https://cdn.example.com/bundles/storefront/storefront/components/vendor/core-HASH.js',
            $result['imports']['@core/vendor']
        );
        static::assertSame(
            ['https://cdn.example.com/bundles/storefront/storefront/components/Core/Button-HASH.css'],
            $result['styles'] ?? []
        );
        static::assertSame(
            [
                'https://cdn.example.com/bundles/myextension/storefront/components/MyExtension/' => [
                    '@ext/vendor' => 'https://cdn.example.com/bundles/myextension/storefront/components/vendor/ext-HASH.js',
                ],
            ],
            $result['scopes'] ?? []
        );
    }

    public function testBuildComponentImportMapScopeKeyStripsPackageQueryString(): void
    {
        $this->writeJson(
            'bundles/myextension/storefront/components/.vite/build-meta.json',
            [
                'manifest' => [],
                'vendorMap' => ['@ext/vendor' => 'vendor/ext-HASH.js'],
            ]
        );

        $collection = new StorefrontPluginConfigurationCollection([
            new StorefrontPluginConfiguration('Storefront'),
            new StorefrontPluginConfiguration('MyExtension'),
        ]);

        $compiler = $this->createCompilerForBundleBuildMeta([
            'public' => new UrlPackage(
                'https://cdn.example.com/base',
                new class implements VersionStrategyInterface {
                    public function getVersion(string $path): string
                    {
                        return 'c159f3a5';
                    }

                    public function applyVersion(string $path): string
                    {
                        return $path . '?c159f3a5';
                    }
                },
            ),
        ]);
        $result = $compiler->buildComponentImportMap($collection);

        static::assertIsArray($result);
        static::assertSame(
            [
                'https://cdn.example.com/base/bundles/myextension/storefront/components/MyExtension/' => [
                    '@ext/vendor' => 'https://cdn.example.com/base/bundles/myextension/storefront/components/vendor/ext-HASH.js?c159f3a5',
                ],
            ],
            $result['scopes'] ?? []
        );
    }

    public function testBuildComponentImportMapPrefersAssetPackageBaseUrlOverPublicPackage(): void
    {
        $collection = new StorefrontPluginConfigurationCollection([
            new StorefrontPluginConfiguration('Storefront'),
        ]);

        $compiler = $this->createCompilerForBundleBuildMeta([
            'asset' => new UrlPackage('https://cdn.example.com/_assets/v/ae6dd181', new EmptyVersionStrategy()),
            'public' => new UrlPackage('https://cdn.example.com/F/K/J/2zrR0', new EmptyVersionStrategy()),
        ]);
        $result = $compiler->buildComponentImportMap($collection);

        static::assertIsArray($result);
        static::assertSame(
            'https://cdn.example.com/_assets/v/ae6dd181/bundles/storefront/storefront/shopware/shopware.js',
            $result['imports']['shopware']
        );
    }

    public function testBuildComponentImportMapUsesGlobalAssetForPluginsAndAssetForApps(): void
    {
        $this->writeJson(
            'bundles/myplugin/storefront/components/.vite/build-meta.json',
            [
                'manifest' => [
                    'MyPlugin/Card.ts' => [
                        'file' => 'MyPlugin/Card-HASH.js',
                        'name' => 'MyPlugin/Card',
                        'isEntry' => true,
                    ],
                ],
                'vendorMap' => [],
            ]
        );
        $this->writeJson(
            'bundles/myapp/storefront/components/.vite/build-meta.json',
            [
                'manifest' => [
                    'MyApp/Card.ts' => [
                        'file' => 'MyApp/Card-HASH.js',
                        'name' => 'MyApp/Card',
                        'isEntry' => true,
                    ],
                ],
                'vendorMap' => [],
            ]
        );

        $storefront = new StorefrontPluginConfiguration('Storefront');
        $plugin = new StorefrontPluginConfiguration('MyPlugin');
        $app = new StorefrontPluginConfiguration('MyApp');
        $app->addArrayExtension('saas_remote_app', ['isFresh' => true]);

        $collection = new StorefrontPluginConfigurationCollection([$storefront, $plugin, $app]);

        $compiler = $this->createCompilerForBundleBuildMeta([
            'global_asset' => new UrlPackage('https://global.cdn.example.com/_assets/v/123', new EmptyVersionStrategy()),
            'asset' => new UrlPackage('https://private.cdn.example.com/d/X/Y/Z', new EmptyVersionStrategy()),
        ]);
        $result = $compiler->buildComponentImportMap($collection);

        static::assertIsArray($result);
        static::assertSame(
            'https://global.cdn.example.com/_assets/v/123/bundles/storefront/storefront/shopware/shopware.js',
            $result['imports']['shopware']
        );
        static::assertSame(
            'https://global.cdn.example.com/_assets/v/123/bundles/myplugin/storefront/components/MyPlugin/Card-HASH.js',
            $result['imports']['MyPlugin:Card']
        );
        static::assertSame(
            'https://private.cdn.example.com/d/X/Y/Z/bundles/myapp/storefront/components/MyApp/Card-HASH.js',
            $result['imports']['MyApp:Card']
        );
    }

    public function testBuildComponentImportMapWithNullConfigurationCollectionReturnsOnlyImports(): void
    {
        $this->writeJson(
            'bundles/storefront/storefront/components/.vite/build-meta.json',
            [
                'manifest' => [],
                'vendorMap' => [],
            ]
        );

        $compiler = $this->createCompilerForBundleBuildMeta();
        $result = $compiler->buildComponentImportMap();

        static::assertSame(
            [
                'imports' => [
                    'shopware' => 'https://cdn.example.com/bundles/storefront/storefront/shopware/shopware.js',
                ],
            ],
            $result
        );
    }

    public function testReadBundleBuildMetaReturnsNullIfNoMatchingAssetPackageExists(): void
    {
        $compiler = $this->createCompilerForBundleBuildMeta([
            'theme' => new UrlPackage('https://cdn.example.com/theme', new EmptyVersionStrategy()),
        ]);

        $result = $this->callPrivate($compiler, 'readBundleBuildMeta', ['Storefront']);

        static::assertNull($result);
    }

    public function testReadBundleBuildMetaReturnsNullForEmptyBuildMetaContent(): void
    {
        $path = 'bundles/storefront/storefront/components/.vite/build-meta.json';
        $this->tempFilesystem->createDirectory('bundles/storefront/storefront/components/.vite');
        $this->tempFilesystem->write($path, '');

        $compiler = $this->createCompilerForBundleBuildMeta();
        $result = $this->callPrivate($compiler, 'readBundleBuildMeta', ['Storefront']);

        static::assertNull($result);
    }

    public function testReadBundleBuildMetaNormalizesScalarJsonToEmptyArrays(): void
    {
        $path = 'bundles/storefront/storefront/components/.vite/build-meta.json';
        $this->tempFilesystem->createDirectory('bundles/storefront/storefront/components/.vite');
        $this->tempFilesystem->write($path, '1');

        $compiler = $this->createCompilerForBundleBuildMeta();
        $result = $this->callPrivate($compiler, 'readBundleBuildMeta', ['Storefront']);

        static::assertSame(
            [
                'manifest' => [],
                'vendorMap' => [],
            ],
            $result
        );
    }

    public function testReadBundleComponentManifestReturnsNullWhenManifestIsEmpty(): void
    {
        $this->writeJson(
            'bundles/emptyextension/storefront/components/.vite/build-meta.json',
            [
                'manifest' => [],
                'vendorMap' => ['@vendor/chunk' => 'vendor/chunk-HASH.js'],
            ]
        );

        $compiler = $this->createCompilerForBundleBuildMeta();
        $result = $this->callPrivate($compiler, 'readBundleComponentManifest', ['EmptyExtension']);

        static::assertNull($result);
    }

    public function testGetAssetPackagesByKeyReturnsProvidedPackages(): void
    {
        $compiler = $this->createCompilerForBundleBuildMeta([
            'public' => new UrlPackage('https://cdn.example.com/public', new EmptyVersionStrategy()),
            'asset' => new UrlPackage('https://cdn.example.com/asset', new EmptyVersionStrategy()),
        ]);

        $result = $this->callPrivate($compiler, 'getAssetPackagesByKey');

        static::assertCount(2, $result);
        static::assertArrayHasKey('public', $result);
        static::assertArrayHasKey('asset', $result);
    }

    public function testReadBundleBuildMetaResolvesVersionedPackagePath(): void
    {
        $versionedMeta = [
            'manifest' => [
                '../../views/components/Sw/Custom/Test.js' => [
                    'file' => 'Sw/Custom/Test-HASH.js',
                    'name' => 'Sw/Custom/Test',
                    'isEntry' => true,
                ],
            ],
            'vendorMap' => [],
        ];

        $this->writeJson(
            '_assets/v/123/bundles/storefront/storefront/components/.vite/build-meta.json',
            $versionedMeta
        );

        $compiler = $this->createCompilerForBundleBuildMeta([
            'global_asset' => new UrlPackage('https://cdn.example.com/_assets/v/123', new EmptyVersionStrategy()),
        ]);
        $result = $this->callPrivate($compiler, 'readBundleBuildMeta', ['Storefront']);

        static::assertIsArray($result);
        static::assertSame($versionedMeta['manifest'], $result['manifest']);
        static::assertSame([], $result['vendorMap']);
    }

    /**
     * @param array<string, UrlPackage> $packages
     */
    private function createCompilerForBundleBuildMeta(array $packages = []): ThemeCompiler
    {
        $themePathBuilder = $this->createMock(MD5ThemePathBuilder::class);
        $themePathBuilder->method('assemblePath')->willReturn('theme-path');
        if ($packages === []) {
            $packages = [
                'public' => new UrlPackage('https://cdn.example.com', new EmptyVersionStrategy()),
            ];
        }
        self::$buildMetaFilesystemForFetch = $this->tempFilesystem;

        return new class($this->createMock(FilesystemOperator::class), $this->createMock(FilesystemOperator::class), new CopyBatchInputFactory(), $this->createMock(ThemeFileResolver::class), true, $this->createMock(EventDispatcherInterface::class), $this->createMock(ThemeFilesystemResolver::class), $packages, $this->createMock(CacheInvalidator::class), $this->createMock(LoggerInterface::class), $themePathBuilder, $this->createMock(ScssPhpCompiler::class), [], false, 'public') extends ThemeCompiler {
            protected function fetchPublicFile(string $url): string|false
            {
                $buildMetaFilesystem = ThemeCompilerImportMapTest::$buildMetaFilesystemForFetch;
                if (!$buildMetaFilesystem instanceof FilesystemOperator) {
                    return false;
                }

                $path = parse_url($url, \PHP_URL_PATH);
                if (!\is_string($path) || $path === '') {
                    return false;
                }

                $path = ltrim($path, '/');
                if ($path === '') {
                    return false;
                }

                try {
                    if (!$buildMetaFilesystem->fileExists($path)) {
                        $bundlesPos = strpos($path, 'bundles/');
                        if ($bundlesPos === false) {
                            return false;
                        }

                        $path = substr($path, $bundlesPos);
                        if ($path === '' || !$buildMetaFilesystem->fileExists($path)) {
                            return false;
                        }
                    }

                    return $buildMetaFilesystem->read($path);
                } catch (\Throwable) {
                    return false;
                }
            }
        };
    }

    /**
     * @param list<mixed> $args
     */
    private function callPrivate(object $object, string $method, array $args = []): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $args);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeJson(string $path, array $data): void
    {
        $directory = \dirname($path);
        if ($directory !== '.' && !$this->tempFilesystem->directoryExists($directory)) {
            $this->tempFilesystem->createDirectory($directory);
        }

        $this->tempFilesystem->write(
            $path,
            json_encode($data, \JSON_THROW_ON_ERROR),
        );
    }
}
