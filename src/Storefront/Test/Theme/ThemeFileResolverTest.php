<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Storefront\Test\Theme\fixtures\MockStorefront\MockStorefront;
use Shopware\Storefront\Test\Theme\fixtures\SimplePlugin\SimplePlugin;
use Shopware\Storefront\Test\Theme\fixtures\ThemeNotIncludingPluginJsAndCss\ThemeNotIncludingPluginJsAndCss;
use Shopware\Storefront\Test\Theme\fixtures\ThemeWithMultiInheritance\ThemeWithMultiInheritance;
use Shopware\Storefront\Test\Theme\fixtures\ThemeWithStorefrontBootstrapScss\ThemeWithStorefrontBootstrapScss;
use Shopware\Storefront\Test\Theme\fixtures\ThemeWithStorefrontSkinScss\ThemeWithStorefrontSkinScss;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\ThemeFileImporter;
use Shopware\Storefront\Theme\ThemeFileResolver;

/**
 * @internal
 */
class ThemeFileResolverTest extends TestCase
{
    use KernelTestBehaviour;

    public function testResolvedFilesIncludeSkinScssPath(): void
    {
        $themePluginBundle = new ThemeWithStorefrontSkinScss();
        $storefrontBundle = new MockStorefront();

        $factory = new StorefrontPluginConfigurationFactory($this->getContainer()->getParameter('kernel.project_dir'));
        $config = $factory->createFromBundle($themePluginBundle);
        $storefront = $factory->createFromBundle($storefrontBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($storefront);

        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');

        $themeFileResolver = new ThemeFileResolver(new ThemeFileImporter($projectDir));
        $resolvedFiles = $themeFileResolver->resolveFiles(
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

        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');

        $factory = new StorefrontPluginConfigurationFactory($projectDir);
        $config = $factory->createFromBundle($themePluginBundle);
        $storefront = $factory->createFromBundle($storefrontBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($storefront);

        $themeFileResolver = new ThemeFileResolver(new ThemeFileImporter($projectDir));
        $resolvedFiles = $themeFileResolver->resolveFiles(
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

        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');

        $factory = new StorefrontPluginConfigurationFactory($projectDir);
        $config = $factory->createFromBundle($themePluginBundle);
        $storefront = $factory->createFromBundle($storefrontBundle);
        $plugin = $factory->createFromBundle($pluginBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($storefront);
        $configCollection->add($plugin);

        $themeFileResolver = new ThemeFileResolver(new ThemeFileImporter($projectDir));
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
        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');

        $themePluginBundle = new ThemeNotIncludingPluginJsAndCss();
        $storefrontBundle = new MockStorefront();
        $pluginBundle = new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin');

        $factory = new StorefrontPluginConfigurationFactory($projectDir);
        $config = $factory->createFromBundle($themePluginBundle);
        $storefront = $factory->createFromBundle($storefrontBundle);
        $plugin = $factory->createFromBundle($pluginBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($storefront);
        $configCollection->add($plugin);

        $themeFileResolver = new ThemeFileResolver(new ThemeFileImporter($projectDir));
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
            if (mb_stripos((string) $path, $pluginScriptFile) !== false) {
                $pluginScriptIncluded = true;

                break;
            }
        }

        static::assertTrue($pluginScriptIncluded);

        /** @var FileCollection $styleFiles */
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

        $factory = new StorefrontPluginConfigurationFactory($this->getContainer()->getParameter('kernel.project_dir'));
        $config = $factory->createFromBundle($themePluginBundle);
        $storefront = $factory->createFromBundle($storefrontBundle);

        $configCollection = new StorefrontPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($storefront);

        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');
        $firstFile = $config->getStyleFiles()->first();
        $currentPath = $firstFile ? $firstFile->getFilepath() : '';

        (new ThemeFileResolver(new ThemeFileImporter($projectDir)))->resolveFiles(
            $config,
            $configCollection,
            false
        );

        // Path is still relative
        static::assertSame(
            $currentPath,
            $config->getStyleFiles()->first() ? $config->getStyleFiles()->first()->getFilepath() : ''
        );

        $config->setScriptFiles(new FileCollection());
        $config->setStorefrontEntryFilepath(__FILE__);

        (new ThemeFileResolver(new ThemeFileImporter($projectDir)))->resolveFiles(
            $config,
            $configCollection,
            true
        );

        static::assertSame(
            $currentPath,
            $config->getStyleFiles()->first() ? $config->getStyleFiles()->first()->getFilepath() : ''
        );
    }
}
