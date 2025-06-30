<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Kernel;
use Shopware\Core\Test\Stub\App\StaticSourceResolver;
use Shopware\Storefront\Theme\Exception\ThemeException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\ThemeFileResolver;
use Shopware\Storefront\Theme\ThemeFilesystemResolver;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\CircularReference\ThemeLevel1\ThemeLevel1;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\CircularReference\ThemeLevel2\ThemeLevel2;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\MockStorefront\MockStorefront;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\SimplePlugin\SimplePlugin;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\SimpleThemeWithResources\SimpleThemeWithResources;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeNotIncludingPluginJsAndCss\ThemeNotIncludingPluginJsAndCss;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeWithMultiInheritance\ThemeWithMultiInheritance;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeWithMultiInheritanceCircularReference\ThemeWithMultiInheritanceCircularReference;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeWithMultiInheritanceExplicitFiles\ThemeWithMultiInheritanceExplicitFiles;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeWithStorefrontBootstrapScss\ThemeWithStorefrontBootstrapScss;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeWithStorefrontSkinScss\ThemeWithStorefrontSkinScss;

/**
 * @internal
 */
#[CoversClass(ThemeFileResolver::class)]
class ThemeFileResolverTest extends TestCase
{
    public function testResolvedFilesIncludeSkinScssPath(): void
    {
        $themePluginBundle = new ThemeWithStorefrontSkinScss();
        $storefrontBundle = new MockStorefront();

        $sourceResolver = new StaticSourceResolver([]);

        $factory = new StorefrontPluginConfigurationFactory(
            $this->createMock(KernelPluginLoader::class),
            $sourceResolver
        );

        $config = $factory->createFromBundle($themePluginBundle);
        $storefront = $factory->createFromBundle($storefrontBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($storefront);

        $kernel = $this->createMock(Kernel::class);

        $kernel->expects($this->any())->method('getBundles')->willReturn([
            'ThemeWithStorefrontSkinScss' => $themePluginBundle,
            'MockStorefront' => $storefrontBundle,
        ]);

        $kernel->expects($this->any())->method('getBundle')->willReturnMap([
            ['ThemeWithStorefrontSkinScss', $themePluginBundle],
            ['MockStorefront', $storefrontBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $sourceResolver,
            $kernel
        );

        $resolvedFiles = (new ThemeFileResolver($themeFilesystemResolver))->resolveFiles(
            $config,
            $configCollection,
            false
        );

        $actual = json_encode($resolvedFiles, \JSON_PRETTY_PRINT);
        $expected = '/Resources\/app\/storefront\/src\/scss\/skin\/shopware\/_base.scss';

        static::assertStringContainsString($expected, (string) $actual);
    }

    public function testResolvedFilesDoNotIncludeSkinScssPath(): void
    {
        $themePluginBundle = new ThemeWithStorefrontBootstrapScss();
        $storefrontBundle = new MockStorefront();

        $sourceResolver = new StaticSourceResolver([]);
        $factory = new StorefrontPluginConfigurationFactory(
            $this->createMock(KernelPluginLoader::class),
            $sourceResolver
        );

        $config = $factory->createFromBundle($themePluginBundle);
        $storefront = $factory->createFromBundle($storefrontBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($storefront);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->any())->method('getBundles')->willReturn([
            'ThemeWithStorefrontBootstrapScss' => $themePluginBundle,
            'MockStorefront' => $storefrontBundle,
        ]);

        $kernel->expects($this->any())->method('getBundle')->willReturnMap([
            ['ThemeWithStorefrontBootstrapScss', $themePluginBundle],
            ['MockStorefront', $storefrontBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $sourceResolver,
            $kernel
        );

        $resolvedFiles = (new ThemeFileResolver($themeFilesystemResolver))->resolveFiles(
            $config,
            $configCollection,
            false
        );

        $actual = json_encode($resolvedFiles, \JSON_PRETTY_PRINT);
        $notExpected = '/Resources\/app\/storefront\/src\/scss\/skin\/shopware\/_base.scss';

        static::assertStringNotContainsString($notExpected, (string) $actual);
    }

    public function testResolvedFilesDontContainDuplicates(): void
    {
        $themePluginBundle = new ThemeWithMultiInheritance(true, __DIR__ . '/fixtures/SimplePlugin');
        $storefrontBundle = new MockStorefront();
        $pluginBundle = new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin');

        $sourceResolver = new StaticSourceResolver([]);
        $factory = new StorefrontPluginConfigurationFactory(
            $this->createMock(KernelPluginLoader::class),
            $sourceResolver
        );

        $config = $factory->createFromBundle($themePluginBundle);
        $storefront = $factory->createFromBundle($storefrontBundle);
        $plugin = $factory->createFromBundle($pluginBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($storefront);
        $configCollection->add($plugin);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->once())->method('getBundles')->willReturn([
            'ThemeWithMultiInheritance' => $themePluginBundle,
            'MockStorefront' => $storefrontBundle,
            'SimplePlugin' => $pluginBundle,
        ]);

        $kernel->expects($this->any())->method('getBundle')->willReturnMap([
            ['ThemeWithMultiInheritance', $themePluginBundle],
            ['MockStorefront', $storefrontBundle],
            ['SimplePlugin', $pluginBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $sourceResolver,
            $kernel
        );

        $resolvedFiles = (new ThemeFileResolver($themeFilesystemResolver))->resolveFiles(
            $config,
            $configCollection,
            false
        );
        $scriptFiles = $resolvedFiles['script'];
        $actual = $scriptFiles->getFilepaths();
        $expected = array_unique($scriptFiles->getFilepaths());

        static::assertSame($expected, $actual);

        $styleFiles = $resolvedFiles['style'];
        $actualStyle = $styleFiles->getFilepaths();
        $expectedStyle = array_unique($styleFiles->getFilepaths());

        static::assertSame($expectedStyle, $actualStyle);
    }

    public function testResolvedFilesThrowExceptionOnCircularReference(): void
    {
        $themePluginBundle = new ThemeWithMultiInheritanceCircularReference(true, __DIR__ . '/fixtures/SimplePlugin');
        $storefrontBundle = new MockStorefront();
        $pluginBundle = new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin');

        $sourceResolver = new StaticSourceResolver([]);
        $factory = new StorefrontPluginConfigurationFactory(
            $this->createMock(KernelPluginLoader::class),
            $sourceResolver
        );

        $config = $factory->createFromBundle($themePluginBundle);
        $storefront = $factory->createFromBundle($storefrontBundle);
        $plugin = $factory->createFromBundle($pluginBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($storefront);
        $configCollection->add($plugin);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->once())->method('getBundles')->willReturn([
            'ThemeWithMultiInheritanceCircularReference' => $themePluginBundle,
            'MockStorefront' => $storefrontBundle,
            'SimplePlugin' => $pluginBundle,
        ]);

        $kernel->expects($this->any())->method('getBundle')->willReturnMap([
            ['ThemeWithMultiInheritanceCircularReference', $themePluginBundle],
            ['MockStorefront', $storefrontBundle],
            ['SimplePlugin', $pluginBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $sourceResolver,
            $kernel
        );
        $this->expectException(ThemeException::class);
        $this->expectExceptionMessageMatches('/Circular dependency detected for theme.*/');

        $resolvedFiles = (new ThemeFileResolver($themeFilesystemResolver))->resolveFiles(
            $config,
            $configCollection,
            false
        );
    }

    public function testResolvedFilesThrowExceptionOnCircularReferenceInMoreDepth(): void
    {
        $theme1PluginBundle = new ThemeLevel1(true, __DIR__ . '/fixtures/SimplePlugin');
        $theme2PluginBundle = new ThemeLevel2(true, __DIR__ . '/fixtures/SimplePlugin');
        $storefrontBundle = new MockStorefront();
        $pluginBundle = new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin');

        $sourceResolver = new StaticSourceResolver([]);
        $factory = new StorefrontPluginConfigurationFactory(
            $this->createMock(KernelPluginLoader::class),
            $sourceResolver
        );

        $themeConfig1 = $factory->createFromBundle($theme1PluginBundle);
        $themeConfig2 = $factory->createFromBundle($theme2PluginBundle);
        $storefront = $factory->createFromBundle($storefrontBundle);
        $plugin = $factory->createFromBundle($pluginBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($themeConfig1);
        $configCollection->add($themeConfig2);
        $configCollection->add($storefront);
        $configCollection->add($plugin);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->once())->method('getBundles')->willReturn([
            'ThemeLevel1' => $theme1PluginBundle,
            'ThemeLevel2' => $theme2PluginBundle,
            'MockStorefront' => $storefrontBundle,
            'SimplePlugin' => $pluginBundle,
        ]);

        $kernel->expects($this->any())->method('getBundle')->willReturnMap([
            ['ThemeLevel1', $theme1PluginBundle],
            ['ThemeLevel2', $theme2PluginBundle],
            ['MockStorefront', $storefrontBundle],
            ['SimplePlugin', $pluginBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $sourceResolver,
            $kernel
        );
        $this->expectException(ThemeException::class);
        $this->expectExceptionMessageMatches('/Circular dependency detected for theme "ThemeLevel1".*/');

        $resolvedFiles = (new ThemeFileResolver($themeFilesystemResolver))->resolveFiles(
            $themeConfig1,
            $configCollection,
            false
        );
    }

    public function testResolvedFilesWithExplicitFiles(): void
    {
        $themePluginBundle = new ThemeWithMultiInheritanceExplicitFiles(true, __DIR__ . '/fixtures/SimplePlugin');
        $themeBundle = new SimpleThemeWithResources();
        $storefrontBundle = new MockStorefront();
        $pluginBundle = new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin');

        $sourceResolver = new StaticSourceResolver([]);
        $factory = new StorefrontPluginConfigurationFactory(
            $this->createMock(KernelPluginLoader::class),
            $sourceResolver
        );

        $config = $factory->createFromBundle($themePluginBundle);
        $storefront = $factory->createFromBundle($storefrontBundle);
        $theme = $factory->createFromBundle($themeBundle);
        $plugin = $factory->createFromBundle($pluginBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($storefront);
        $configCollection->add($theme);
        $configCollection->add($plugin);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->once())->method('getBundles')->willReturn([
            'ThemeWithMultiInheritanceExplicitFiles' => $themePluginBundle,
            'SimpleThemeWithResources' => $themeBundle,
            'MockStorefront' => $storefrontBundle,
            'SimplePlugin' => $pluginBundle,
        ]);

        $kernel->expects($this->any())->method('getBundle')->willReturnMap([
            ['ThemeWithMultiInheritanceExplicitFiles', $themePluginBundle],
            ['SimpleThemeWithResources', $themeBundle],
            ['MockStorefront', $storefrontBundle],
            ['SimplePlugin', $pluginBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $sourceResolver,
            $kernel
        );

        $resolvedFiles = (new ThemeFileResolver($themeFilesystemResolver))->resolveFiles(
            $config,
            $configCollection,
            false
        );
        $scriptFiles = $resolvedFiles['script'];
        $actual = $scriptFiles->getFilepaths();
        $currentPath = __DIR__ . '/fixtures/';
        // check that the explicit files are included and at the beginning of the list
        $expected = [
            $currentPath . 'SimpleThemeWithResources/Resources/app/storefront/dist/storefront/js/storefront.js',
            $currentPath . 'MockStorefront/Resources/app/storefront/dist/js/vendor-node.js',
            $currentPath . 'MockStorefront/Resources/app/storefront/dist/js/vendor-shared.js',
            $currentPath . 'MockStorefront/Resources/app/storefront/dist/js/runtime.js',
            $currentPath . 'MockStorefront/Resources/app/storefront/dist/storefront/js/storefront.js',
            $currentPath . 'SimpleThemeWithResources/Resources/app/storefront/dist/js/runtime.js',
            $currentPath . 'SimplePlugin/Resources/app/storefront/dist/storefront/js/simple-plugin/simple-plugin.js',
        ];

        static::assertSame($expected, $actual);

        $styleFiles = $resolvedFiles['style'];
        $actualStyle = $styleFiles->getFilepaths();
        // check that the explicit files are included and at the beginning of the list
        $expectedStyle = [
            $currentPath . 'SimpleThemeWithResources/Resources/app/storefront/src/scss/overrides.scss',
            $currentPath . 'MockStorefront/Resources/app/storefront/src/scss/base.scss',
            $currentPath . 'MockStorefront/Resources/app/storefront/src/scss/skin/shopware/_base.scss',
            $currentPath . 'SimpleThemeWithResources/Resources/app/storefront/src/scss/base.scss',
            $currentPath . 'SimplePlugin/Resources/app/storefront/src/scss/base.scss',
        ];
        static::assertSame($expectedStyle, $actualStyle);
    }

    public function testParentThemeIncludesPlugins(): void
    {
        $themePluginBundle = new ThemeNotIncludingPluginJsAndCss();
        $storefrontBundle = new MockStorefront();
        $pluginBundle = new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin');

        $sourceResolver = new StaticSourceResolver([]);
        $factory = new StorefrontPluginConfigurationFactory(
            $this->createMock(KernelPluginLoader::class),
            $sourceResolver
        );

        $config = $factory->createFromBundle($themePluginBundle);
        $storefront = $factory->createFromBundle($storefrontBundle);
        $plugin = $factory->createFromBundle($pluginBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($storefront);
        $configCollection->add($plugin);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->once())->method('getBundles')->willReturn([
            'ThemeNotIncludingPluginJsAndCss' => $themePluginBundle,
            'MockStorefront' => $storefrontBundle,
            'SimplePlugin' => $pluginBundle,
        ]);

        $kernel->expects($this->any())->method('getBundle')->willReturnMap([
            ['ThemeNotIncludingPluginJsAndCss', $themePluginBundle],
            ['MockStorefront', $storefrontBundle],
            ['SimplePlugin', $pluginBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $sourceResolver,
            $kernel
        );

        $resolvedFiles = (new ThemeFileResolver($themeFilesystemResolver))->resolveFiles(
            $config,
            $configCollection,
            false
        );

        $scriptFiles = $resolvedFiles['script'];
        $pluginScriptFile = 'SimplePlugin/Resources/app/storefront/dist/storefront/js/simple-plugin/simple-plugin.js';
        $pluginScriptIncluded = false;

        foreach ($scriptFiles->getFilepaths() as $path) {
            if (mb_stripos((string) $path, $pluginScriptFile) !== false) {
                $pluginScriptIncluded = true;

                break;
            }
        }

        static::assertTrue($pluginScriptIncluded);

        $styleFiles = $resolvedFiles['style'];
        $pluginEntryStyleFile = 'SimplePlugin/Resources/app/storefront/src/scss/base.scss';
        $pluginStyleIncluded = false;

        foreach ($styleFiles->getFilepaths() as $path) {
            if (mb_stripos((string) $path, $pluginEntryStyleFile) !== false) {
                $pluginStyleIncluded = true;

                break;
            }
        }

        static::assertTrue($pluginStyleIncluded);
    }

    public function testResolveFilesDoesntAffectPassedArguments(): void
    {
        $themePluginBundle = new ThemeWithStorefrontSkinScss();
        $storefrontBundle = new MockStorefront();

        $sourceResolver = new StaticSourceResolver([]);
        $factory = new StorefrontPluginConfigurationFactory(
            $this->createMock(KernelPluginLoader::class),
            $sourceResolver
        );
        $config = $factory->createFromBundle($themePluginBundle);
        $storefront = $factory->createFromBundle($storefrontBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($storefront);

        $firstFile = $config->getStyleFiles()->first();
        static::assertNotNull($firstFile);
        $currentPath = $firstFile->getFilepath();

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->once())->method('getBundles')->willReturn([
            'ThemeWithStorefrontSkinScss' => $themePluginBundle,
            'MockStorefront' => $storefrontBundle,
        ]);

        $kernel->expects($this->any())->method('getBundle')->willReturnMap([
            ['ThemeWithStorefrontSkinScss', $themePluginBundle],
            ['MockStorefront', $storefrontBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $sourceResolver,
            $kernel
        );

        (new ThemeFileResolver($themeFilesystemResolver))->resolveFiles(
            $config,
            $configCollection,
            false
        );

        // Path is still relative
        static::assertSame($currentPath, $config->getStyleFiles()->first()?->getFilepath());

        $config->setScriptFiles(new FileCollection());
        $config->setStorefrontEntryFilepath(__FILE__);

        (new ThemeFileResolver($themeFilesystemResolver))->resolveFiles(
            $config,
            $configCollection,
            true
        );

        static::assertSame($currentPath, $config->getStyleFiles()->first()?->getFilepath());
    }
}
