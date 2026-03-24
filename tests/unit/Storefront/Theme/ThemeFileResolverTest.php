<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Kernel;
use Shopware\Core\Test\Stub\App\StaticSourceResolver;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;
use Shopware\Storefront\Framework\Twig\Components\TwigComponent;
use Shopware\Storefront\Framework\Twig\Components\TwigComponentCollection;
use Shopware\Storefront\Framework\Twig\Components\TwigComponentHelper;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;
use Shopware\Storefront\Theme\Exception\ThemeException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\File;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\ThemeFileResolver;
use Shopware\Storefront\Theme\ThemeFilesystemResolver;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\MockStorefront\MockStorefront;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\SimplePlugin\SimplePlugin;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeNotIncludingPluginJsAndCss\ThemeNotIncludingPluginJsAndCss;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeWithBundleRelativeFiles\ThemeWithBundleRelativeFiles;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeWithComponentReference\ThemeWithComponentReference;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeWithInvalidBundleReference\ThemeWithInvalidBundleReference;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeWithMultiInheritance\ThemeWithMultiInheritance;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeWithNamespacedComponentReference\ThemeWithNamespacedComponentReference;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeWithStorefrontBootstrapScss\ThemeWithStorefrontBootstrapScss;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeWithStorefrontSkinScss\ThemeWithStorefrontSkinScss;
use Symfony\Component\Filesystem\Filesystem;

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
            $sourceResolver,
            new Filesystem(),
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

        $twigComponentHelper = $this->createMock(TwigComponentHelper::class);

        $resolvedFiles = (new ThemeFileResolver($themeFilesystemResolver, $twigComponentHelper))->resolveFiles(
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
            $sourceResolver,
            new Filesystem(),
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

        $twigComponentHelper = $this->createMock(TwigComponentHelper::class);

        $resolvedFiles = (new ThemeFileResolver($themeFilesystemResolver, $twigComponentHelper))->resolveFiles(
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
            $sourceResolver,
            new Filesystem(),
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

        $twigComponentHelper = $this->createMock(TwigComponentHelper::class);

        $resolvedFiles = (new ThemeFileResolver($themeFilesystemResolver, $twigComponentHelper))->resolveFiles(
            $config,
            $configCollection,
            false
        );
        $scriptFiles = $resolvedFiles['script'];
        $actual = $scriptFiles->getFilepaths();
        $expected = array_unique($scriptFiles->getFilepaths());

        static::assertSame($expected, $actual);
    }

    public function testParentThemeIncludesPlugins(): void
    {
        $themePluginBundle = new ThemeNotIncludingPluginJsAndCss();
        $storefrontBundle = new MockStorefront();
        $pluginBundle = new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin');

        $sourceResolver = new StaticSourceResolver([]);
        $factory = new StorefrontPluginConfigurationFactory(
            $this->createMock(KernelPluginLoader::class),
            $sourceResolver,
            new Filesystem(),
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

        $twigComponentHelper = $this->createMock(TwigComponentHelper::class);

        $resolvedFiles = (new ThemeFileResolver($themeFilesystemResolver, $twigComponentHelper))->resolveFiles(
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
            $sourceResolver,
            new Filesystem(),
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

        $twigComponentHelper = $this->createMock(TwigComponentHelper::class);

        (new ThemeFileResolver($themeFilesystemResolver, $twigComponentHelper))->resolveFiles(
            $config,
            $configCollection,
            false
        );

        // Path is still relative
        static::assertSame($currentPath, $config->getStyleFiles()->first()?->getFilepath());

        $config->setScriptFiles(new FileCollection());
        $config->setStorefrontEntryFilepath(__FILE__);

        (new ThemeFileResolver($themeFilesystemResolver, $twigComponentHelper))->resolveFiles(
            $config,
            $configCollection,
            true
        );

        static::assertSame($currentPath, $config->getStyleFiles()->first()?->getFilepath());
    }

    public function testCircularReferencePreventionReturnsEmptyCollection(): void
    {
        $themePluginBundle = new ThemeWithMultiInheritance(true, __DIR__ . '/fixtures/SimplePlugin');
        $storefrontBundle = new MockStorefront();

        $sourceResolver = new StaticSourceResolver([]);
        $factory = new StorefrontPluginConfigurationFactory(
            $this->createMock(KernelPluginLoader::class),
            $sourceResolver,
            new Filesystem(),
        );

        $config = $factory->createFromBundle($themePluginBundle);
        $storefront = $factory->createFromBundle($storefrontBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($storefront);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->any())->method('getBundles')->willReturn([
            'ThemeWithMultiInheritance' => $themePluginBundle,
            'MockStorefront' => $storefrontBundle,
        ]);

        $kernel->expects($this->any())->method('getBundle')->willReturnMap([
            ['ThemeWithMultiInheritance', $themePluginBundle],
            ['MockStorefront', $storefrontBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $sourceResolver,
            $kernel
        );

        $twigComponentHelper = $this->createMock(TwigComponentHelper::class);
        $resolver = new ThemeFileResolver($themeFilesystemResolver, $twigComponentHelper);

        // This should not cause infinite loop - circular references are handled internally
        $result = $resolver->resolveScriptFiles($config, $configCollection, false);

        static::assertGreaterThan(0, $result->count());
    }

    public function testFileDeduplicationAcrossNamespaces(): void
    {
        $themePluginBundle = new ThemeWithMultiInheritance(true, __DIR__ . '/fixtures/SimplePlugin');
        $storefrontBundle = new MockStorefront();
        $pluginBundle = new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin');

        $sourceResolver = new StaticSourceResolver([]);
        $factory = new StorefrontPluginConfigurationFactory(
            $this->createMock(KernelPluginLoader::class),
            $sourceResolver,
            new Filesystem(),
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

        $twigComponentHelper = $this->createMock(TwigComponentHelper::class);

        $resolvedFiles = (new ThemeFileResolver($themeFilesystemResolver, $twigComponentHelper))->resolveFiles(
            $config,
            $configCollection,
            false
        );

        $styleFiles = $resolvedFiles['style'];
        $stylePaths = $styleFiles->getFilepaths();

        // Check that all paths are unique
        static::assertCount(\count(array_unique($stylePaths)), $stylePaths, 'Style files should not contain duplicates');

        $scriptFiles = $resolvedFiles['script'];
        $scriptPaths = $scriptFiles->getFilepaths();

        // Check that all paths are unique
        static::assertCount(\count(array_unique($scriptPaths)), $scriptPaths, 'Script files should not contain duplicates');
    }

    public function testBundleRelativeFileResolution(): void
    {
        $themePluginBundle = new ThemeWithBundleRelativeFiles();
        $storefrontBundle = new MockStorefront();

        $sourceResolver = new StaticSourceResolver([]);
        $factory = new StorefrontPluginConfigurationFactory(
            $this->createMock(KernelPluginLoader::class),
            $sourceResolver,
            new Filesystem(),
        );

        $config = $factory->createFromBundle($themePluginBundle);
        $storefront = $factory->createFromBundle($storefrontBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($storefront);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->any())->method('getBundles')->willReturn([
            'ThemeWithBundleRelativeFiles' => $themePluginBundle,
            'MockStorefront' => $storefrontBundle,
        ]);

        $kernel->expects($this->any())->method('getBundle')->willReturnMap([
            ['ThemeWithBundleRelativeFiles', $themePluginBundle],
            ['MockStorefront', $storefrontBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $sourceResolver,
            $kernel
        );

        $twigComponentHelper = $this->createMock(TwigComponentHelper::class);

        $resolvedFiles = (new ThemeFileResolver($themeFilesystemResolver, $twigComponentHelper))->resolveFiles(
            $config,
            $configCollection,
            false
        );

        $styleFiles = $resolvedFiles['style'];
        $paths = $styleFiles->getFilepaths();

        // Check that overrides.scss is resolved
        $overridesFound = false;
        $overridesPosition = -1;
        foreach ($paths as $index => $path) {
            if (str_contains((string) $path, 'overrides.scss')) {
                $overridesFound = true;
                $overridesPosition = $index;
                break;
            }
        }
        static::assertTrue($overridesFound, 'Bundle-relative file @MockStorefront/app/storefront/src/scss/overrides.scss should be resolved');

        // Check that overrides.scss appears only once (not duplicated when @MockStorefront is expanded)
        $overridesCount = 0;
        foreach ($paths as $path) {
            if (str_contains((string) $path, 'overrides.scss')) {
                ++$overridesCount;
            }
        }
        static::assertSame(1, $overridesCount, 'overrides.scss should appear only once (no duplication)');

        // Check that overrides.scss appears before base.scss (order preservation)
        $basePosition = -1;
        foreach ($paths as $index => $path) {
            if (str_contains((string) $path, 'base.scss')) {
                $basePosition = $index;
                break;
            }
        }

        if ($basePosition !== -1) {
            static::assertLessThan($basePosition, $overridesPosition, 'Bundle-relative file should appear in order before full bundle expansion');
        }

        // Check that custom.scss from the theme itself is also included
        $customFound = false;
        foreach ($paths as $path) {
            if (str_contains((string) $path, 'custom.scss')) {
                $customFound = true;
                break;
            }
        }
        static::assertTrue($customFound, 'Direct file reference custom.scss should be resolved');
    }

    public function testBundleRelativeFileThrowsExceptionForMissingBundle(): void
    {
        $themePluginBundle = new ThemeWithInvalidBundleReference();

        $sourceResolver = new StaticSourceResolver([]);
        $factory = new StorefrontPluginConfigurationFactory(
            $this->createMock(KernelPluginLoader::class),
            $sourceResolver,
            new Filesystem(),
        );

        $config = $factory->createFromBundle($themePluginBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->any())->method('getBundles')->willReturn([
            'ThemeWithInvalidBundleReference' => $themePluginBundle,
        ]);

        $kernel->expects($this->any())->method('getBundle')->willReturnMap([
            ['ThemeWithInvalidBundleReference', $themePluginBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $sourceResolver,
            $kernel
        );

        $twigComponentHelper = $this->createMock(TwigComponentHelper::class);
        $resolver = new ThemeFileResolver($themeFilesystemResolver, $twigComponentHelper);

        $this->expectExceptionObject(
            ThemeException::couldNotFindThemeByName('NonExistentBundle')
        );

        $resolver->resolveStyleFiles($config, $configCollection, false);
    }

    public function testComponentSingleFileReference(): void
    {
        $themePluginBundle = new ThemeWithComponentReference();

        $sourceResolver = new StaticSourceResolver([]);
        $factory = new StorefrontPluginConfigurationFactory(
            $this->createMock(KernelPluginLoader::class),
            $sourceResolver,
            new Filesystem(),
        );

        $config = $factory->createFromBundle($themePluginBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->any())->method('getBundles')->willReturn([
            'ThemeWithComponentReference' => $themePluginBundle,
        ]);

        $kernel->expects($this->any())->method('getBundle')->willReturnMap([
            ['ThemeWithComponentReference', $themePluginBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $sourceResolver,
            $kernel
        );

        $testComponent = new class('Sw:Alert', '/base/Storefront/Resources/views/components/Sw/Alert/index.html.twig', 'Storefront') extends TwigComponent {
            public function getStylePath(): string
            {
                return '/base/Storefront/Resources/views/components/Sw/Alert/index.scss';
            }
        };

        $componentCollection = new TwigComponentCollection([$testComponent]);

        $twigComponentHelper = $this->createMock(TwigComponentHelper::class);
        $twigComponentHelper->expects($this->any())
            ->method('getComponents')
            ->willReturn($componentCollection);

        $localFilesystem = $this->createMock(Filesystem::class);
        $localFilesystem->method('exists')->willReturn(true);

        $resolver = new ThemeFileResolver($themeFilesystemResolver, $twigComponentHelper, $localFilesystem);

        $result = $resolver->resolveStyleFiles($config, $configCollection, false);

        // Verify the component file was resolved
        static::assertCount(1, $result);
        $resolvedPath = $result->first()?->getFilepath();
        static::assertStringContainsString('Sw/Alert/index.scss', (string) $resolvedPath);
    }

    /**
     * Regression test: @Components:MyPlugin/Custom/Test.scss should resolve
     * to a file whose assetName is 'MyPlugin/Custom' (not just 'MyPlugin').
     */
    public function testNamespacedComponentReferenceSubdirectoryUsesCorrectAssetName(): void
    {
        $themePluginBundle = new ThemeWithNamespacedComponentReference();

        $sourceResolver = new StaticSourceResolver([]);
        $factory = new StorefrontPluginConfigurationFactory(
            $this->createMock(KernelPluginLoader::class),
            $sourceResolver,
            new Filesystem(),
        );

        $config = $factory->createFromBundle($themePluginBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->any())->method('getBundles')->willReturn([
            'ThemeWithNamespacedComponentReference' => $themePluginBundle,
        ]);
        $kernel->expects($this->any())->method('getBundle')->willReturnMap([
            ['ThemeWithNamespacedComponentReference', $themePluginBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver($sourceResolver, $kernel);

        $component = new class('Custom:Test', '/plugin/MyPlugin/Resources/views/components/Custom/Test/index.html.twig', 'MyPlugin') extends TwigComponent {
            public function getStylePath(): string
            {
                return '/plugin/MyPlugin/Resources/views/components/Custom/Test/index.scss';
            }
        };

        $twigComponentHelper = $this->createMock(TwigComponentHelper::class);
        $twigComponentHelper->expects($this->any())
            ->method('getComponents')
            ->willReturn(new TwigComponentCollection([$component]));

        $localFilesystem = $this->createMock(Filesystem::class);
        $localFilesystem->method('exists')->willReturn(true);

        $result = (new ThemeFileResolver($themeFilesystemResolver, $twigComponentHelper, $localFilesystem))
            ->resolveStyleFiles($config, $configCollection, false);

        static::assertCount(1, $result);
        $resolvedFile = $result->first();
        static::assertNotNull($resolvedFile);
        static::assertStringContainsString('Custom/Test/index.scss', $resolvedFile->getFilepath());

        // assetName must include the 'Custom' subdirectory
        static::assertSame('MyPlugin/Custom', $resolvedFile->assetName);
    }

    public function testComponentSingleFileReferenceWithBundleNamespace(): void
    {
        $themePluginBundle = new ThemeWithNamespacedComponentReference();

        $sourceResolver = new StaticSourceResolver([]);
        $factory = new StorefrontPluginConfigurationFactory(
            $this->createMock(KernelPluginLoader::class),
            $sourceResolver,
            new Filesystem(),
        );

        $config = $factory->createFromBundle($themePluginBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->any())->method('getBundles')->willReturn([
            'ThemeWithNamespacedComponentReference' => $themePluginBundle,
        ]);

        $kernel->expects($this->any())->method('getBundle')->willReturnMap([
            ['ThemeWithNamespacedComponentReference', $themePluginBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $sourceResolver,
            $kernel
        );

        // Two components in the same relative path but different namespaces.
        // Paths must end with {namespace}/Resources/views/components/{requestedPath}
        // for resolveComponentSingleFile()'s str_ends_with() check to match.
        $componentStorefront = new class('Custom:Test', '/base/Storefront/Resources/views/components/Custom/Test/index.html.twig', 'Storefront') extends TwigComponent {
            public function getStylePath(): string
            {
                return '/base/Storefront/Resources/views/components/Custom/Test/index.scss';
            }
        };

        $componentPlugin = new class('Custom:Test', '/base/MyPlugin/Resources/views/components/Custom/Test/index.html.twig', 'MyPlugin') extends TwigComponent {
            public function getStylePath(): string
            {
                return '/base/MyPlugin/Resources/views/components/Custom/Test/index.scss';
            }
        };

        $componentCollection = new TwigComponentCollection([
            $componentStorefront,
            $componentPlugin,
        ]);

        $twigComponentHelper = $this->createMock(TwigComponentHelper::class);
        $twigComponentHelper->expects($this->any())
            ->method('getComponents')
            ->willReturn($componentCollection);

        $localFilesystem = $this->createMock(Filesystem::class);
        $localFilesystem->method('exists')->willReturn(true);

        $resolver = new ThemeFileResolver($themeFilesystemResolver, $twigComponentHelper, $localFilesystem);

        $result = $resolver->resolveStyleFiles($config, $configCollection, false);

        // Verify that only the MyPlugin component was resolved (not the Storefront one)
        static::assertCount(1, $result);
        $resolvedPath = $result->first()?->getFilepath();
        static::assertStringContainsString('/MyPlugin/Resources/views/components/Custom/Test/index.scss', (string) $resolvedPath);
        static::assertStringNotContainsString('/Storefront/Resources/views/components/', (string) $resolvedPath);
    }

    public function testResolveScriptFilesWithComponentsPlaceholder(): void
    {
        $config = new StorefrontPluginConfiguration('TestTheme');
        $config->setScriptFiles(FileCollection::createFromArray(['@Components']));
        $config->setStyleFiles(new FileCollection());

        $configCollection = new StorefrontPluginConfigurationCollection([$config]);

        $component = new class('Sw:Button', '/base/Storefront/Resources/views/components/Sw/Button.html.twig', 'Storefront') extends TwigComponent {
            public function getScriptPath(): string
            {
                return '/base/Storefront/Resources/views/components/Sw/Button/index.js';
            }
        };

        $twigComponentHelper = $this->createMock(TwigComponentHelper::class);
        $twigComponentHelper->method('getComponents')
            ->willReturn(new TwigComponentCollection([$component]));

        $localFilesystem = $this->createMock(Filesystem::class);
        $localFilesystem->method('exists')->willReturn(true);

        $themeFilesystemResolver = $this->createMock(ThemeFilesystemResolver::class);
        $resolver = new ThemeFileResolver($themeFilesystemResolver, $twigComponentHelper, $localFilesystem);

        $result = $resolver->resolveScriptFiles($config, $configCollection, false);

        static::assertCount(1, $result);
        static::assertStringContainsString('Sw/Button/index.js', (string) $result->first()?->getFilepath());
    }

    public function testComponentsPlaceholderSkipsComponentsWithNullScriptPath(): void
    {
        $config = new StorefrontPluginConfiguration('TestTheme');
        $config->setScriptFiles(FileCollection::createFromArray(['@Components']));
        $config->setStyleFiles(new FileCollection());

        $configCollection = new StorefrontPluginConfigurationCollection([$config]);

        // Component with no .js file alongside its template — filesystem reports it does not exist
        $component = new TwigComponent('Sw:Badge', '/base/Storefront/Resources/views/components/Sw/Badge/index.html.twig', 'Storefront');

        $twigComponentHelper = $this->createMock(TwigComponentHelper::class);
        $twigComponentHelper->method('getComponents')
            ->willReturn(new TwigComponentCollection([$component]));

        $localFilesystem = $this->createMock(Filesystem::class);
        $localFilesystem->method('exists')->willReturn(false);

        $themeFilesystemResolver = $this->createMock(ThemeFilesystemResolver::class);
        $resolver = new ThemeFileResolver($themeFilesystemResolver, $twigComponentHelper, $localFilesystem);

        $result = $resolver->resolveScriptFiles($config, $configCollection, false);

        static::assertCount(0, $result);
    }

    public function testResolveComponentSingleFileThrowsWhenNoComponentMatches(): void
    {
        $config = new StorefrontPluginConfiguration('TestTheme');
        $config->setStyleFiles(FileCollection::createFromArray(['@Components/Sw/NonExistent.scss']));
        $config->setScriptFiles(new FileCollection());

        $configCollection = new StorefrontPluginConfigurationCollection([$config]);

        $twigComponentHelper = $this->createMock(TwigComponentHelper::class);
        $twigComponentHelper->method('getComponents')
            ->willReturn(new TwigComponentCollection());

        $themeFilesystemResolver = $this->createMock(ThemeFilesystemResolver::class);
        $resolver = new ThemeFileResolver($themeFilesystemResolver, $twigComponentHelper);

        $this->expectException(ThemeCompileException::class);

        $resolver->resolveStyleFiles($config, $configCollection, false);
    }

    public function testStorefrontBootstrapNamespaceResolvesToBaseScssWithVendorMapping(): void
    {
        $config = new StorefrontPluginConfiguration('TestTheme');
        $config->setStyleFiles(FileCollection::createFromArray(['@StorefrontBootstrap']));
        $config->setScriptFiles(new FileCollection());

        $configCollection = new StorefrontPluginConfigurationCollection([$config]);

        $themeFilesystemResolver = $this->createMock(ThemeFilesystemResolver::class);
        $twigComponentHelper = $this->createMock(TwigComponentHelper::class);
        $resolver = new ThemeFileResolver($themeFilesystemResolver, $twigComponentHelper);

        $result = $resolver->resolveStyleFiles($config, $configCollection, false);

        static::assertCount(1, $result);
        $file = $result->first();
        static::assertNotNull($file);
        static::assertStringEndsWith('Resources/app/storefront/src/scss/base.scss', $file->getFilepath());
        static::assertArrayHasKey('vendor', $file->getResolveMapping());
        static::assertStringEndsWith('Resources/app/storefront/vendor', $file->getResolveMapping()['vendor']);
    }

    public function testDirectFileMissingMatchingOldJsStructureThrowsException(): void
    {
        $config = new StorefrontPluginConfiguration('TestTheme');

        // Path ends with "test-plugin/test-plugin.css" — matches the old-JS-structure pattern → throw
        $file = new File('/nonexistent/test-plugin/test-plugin.css', [], 'test-plugin');
        $fileCollection = new FileCollection();
        $fileCollection->add($file);
        $config->setStyleFiles($fileCollection);
        $config->setScriptFiles(new FileCollection());

        $configCollection = new StorefrontPluginConfigurationCollection([$config]);

        $themeFilesystemResolver = $this->createMock(ThemeFilesystemResolver::class);
        $themeFilesystemResolver->method('getFilesystemForStorefrontConfig')->willReturn(new StaticFilesystem([]));

        $twigComponentHelper = $this->createMock(TwigComponentHelper::class);
        $resolver = new ThemeFileResolver($themeFilesystemResolver, $twigComponentHelper);

        $this->expectException(ThemeCompileException::class);
        $resolver->resolveStyleFiles($config, $configCollection, false);
    }

    public function testDirectFileMissingNotMatchingOldJsStructureIsSilentlySkipped(): void
    {
        $config = new StorefrontPluginConfiguration('TestTheme');

        // assetName is 'test-plugin', but file is 'other-file.css' — no old-JS-structure match → silent skip
        $file = new File('/nonexistent/other-file.css', [], 'test-plugin');
        $fileCollection = new FileCollection();
        $fileCollection->add($file);
        $config->setStyleFiles($fileCollection);
        $config->setScriptFiles(new FileCollection());

        $configCollection = new StorefrontPluginConfigurationCollection([$config]);

        $themeFilesystemResolver = $this->createMock(ThemeFilesystemResolver::class);
        $themeFilesystemResolver->method('getFilesystemForStorefrontConfig')->willReturn(new StaticFilesystem([]));

        $twigComponentHelper = $this->createMock(TwigComponentHelper::class);
        $resolver = new ThemeFileResolver($themeFilesystemResolver, $twigComponentHelper);

        $result = $resolver->resolveStyleFiles($config, $configCollection, false);

        static::assertCount(0, $result, 'Missing file with non-matching path structure should be silently skipped');
    }

    public function testPluginsNamespaceExpandsAllNonThemePlugins(): void
    {
        $pluginBundle = new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin');

        $sourceResolver = new StaticSourceResolver([]);
        $factory = new StorefrontPluginConfigurationFactory(
            $this->createMock(KernelPluginLoader::class),
            $sourceResolver,
            new Filesystem(),
        );

        // Theme that directly declares @Plugins — no @MockStorefront indirection
        $themeConfig = new StorefrontPluginConfiguration('TestTheme');
        $themeConfig->setIsTheme(true);
        $themeConfig->setStyleFiles(FileCollection::createFromArray(['@Plugins']));
        $themeConfig->setScriptFiles(new FileCollection());

        $plugin = $factory->createFromBundle($pluginBundle);

        $configCollection = new StorefrontPluginConfigurationCollection([$themeConfig, $plugin]);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->any())->method('getBundles')->willReturn([
            'SimplePlugin' => $pluginBundle,
        ]);
        $kernel->expects($this->any())->method('getBundle')->willReturnMap([
            ['SimplePlugin', $pluginBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver($sourceResolver, $kernel);
        $twigComponentHelper = $this->createMock(TwigComponentHelper::class);

        $result = (new ThemeFileResolver($themeFilesystemResolver, $twigComponentHelper))
            ->resolveStyleFiles($themeConfig, $configCollection, false);

        $paths = $result->getFilepaths();
        $pluginStyleIncluded = false;
        foreach ($paths as $path) {
            if (mb_stripos((string) $path, 'SimplePlugin') !== false) {
                $pluginStyleIncluded = true;
                break;
            }
        }

        static::assertTrue($pluginStyleIncluded, '@Plugins should include style files from non-theme plugins');
    }

    public function testDuplicateNamespaceReferenceIsExpandedOnlyOnce(): void
    {
        $config = new StorefrontPluginConfiguration('TestTheme');
        // @StorefrontBootstrap listed twice — base.scss should still appear exactly once
        $config->setStyleFiles(FileCollection::createFromArray(['@StorefrontBootstrap', '@StorefrontBootstrap']));
        $config->setScriptFiles(new FileCollection());

        $configCollection = new StorefrontPluginConfigurationCollection([$config]);

        $themeFilesystemResolver = $this->createMock(ThemeFilesystemResolver::class);
        $twigComponentHelper = $this->createMock(TwigComponentHelper::class);
        $resolver = new ThemeFileResolver($themeFilesystemResolver, $twigComponentHelper);

        $result = $resolver->resolveStyleFiles($config, $configCollection, false);

        static::assertCount(1, $result, 'Duplicate @Namespace reference should be expanded only once');
    }

    public function testResolveStyleFilesWithComponentsPlaceholder(): void
    {
        $config = new StorefrontPluginConfiguration('TestTheme');
        $config->setStyleFiles(FileCollection::createFromArray(['@Components']));
        $config->setScriptFiles(new FileCollection());

        $configCollection = new StorefrontPluginConfigurationCollection([$config]);

        $component = new class('Sw:Button', '/base/Storefront/Resources/views/components/Sw/Button.html.twig', 'Storefront') extends TwigComponent {
            public function getStylePath(): string
            {
                return '/base/Storefront/Resources/views/components/Sw/Button/Button.css';
            }
        };

        $twigComponentHelper = $this->createMock(TwigComponentHelper::class);
        $twigComponentHelper->method('getComponents')
            ->willReturn(new TwigComponentCollection([$component]));

        $localFilesystem = $this->createMock(Filesystem::class);
        $localFilesystem->method('exists')->willReturn(true);

        $themeFilesystemResolver = $this->createMock(ThemeFilesystemResolver::class);
        $resolver = new ThemeFileResolver($themeFilesystemResolver, $twigComponentHelper, $localFilesystem);

        $result = $resolver->resolveStyleFiles($config, $configCollection, false);

        static::assertCount(1, $result);
        static::assertStringContainsString('Button.css', (string) $result->first()?->getFilepath());
    }

    public function testResolveComponentSingleFileForScriptFilesType(): void
    {
        // The reference @Components:MyPlugin/Custom/Test.js resolves to a component
        // whose getScriptPath() ends with "MyPlugin/Resources/views/components/Custom/Test.js"
        $config = new StorefrontPluginConfiguration('TestTheme');
        $config->setScriptFiles(FileCollection::createFromArray(['@Components:MyPlugin/Custom/Test.js']));
        $config->setStyleFiles(new FileCollection());

        $configCollection = new StorefrontPluginConfigurationCollection([$config]);

        $component = new class('Custom:Test', '/plugin/MyPlugin/Resources/views/components/Custom/Test.html.twig', 'MyPlugin') extends TwigComponent {
            public function getScriptPath(): string
            {
                // Must end with "MyPlugin/Resources/views/components/Custom/Test.js" for the path matching
                return '/plugin/MyPlugin/Resources/views/components/Custom/Test.js';
            }
        };

        $twigComponentHelper = $this->createMock(TwigComponentHelper::class);
        $twigComponentHelper->method('getComponents')
            ->willReturn(new TwigComponentCollection([$component]));

        $localFilesystem = $this->createMock(Filesystem::class);
        $localFilesystem->method('exists')->willReturn(true);

        $themeFilesystemResolver = $this->createMock(ThemeFilesystemResolver::class);
        $resolver = new ThemeFileResolver($themeFilesystemResolver, $twigComponentHelper, $localFilesystem);

        $result = $resolver->resolveScriptFiles($config, $configCollection, false);

        static::assertCount(1, $result);
        static::assertStringContainsString('Custom/Test.js', (string) $result->first()?->getFilepath());
    }
}
