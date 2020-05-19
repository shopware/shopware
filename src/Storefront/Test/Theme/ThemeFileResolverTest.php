<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme;

use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Test\Theme\fixtures\MockStorefront\MockStorefront;
use Shopware\Storefront\Test\Theme\fixtures\SimplePlugin\SimplePlugin;
use Shopware\Storefront\Test\Theme\fixtures\ThemeWithMultiInheritance\ThemeWithMultiInheritance;
use Shopware\Storefront\Test\Theme\fixtures\ThemeWithStorefrontBootstrapScss\ThemeWithStorefrontBootstrapScss;
use Shopware\Storefront\Test\Theme\fixtures\ThemeWithStorefrontSkinScss\ThemeWithStorefrontSkinScss;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\ThemeFileResolver;

class ThemeFileResolverTest extends TestCase
{
    public function testResolvedFilesIncludeSkinScssPath(): void
    {
        $themePluginBundle = new ThemeWithStorefrontSkinScss();
        $storefrontBundle = new MockStorefront();

        $config = StorefrontPluginConfiguration::createFromConfigFile($themePluginBundle);
        $storefront = StorefrontPluginConfiguration::createFromConfigFile($storefrontBundle);

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

        $config = StorefrontPluginConfiguration::createFromConfigFile($themePluginBundle);
        $storefront = StorefrontPluginConfiguration::createFromConfigFile($storefrontBundle);

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

        $config = StorefrontPluginConfiguration::createFromConfigFile($themePluginBundle);
        $storefront = StorefrontPluginConfiguration::createFromConfigFile($storefrontBundle);
        $plugin = StorefrontPluginConfiguration::createFromBundle($pluginBundle);

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
}
