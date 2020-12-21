<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme\StorefrontPluginConfiguration;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;

class StorefrontPluginConfigurationFactoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var StorefrontPluginConfigurationFactory
     */
    private $configFactory;

    public function setUp(): void
    {
        $this->configFactory = $this->getContainer()->get(StorefrontPluginConfigurationFactory::class);
    }

    public function testCreateThemeConfig(): void
    {
        $basePath = realpath(__DIR__ . '/../fixtures/ThemeConfig');
        $config = $this->configFactory->createThemeConfig('TestTheme', $basePath);
        static::assertEquals('TestTheme', $config->getTechnicalName());
        static::assertEquals($basePath . '/Resources', $config->getBasePath());
        static::assertTrue($config->getIsTheme());
        static::assertEquals(
            $basePath . '/Resources/app/storefront/src/main.js',
            $config->getStorefrontEntryFilepath()
        );
        $this->assertFileCollection([
            $basePath . '/Resources/app/storefront/src/scss/overrides.scss' => [],
            '@Storefront' => [],
            $basePath . '/Resources/app/storefront/src/scss/base.scss' => [
                'vendor' => $basePath . '/Resources/app/storefront/vendor',
            ],
        ], $config->getStyleFiles());
        $this->assertFileCollection([
            '@Storefront' => [],
            $basePath . '/Resources/app/storefront/dist/js/main.js' => [],
        ], $config->getScriptFiles());
        static::assertEquals([
            '@Storefront',
            '@Plugins',
            '@SwagTheme',
        ], $config->getViewInheritance());
        static::assertEquals([
            $basePath . '/Resources/app/storefront/dist/assets',
        ], $config->getAssetPaths());
        static::assertEquals($basePath . '/Resources/app/storefront/dist/assets/preview.jpg', $config->getPreviewMedia());
        static::assertEquals([
            'fields' => [
                'sw-image' => [
                    'type' => 'media',
                    'value' => 'app/storefront/dist/assets/test.jpg',
                ],
            ],
        ], $config->getThemeConfig());
    }

    public function testPluginHasSingleScssEntryPoint(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_7365', $this);

        $basePath = realpath(__DIR__ . '/../fixtures/SimplePlugin');
        $config = $this->configFactory->createPluginConfig('SimplePlugin', $basePath);

        $this->assertFileCollection([
            $basePath . '/Resources/app/storefront/src/scss/base.scss' => [],
        ], $config->getStyleFiles());
    }

    public function testPluginHasNoScssEntryPoint(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_7365', $this);

        $basePath = realpath(__DIR__ . '/../fixtures/SimplePluginWithoutCompilation');
        $config = $this->configFactory->createPluginConfig('SimplePluginWithoutCompilation', $basePath);

        $this->assertFileCollection([], $config->getStyleFiles());
    }

    public function testPluginHasNoScssEntryPointButDifferentScssFiles(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_7365', $this);

        $basePath = realpath(__DIR__ . '/../fixtures/SimpleWithoutStyleEntryPoint');
        $config = $this->configFactory->createPluginConfig('SimpleWithoutStyleEntryPoint', $basePath);

        // Style files should still be empty because of missing base.scss
        $this->assertFileCollection([], $config->getStyleFiles());
    }

    private function assertFileCollection(array $expected, FileCollection $files): void
    {
        $flatFiles = [];
        foreach ($files as $file) {
            $flatFiles[$file->getFilepath()] = $file->getResolveMapping();
        }

        static::assertEquals($expected, $flatFiles);
    }
}
