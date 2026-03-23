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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(ThemeCompiler::class)]
class ThemeCompilerImportMapTest extends TestCase
{
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
                'Core:Button' => ['js' => '/bundles/storefront/storefront/components/Core/Button-HASH.js'],
                'MyExtension:Card' => ['js' => '/bundles/myextension/storefront/components/MyExtension/Card-HASH.js'],
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
        static::assertSame('/bundles/storefront/storefront/shopware/shopware.js', $result['imports']['shopware']);
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

    /**
     * @param array<string, UrlPackage> $packages
     */
    private function createCompilerForBundleBuildMeta(array $packages = []): ThemeCompiler
    {
        $themePathBuilder = $this->createMock(MD5ThemePathBuilder::class);
        $themePathBuilder->method('assemblePath')->willReturn('theme-path');

        return new ThemeCompiler(
            $this->createMock(FilesystemOperator::class),
            $this->createMock(FilesystemOperator::class),
            $this->tempFilesystem,
            new CopyBatchInputFactory(),
            $this->createMock(ThemeFileResolver::class),
            true,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(ThemeFilesystemResolver::class),
            $packages,
            $this->createMock(CacheInvalidator::class),
            $this->createMock(LoggerInterface::class),
            $themePathBuilder,
            $this->createMock(ScssPhpCompiler::class),
            [],
            false,
            'public',
        );
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
