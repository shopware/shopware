<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Kernel;
use Shopware\Core\Test\Stub\App\StaticSourceResolver;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\ThemeFileResolver;
use Shopware\Storefront\Theme\ThemeFilesystemResolver;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\MockStorefront\MockStorefront;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\SimplePlugin\SimplePlugin;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeNotIncludingPluginJsAndCss\ThemeNotIncludingPluginJsAndCss;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeWithMultiInheritance\ThemeWithMultiInheritance;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeWithStorefrontBootstrapScss\ThemeWithStorefrontBootstrapScss;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeWithStorefrontSkinScss\ThemeWithStorefrontSkinScss;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * @internal
 */
#[CoversClass(ThemeFileResolver::class)]
class ThemeFileResolverTest extends TestCase
{
    public function testResolvedFilesIncludeSkinScssPath(): void
    {
        $projectDir = __DIR__;
        $themePluginBundle = new ThemeWithStorefrontSkinScss();
        $storefrontBundle = new MockStorefront();

        $sourceResolver = new StaticSourceResolver([]);

        $factory = new StorefrontPluginConfigurationFactory(
            $projectDir,
            $this->createMock(KernelPluginLoader::class),
            $sourceResolver
        );

        $config = $factory->createFromBundle($themePluginBundle);
        $storefront = $factory->createFromBundle($storefrontBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($storefront);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects(static::once())->method('getBundles')->willReturn([
            'ThemeWithStorefrontSkinScss' => $this->createMock(BundleInterface::class),
            'MockStorefront' => $this->createMock(BundleInterface::class),
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $sourceResolver,
            $projectDir,
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

        $projectDir = __DIR__;

        $sourceResolver = new StaticSourceResolver([]);
        $factory = new StorefrontPluginConfigurationFactory(
            $projectDir,
            $this->createMock(KernelPluginLoader::class),
            $sourceResolver
        );

        $config = $factory->createFromBundle($themePluginBundle);
        $storefront = $factory->createFromBundle($storefrontBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($storefront);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects(static::once())->method('getBundles')->willReturn([
            'ThemeWithStorefrontBootstrapScss' => $this->createMock(BundleInterface::class),
            'MockStorefront' => $this->createMock(BundleInterface::class),
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $sourceResolver,
            $projectDir,
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

        $projectDir = __DIR__;

        $sourceResolver = new StaticSourceResolver([]);
        $factory = new StorefrontPluginConfigurationFactory(
            $projectDir,
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
        $kernel->expects(static::once())->method('getBundles')->willReturn([
            'ThemeWithMultiInheritance' => $this->createMock(BundleInterface::class),
            'MockStorefront' => $this->createMock(BundleInterface::class),
            'SimplePlugin' => $this->createMock(BundleInterface::class),
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $sourceResolver,
            $projectDir,
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

        static::assertEquals($expected, $actual);
    }

    public function testParentThemeIncludesPlugins(): void
    {
        $projectDir = __DIR__;

        $themePluginBundle = new ThemeNotIncludingPluginJsAndCss();
        $storefrontBundle = new MockStorefront();
        $pluginBundle = new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin');

        $sourceResolver = new StaticSourceResolver([]);
        $factory = new StorefrontPluginConfigurationFactory(
            $projectDir,
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
        $kernel->expects(static::once())->method('getBundles')->willReturn([
            'ThemeNotIncludingPluginJsAndCss' => $this->createMock(BundleInterface::class),
            'MockStorefront' => $this->createMock(BundleInterface::class),
            'SimplePlugin' => $this->createMock(BundleInterface::class),
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $sourceResolver,
            $projectDir,
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

        $projectDir = __DIR__;
        $sourceResolver = new StaticSourceResolver([]);
        $factory = new StorefrontPluginConfigurationFactory(
            $projectDir,
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
        $kernel->expects(static::once())->method('getBundles')->willReturn([
            'ThemeWithStorefrontSkinScss' => $this->createMock(BundleInterface::class),
            'MockStorefront' => $this->createMock(BundleInterface::class),
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $sourceResolver,
            $projectDir,
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
