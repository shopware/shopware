<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme\StorefrontPluginConfiguration;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Framework\ThemeInterface;
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

        $theme = $this->getBundle('TestTheme', $basePath, true);
        $config = $this->configFactory->createFromBundle($theme);

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
        static::assertEquals([
            'custom-icons' => 'app/storefront/src/assets/icon-pack/custom-icons',
        ], $config->getIconSets());
    }

    public function testPluginHasSingleScssEntryPoint(): void
    {
        $basePath = realpath(__DIR__ . '/../fixtures/SimplePlugin');
        $bundle = $this->getBundle('SimplePlugin', $basePath);

        $config = $this->configFactory->createFromBundle($bundle);

        $this->assertFileCollection([
            $basePath . '/Resources/app/storefront/src/scss/base.scss' => [],
        ], $config->getStyleFiles());
    }

    public function testPluginHasNoScssEntryPoint(): void
    {
        $basePath = realpath(__DIR__ . '/../fixtures/SimplePluginWithoutCompilation');

        $bundle = $this->getBundle('SimplePluginWithoutCompilation', $basePath);
        $config = $this->configFactory->createFromBundle($bundle);

        $this->assertFileCollection([], $config->getStyleFiles());
    }

    public function testPluginHasNoScssEntryPointButDifferentScssFiles(): void
    {
        $basePath = realpath(__DIR__ . '/../fixtures/SimpleWithoutStyleEntryPoint');

        $bundle = $this->getBundle('SimpleWithoutStyleEntryPoint', $basePath);

        $config = $this->configFactory->createFromBundle($bundle);

        // Style files should still be empty because of missing base.scss
        $this->assertFileCollection([], $config->getStyleFiles());
    }

    private function getBundle(string $name, string $basePath, bool $isTheme = false)
    {
        if ($isTheme) {
            return new class($name, $basePath) extends Bundle implements ThemeInterface {
                public function __construct($name, $basePath)
                {
                    $this->name = $name;
                    $this->path = $basePath;
                }
            };
        } else {
            return new class($name, $basePath) extends Bundle {
                public function __construct($name, $basePath)
                {
                    $this->name = $name;
                    $this->path = $basePath;
                }
            };
        }
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
