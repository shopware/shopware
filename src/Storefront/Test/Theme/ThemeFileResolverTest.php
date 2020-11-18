<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme;

use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Test\Theme\fixtures\MockStorefront\MockStorefront;
use Shopware\Storefront\Test\Theme\fixtures\SimplePlugin\SimplePlugin;
use Shopware\Storefront\Test\Theme\fixtures\ThemeNotIncludingPluginJsAndCss\ThemeNotIncludingPluginJsAndCss;
use Shopware\Storefront\Test\Theme\fixtures\ThemeWithMultiInheritance\ThemeWithMultiInheritance;
use Shopware\Storefront\Test\Theme\fixtures\ThemeWithStorefrontBootstrapScss\ThemeWithStorefrontBootstrapScss;
use Shopware\Storefront\Test\Theme\fixtures\ThemeWithStorefrontSkinScss\ThemeWithStorefrontSkinScss;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\ThemeFileResolver;

class ThemeFileResolverTest extends TestCase
{
    public function testResolvedFilesIncludeSkinScssPath(): void
    {
        $themePluginBundle = new ThemeWithStorefrontSkinScss();
        $storefrontBundle = new MockStorefront();

        $factory = new StorefrontPluginConfigurationFactory(__DIR__);
        $config = $factory->createFromBundle($themePluginBundle);
        $storefront = $factory->createFromBundle($storefrontBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($storefront);

        $themeFileResolver = new ThemeFileResolver();
        $resolvedFiles = $themeFileResolver->resolveFiles(
            $config,
            $configCollection,
            false
        );

        $actual = json_encode($resolvedFiles, JSON_PRETTY_PRINT);
        $expected = '/Resources\/app\/storefront\/src\/scss\/skin\/shopware\/_base.scss';

        static::assertStringContainsString($expected, $actual);
    }

    public function testResolvedFilesDoNotIncludeSkinScssPath(): void
    {
        $themePluginBundle = new ThemeWithStorefrontBootstrapScss();
        $storefrontBundle = new MockStorefront();

        $factory = new StorefrontPluginConfigurationFactory(__DIR__);
        $config = $factory->createFromBundle($themePluginBundle);
        $storefront = $factory->createFromBundle($storefrontBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($storefront);

        $themeFileResolver = new ThemeFileResolver();
        $resolvedFiles = $themeFileResolver->resolveFiles(
            $config,
            $configCollection,
            false
        );

        $actual = json_encode($resolvedFiles, JSON_PRETTY_PRINT);
        $notExpected = '/Resources\/app\/storefront\/src\/scss\/skin\/shopware\/_base.scss';

        static::assertStringNotContainsString($notExpected, $actual);
    }

    public function testResolvedFilesDontContainDuplicates(): void
    {
        $themePluginBundle = new ThemeWithMultiInheritance();
        $storefrontBundle = new MockStorefront();
        $pluginBundle = new SimplePlugin();

        $factory = new StorefrontPluginConfigurationFactory(__DIR__);
        $config = $factory->createFromBundle($themePluginBundle);
        $storefront = $factory->createFromBundle($storefrontBundle);
        $plugin = $factory->createFromBundle($pluginBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($storefront);
        $configCollection->add($plugin);

        $themeFileResolver = new ThemeFileResolver();
        $resolvedFiles = $themeFileResolver->resolveFiles(
            $config,
            $configCollection,
            false
        );
        /** @var FileCollection $scriptFiles */
        $scriptFiles = $resolvedFiles['script'];
        $actual = $scriptFiles->getFilepaths();
        $expected = array_unique($scriptFiles->getFilepaths());

        static::assertEquals($expected, $actual);
    }

    public function testParentThemeIncludesPlugins(): void
    {
        $themePluginBundle = new ThemeNotIncludingPluginJsAndCss();
        $storefrontBundle = new MockStorefront();
        $pluginBundle = new SimplePlugin();

        $factory = new StorefrontPluginConfigurationFactory(__DIR__);
        $config = $factory->createFromBundle($themePluginBundle);
        $storefront = $factory->createFromBundle($storefrontBundle);
        $plugin = $factory->createFromBundle($pluginBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($storefront);
        $configCollection->add($plugin);

        $themeFileResolver = new ThemeFileResolver();
        $resolvedFiles = $themeFileResolver->resolveFiles(
            $config,
            $configCollection,
            false
        );

        /** @var FileCollection $scriptFiles */
        $scriptFiles = $resolvedFiles['script'];
        $pluginScriptFile = 'SimplePlugin/Resources/app/storefront/dist/storefront/js/main.js';
        $pluginScriptIncluded = false;

        foreach ($scriptFiles->getFilepaths() as $path) {
            if (stripos($path, $pluginScriptFile) !== false) {
                $pluginScriptIncluded = true;
            }
        }

        static::assertTrue($pluginScriptIncluded);

        /** @var FileCollection $styleFiles */
        $styleFiles = $resolvedFiles['style'];
        $pluginStyleFile = 'SimplePlugin/Resources/app/storefront/src/scss/example.scss';
        $pluginStyleIncluded = false;

        foreach ($styleFiles->getFilepaths() as $path) {
            if (stripos($path, $pluginStyleFile) !== false) {
                $pluginStyleIncluded = true;
            }
        }

        static::assertTrue($pluginStyleIncluded);
    }
}
