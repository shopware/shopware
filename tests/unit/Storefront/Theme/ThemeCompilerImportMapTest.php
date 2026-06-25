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
use Shopware\Storefront\Theme\AbstractScssCompiler;
use Shopware\Storefront\Theme\AbstractThemePathBuilder;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\ThemeCompiler;
use Shopware\Storefront\Theme\ThemeFileResolver;
use Shopware\Storefront\Theme\ThemeFilesystemResolver;
use Symfony\Component\Asset\Package as AssetPackage;
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
    private Filesystem $assetFilesystem;

    private ThemeCompiler $compiler;

    protected function setUp(): void
    {
        $this->assetFilesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->compiler = $this->createCompilerForBundleBuildMeta();
    }

    public function testBuildComponentImportMapReadsFromBundleAssetsDirectory(): void
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

        $result = $this->assertImportMap($this->compiler->buildComponentImportMap());

        static::assertSame(
            'https://cdn.example.com/bundles/storefront/storefront/components/Example/Component-HASH.js',
            $result['imports']['Example:Component']
        );
        static::assertSame(
            ['https://cdn.example.com/bundles/storefront/storefront/components/Example/Component-HASH.css'],
            $result['styles'] ?? []
        );
    }

    public function testBuildComponentImportMapUsesBundleVendorMapForScopes(): void
    {
        $this->writeJson(
            'bundles/myextension/storefront/components/.vite/build-meta.json',
            ['manifest' => [], 'vendorMap' => ['@vendor/chunk' => 'vendor/chunk-HASH.js']]
        );
        $collection = new StorefrontPluginConfigurationCollection([
            new StorefrontPluginConfiguration('Storefront'),
            new StorefrontPluginConfiguration('MyExtension'),
        ]);

        $result = $this->assertImportMap($this->compiler->buildComponentImportMap($collection));

        static::assertIsArray($result);
        static::assertSame(
            [
                'https://cdn.example.com/bundles/myextension/storefront/components/MyExtension/' => [
                    '@vendor/chunk' => 'https://cdn.example.com/bundles/myextension/storefront/components/vendor/chunk-HASH.js',
                ],
            ],
            $result['scopes'] ?? []
        );
    }

    public function testBuildComponentImportMapSkipsBundleWhenBuildMetaJsonIsInvalid(): void
    {
        $path = 'bundles/brokenextension/storefront/components/.vite/build-meta.json';
        $this->assetFilesystem->createDirectory('bundles/brokenextension/storefront/components/.vite');
        $this->assetFilesystem->write($path, '{invalid json');
        $collection = new StorefrontPluginConfigurationCollection([
            new StorefrontPluginConfiguration('Storefront'),
            new StorefrontPluginConfiguration('BrokenExtension'),
        ]);

        $result = $this->assertImportMap($this->compiler->buildComponentImportMap($collection));

        static::assertIsArray($result);
        static::assertArrayNotHasKey('BrokenExtension:Card', $result['imports']);
        static::assertArrayNotHasKey('scopes', $result);
    }

    public function testBuildComponentImportMapFallsBackToEmptyArraysForInvalidBuildMetaStructure(): void
    {
        $this->writeJson(
            'bundles/invalidmeta/storefront/components/.vite/build-meta.json',
            ['manifest' => 'invalid', 'vendorMap' => 'invalid']
        );
        $collection = new StorefrontPluginConfigurationCollection([
            new StorefrontPluginConfiguration('Storefront'),
            new StorefrontPluginConfiguration('InvalidMeta'),
        ]);

        $result = $this->assertImportMap($this->compiler->buildComponentImportMap($collection));

        static::assertIsArray($result);
        static::assertArrayNotHasKey('InvalidMeta:Component', $result['imports']);
        static::assertArrayNotHasKey('scopes', $result);
    }

    public function testBuildComponentImportMapRefreshesBuildMetaBetweenCalls(): void
    {
        $metaPath = 'bundles/cachedextension/storefront/components/.vite/build-meta.json';
        $this->writeJson(
            $metaPath,
            ['manifest' => [], 'vendorMap' => ['@cached/chunk' => 'vendor/chunk-one.js']]
        );
        $collection = new StorefrontPluginConfigurationCollection([
            new StorefrontPluginConfiguration('Storefront'),
            new StorefrontPluginConfiguration('CachedExtension'),
        ]);

        $firstResult = $this->assertImportMap($this->compiler->buildComponentImportMap($collection));

        $this->writeJson(
            $metaPath,
            ['manifest' => [], 'vendorMap' => ['@cached/chunk' => 'vendor/chunk-two.js']]
        );
        $secondResult = $this->assertImportMap($this->compiler->buildComponentImportMap($collection));
        static::assertArrayHasKey('scopes', $firstResult);
        static::assertArrayHasKey('scopes', $secondResult);
        static::assertSame(
            'https://cdn.example.com/bundles/cachedextension/storefront/components/vendor/chunk-one.js',
            $firstResult['scopes']['https://cdn.example.com/bundles/cachedextension/storefront/components/CachedExtension/']['@cached/chunk']
        );
        static::assertSame(
            'https://cdn.example.com/bundles/cachedextension/storefront/components/vendor/chunk-two.js',
            $secondResult['scopes']['https://cdn.example.com/bundles/cachedextension/storefront/components/CachedExtension/']['@cached/chunk']
        );
    }

    public function testBuildComponentImportMapCollectsEntriesOnlyFromProvidedBundles(): void
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

        $collection = new StorefrontPluginConfigurationCollection([
            new StorefrontPluginConfiguration('Storefront'),
            new StorefrontPluginConfiguration('MyExtension'),
        ]);
        $result = $this->assertImportMap($this->compiler->buildComponentImportMap($collection));

        static::assertSame(
            'https://cdn.example.com/bundles/storefront/storefront/components/Core/Button-HASH.js',
            $result['imports']['Core:Button']
        );
        static::assertSame(
            'https://cdn.example.com/bundles/myextension/storefront/components/MyExtension/Card-HASH.js',
            $result['imports']['MyExtension:Card']
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

        $result = $this->assertImportMap($this->compiler->buildComponentImportMap($collection));

        static::assertIsArray($result);
        static::assertArrayHasKey('imports', $result);
        static::assertSame('https://cdn.example.com/bundles/storefront/storefront/shopware/shopware.js', $result['imports']['shopware']);
        static::assertArrayHasKey('Core:Button', $result['imports']);
        static::assertArrayNotHasKey('InactiveApp:Card', $result['imports']);
    }

    public function testBuildComponentImportMapDeduplicatesCssAndSkipsInvalidManifestEntries(): void
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

        $collection = new StorefrontPluginConfigurationCollection([
            new StorefrontPluginConfiguration('Storefront'),
            new StorefrontPluginConfiguration('MyExtension'),
        ]);
        $result = $this->compiler->buildComponentImportMap($collection);

        static::assertIsArray($result);
        static::assertSame(
            'https://cdn.example.com/bundles/myextension/storefront/components/MyExtension/Card-HASH.js',
            $result['imports']['MyExtension:Card']
        );
        static::assertSame(
            ['https://cdn.example.com/bundles/myextension/storefront/components/MyExtension/Card-HASH.css'],
            $result['styles'] ?? []
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

        $result = $this->compiler->buildComponentImportMap();

        static::assertSame(
            [
                'imports' => [
                    'shopware' => 'https://cdn.example.com/bundles/storefront/storefront/shopware/shopware.js',
                ],
            ],
            $result
        );
    }

    public function testBuildComponentImportMapFallsBackToRelativeShopwarePathWithoutMatchingPackage(): void
    {
        $compiler = $this->createCompilerForBundleBuildMeta([
            'theme' => new UrlPackage('https://cdn.example.com/theme', new EmptyVersionStrategy()),
        ]);

        static::assertSame(
            [
                'imports' => [
                    'shopware' => '/bundles/storefront/storefront/shopware/shopware.js',
                ],
            ],
            $compiler->buildComponentImportMap()
        );
    }

    public function testBuildComponentImportMapIgnoresEmptyBuildMetaContent(): void
    {
        $path = 'bundles/storefront/storefront/components/.vite/build-meta.json';
        $this->assetFilesystem->createDirectory('bundles/storefront/storefront/components/.vite');
        $this->assetFilesystem->write($path, '');

        static::assertSame(
            [
                'imports' => [
                    'shopware' => 'https://cdn.example.com/bundles/storefront/storefront/shopware/shopware.js',
                ],
            ],
            $this->compiler->buildComponentImportMap()
        );
    }

    public function testBuildComponentImportMapNormalizesScalarBuildMetaJsonToEmptyArrays(): void
    {
        $path = 'bundles/storefront/storefront/components/.vite/build-meta.json';
        $this->assetFilesystem->createDirectory('bundles/storefront/storefront/components/.vite');
        $this->assetFilesystem->write($path, '1');

        static::assertSame(
            [
                'imports' => [
                    'shopware' => 'https://cdn.example.com/bundles/storefront/storefront/shopware/shopware.js',
                ],
            ],
            $this->compiler->buildComponentImportMap()
        );
    }

    public function testBuildComponentImportMapSkipsComponentImportsWhenManifestIsEmpty(): void
    {
        $this->writeJson(
            'bundles/emptyextension/storefront/components/.vite/build-meta.json',
            [
                'manifest' => [],
                'vendorMap' => ['@vendor/chunk' => 'vendor/chunk-HASH.js'],
            ]
        );

        $collection = new StorefrontPluginConfigurationCollection([
            new StorefrontPluginConfiguration('Storefront'),
            new StorefrontPluginConfiguration('EmptyExtension'),
        ]);
        $result = $this->assertImportMap($this->compiler->buildComponentImportMap($collection));

        static::assertArrayNotHasKey('EmptyExtension:Card', $result['imports']);
        static::assertArrayHasKey('scopes', $result);
        static::assertSame(
            [
                'https://cdn.example.com/bundles/emptyextension/storefront/components/EmptyExtension/' => [
                    '@vendor/chunk' => 'https://cdn.example.com/bundles/emptyextension/storefront/components/vendor/chunk-HASH.js',
                ],
            ],
            $result['scopes'] ?? []
        );
    }

    public function testBuildComponentImportMapUsesProvidedAssetPackageKeys(): void
    {
        $compiler = $this->createCompilerForBundleBuildMeta([
            'public' => new UrlPackage('https://cdn.example.com/public', new EmptyVersionStrategy()),
            'asset' => new UrlPackage('https://cdn.example.com/asset', new EmptyVersionStrategy()),
        ]);
        $collection = new StorefrontPluginConfigurationCollection([
            new StorefrontPluginConfiguration('Storefront'),
        ]);

        $result = $this->assertImportMap($compiler->buildComponentImportMap($collection));
        static::assertSame(
            'https://cdn.example.com/asset/bundles/storefront/storefront/shopware/shopware.js',
            $result['imports']['shopware']
        );
    }

    public function testBuildComponentImportMapReadsUnversionedFilesystemPathAndEmitsVersionedPublicUrls(): void
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
            'bundles/storefront/storefront/components/.vite/build-meta.json',
            $versionedMeta
        );

        $compiler = $this->createCompilerForBundleBuildMeta([
            'global_asset' => new UrlPackage('https://cdn.example.com/_assets/v/123', new EmptyVersionStrategy()),
        ]);
        $result = $this->assertImportMap($compiler->buildComponentImportMap());
        static::assertSame(
            'https://cdn.example.com/_assets/v/123/bundles/storefront/storefront/components/Sw/Custom/Test-HASH.js',
            $result['imports']['Sw:Custom:Test']
        );
    }

    public function testBuildComponentImportMapFetchesBuildMetaFromGlobalAssetUrlWhenMissingOnFilesystem(): void
    {
        $collection = new StorefrontPluginConfigurationCollection([
            new StorefrontPluginConfiguration('Storefront'),
            new StorefrontPluginConfiguration('MyPlugin'),
        ]);

        $compiler = $this->createCompilerWithFetchPublicFileOverride(
            [
                'global_asset' => new UrlPackage('https://global.cdn.example.com/_assets/v/123', new EmptyVersionStrategy()),
            ],
            static function (string $url): string|false {
                if ($url !== 'https://global.cdn.example.com/_assets/v/123/bundles/myplugin/storefront/components/.vite/build-meta.json') {
                    return false;
                }

                return json_encode([
                    'manifest' => [
                        'MyPlugin/Card.ts' => [
                            'file' => 'MyPlugin/Card-HTTP.js',
                            'name' => 'MyPlugin/Card',
                            'isEntry' => true,
                        ],
                    ],
                    'vendorMap' => [],
                ], \JSON_THROW_ON_ERROR);
            },
        );

        $result = $this->assertImportMap($compiler->buildComponentImportMap($collection));

        static::assertSame(
            'https://global.cdn.example.com/_assets/v/123/bundles/myplugin/storefront/components/MyPlugin/Card-HTTP.js',
            $result['imports']['MyPlugin:Card']
        );
    }

    public function testBuildComponentImportMapDoesNotFetchPublicUrlForNonGlobalAssetPackages(): void
    {
        $collection = new StorefrontPluginConfigurationCollection([
            new StorefrontPluginConfiguration('Storefront'),
            new StorefrontPluginConfiguration('MyExtension'),
        ]);

        $compiler = $this->createCompilerWithFetchPublicFileOverride(
            [
                'asset' => new UrlPackage('https://cdn.example.com/_assets/v/ae6dd181', new EmptyVersionStrategy()),
                'public' => new UrlPackage('https://cdn.example.com/public', new EmptyVersionStrategy()),
            ],
            static function (string $url): string|false {
                throw new \RuntimeException('fetchPublicFile must not be called for non-global_asset packages: ' . $url);
            },
        );

        $result = $this->assertImportMap($compiler->buildComponentImportMap($collection));

        static::assertSame(
            'https://cdn.example.com/_assets/v/ae6dd181/bundles/storefront/storefront/shopware/shopware.js',
            $result['imports']['shopware']
        );
        static::assertArrayNotHasKey('MyExtension:Card', $result['imports']);
        static::assertArrayNotHasKey('scopes', $result);
    }

    public function testBuildComponentImportMapPrefersAssetFilesystemOverGlobalAssetUrl(): void
    {
        $this->writeJson(
            'bundles/myplugin/storefront/components/.vite/build-meta.json',
            [
                'manifest' => [
                    'MyPlugin/Card.ts' => [
                        'file' => 'MyPlugin/Card-FROM-FS.js',
                        'name' => 'MyPlugin/Card',
                        'isEntry' => true,
                    ],
                ],
                'vendorMap' => [],
            ]
        );

        $collection = new StorefrontPluginConfigurationCollection([
            new StorefrontPluginConfiguration('Storefront'),
            new StorefrontPluginConfiguration('MyPlugin'),
        ]);

        $compiler = $this->createCompilerWithFetchPublicFileOverride(
            [
                'global_asset' => new UrlPackage('https://global.cdn.example.com/_assets/v/123', new EmptyVersionStrategy()),
            ],
            static function (string $url): string|false {
                if ($url !== 'https://global.cdn.example.com/_assets/v/123/bundles/myplugin/storefront/components/.vite/build-meta.json') {
                    return false;
                }

                return json_encode([
                    'manifest' => [
                        'MyPlugin/Card.ts' => [
                            'file' => 'MyPlugin/Card-FROM-HTTP.js',
                            'name' => 'MyPlugin/Card',
                            'isEntry' => true,
                        ],
                    ],
                    'vendorMap' => [],
                ], \JSON_THROW_ON_ERROR);
            },
        );

        $result = $this->assertImportMap($compiler->buildComponentImportMap($collection));

        static::assertSame(
            'https://global.cdn.example.com/_assets/v/123/bundles/myplugin/storefront/components/MyPlugin/Card-FROM-FS.js',
            $result['imports']['MyPlugin:Card']
        );
    }

    /**
     * @param array{imports: array<string, string>, scopes?: array<string, array<string, string>>, styles?: list<string>}|null $result
     *
     * @return array{imports: array<string, string>, scopes?: array<string, array<string, string>>, styles?: list<string>}
     */
    private function assertImportMap(?array $result): array
    {
        static::assertIsArray($result);
        static::assertArrayHasKey('imports', $result);

        return $result;
    }

    /**
     * @param array<string, UrlPackage> $packages
     */
    private function createCompilerForBundleBuildMeta(array $packages = []): ThemeCompiler
    {
        return $this->createCompilerWithFetchPublicFileOverride(
            $packages,
            static fn (string $_url): false => false,
        );
    }

    /**
     * @param array<string, UrlPackage> $packages
     * @param \Closure(string): (string|false) $fetchPublicFile
     */
    private function createCompilerWithFetchPublicFileOverride(array $packages, \Closure $fetchPublicFile): ThemeCompiler
    {
        $themePathBuilder = $this->createMock(AbstractThemePathBuilder::class);
        $themePathBuilder->method('assemblePath')->willReturn('theme-path');
        if ($packages === []) {
            $packages = [
                'public' => new UrlPackage('https://cdn.example.com', new EmptyVersionStrategy()),
            ];
        }

        $assetFilesystem = $this->assetFilesystem;

        return new class($this->createMock(FilesystemOperator::class), $this->createMock(FilesystemOperator::class), $assetFilesystem, new CopyBatchInputFactory(), $this->createMock(ThemeFileResolver::class), true, $this->createMock(EventDispatcherInterface::class), $this->createMock(ThemeFilesystemResolver::class), $packages, $this->createMock(CacheInvalidator::class), $this->createMock(LoggerInterface::class), $themePathBuilder, $this->createMock(AbstractScssCompiler::class), [], false, 'public', $fetchPublicFile) extends ThemeCompiler {
            /**
             * @var \Closure(string): (string|false)
             */
            private readonly \Closure $fetchPublicFileCallback;

            /**
             * @param array<string, AssetPackage> $packages
             * @param \Closure(string): (string|false) $fetchPublicFileCallback
             */
            public function __construct(
                FilesystemOperator $filesystem,
                FilesystemOperator $tempFilesystem,
                FilesystemOperator $assetFilesystem,
                CopyBatchInputFactory $copyBatchInputFactory,
                ThemeFileResolver $themeFileResolver,
                bool $debug,
                EventDispatcherInterface $eventDispatcher,
                ThemeFilesystemResolver $themeFilesystemResolver,
                array $packages,
                CacheInvalidator $cacheInvalidator,
                LoggerInterface $logger,
                AbstractThemePathBuilder $themePathBuilder,
                AbstractScssCompiler $scssCompiler,
                array $customAllowedRegex,
                bool $validate,
                string $visibility,
                \Closure $fetchPublicFileCallback,
            ) {
                $this->fetchPublicFileCallback = $fetchPublicFileCallback;

                parent::__construct(
                    $filesystem,
                    $tempFilesystem,
                    $assetFilesystem,
                    $copyBatchInputFactory,
                    $themeFileResolver,
                    $debug,
                    $eventDispatcher,
                    $themeFilesystemResolver,
                    $packages,
                    $cacheInvalidator,
                    $logger,
                    $themePathBuilder,
                    $scssCompiler,
                    $customAllowedRegex,
                    $validate,
                    $visibility,
                );
            }

            protected function fetchPublicFile(string $url): string|false
            {
                return ($this->fetchPublicFileCallback)($url);
            }
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeJson(string $path, array $data): void
    {
        $directory = \dirname($path);
        if ($directory !== '.' && !$this->assetFilesystem->directoryExists($directory)) {
            $this->assetFilesystem->createDirectory($directory);
        }

        $this->assetFilesystem->write(
            $path,
            json_encode($data, \JSON_THROW_ON_ERROR),
        );
    }
}
