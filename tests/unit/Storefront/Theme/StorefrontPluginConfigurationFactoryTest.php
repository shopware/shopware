<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\PluginWithAdditionalBundles\PluginWithAdditionalBundles;
use Shopware\Tests\Unit\Storefront\Theme\fixtures\ThemeAndPlugin\TestTheme\TestTheme;

/**
 * @internal
 */
#[CoversClass(StorefrontPluginConfigurationFactory::class)]
class StorefrontPluginConfigurationFactoryTest extends TestCase
{
    public function testFactorySetsConfiguration(): void
    {
        $projectDir = 'tests/unit/Storefront/Theme/fixtures';

        $configurationFactory = new StorefrontPluginConfigurationFactory(
            $projectDir,
            $this->createMock(KernelPluginLoader::class)
        );

        $themePluginBundle = new TestTheme();

        $config = $configurationFactory->createFromBundle($themePluginBundle);

        static::assertEquals('TestTheme', $config->getName());
        static::assertEquals(
            [
                'name' => 'TestTheme',
                'author' => 'Shopware AG',
                'views' => [
                    '@Storefront',
                    '@Plugins',
                    '@TestTheme',
                ],
                'style' => [
                    'app/storefront/src/scss/overrides.scss',
                    '@Storefront',
                    'app/storefront/src/scss/base.scss',
                ],
                'script' => [
                    '@Storefront',
                    'app/storefront/dist/storefront/js/test-theme/test-theme.js',
                ],
                'asset' => [],
            ],
            $config->getThemeJson()
        );
        static::assertEmpty($config->getThemeConfig());
        static::assertTrue($config->getIsTheme());
        static::assertCount(3, $config->getStyleFiles());
        static::assertCount(2, $config->getScriptFiles());
        static::assertFalse($config->hasAdditionalBundles());
    }

    public function testFactorySetsConfigurationWithAdditionalBundles(): void
    {
        $projectDir = 'tests/unit/Storefront/Theme/fixtures';

        $configurationFactory = new StorefrontPluginConfigurationFactory(
            $projectDir,
            $this->createMock(KernelPluginLoader::class)
        );

        $PluginSubBundle = new PluginWithAdditionalBundles(true, '');

        $config = $configurationFactory->createFromBundle($PluginSubBundle);

        static::assertTrue($config->hasAdditionalBundles());
    }
}
