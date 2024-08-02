<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Theme\StorefrontPluginConfiguration;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Framework\ThemeInterface;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\AbstractStorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;

/**
 * @internal
 */
class StorefrontPluginConfigurationFactoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    private AbstractStorefrontPluginConfigurationFactory $configFactory;

    protected function setUp(): void
    {
        $this->configFactory = $this->getContainer()->get(StorefrontPluginConfigurationFactory::class);
    }

    public function testCreateThemeConfig(): void
    {
        $basePath = realpath(__DIR__ . '/../fixtures/ThemeConfig');
        static::assertIsString($basePath);

        $theme = $this->getBundle('TestTheme', $basePath, true);
        $config = $this->configFactory->createFromBundle($theme);

        $basePath = $this->stripProjectDir($basePath);

        static::assertEquals('TestTheme', $config->getTechnicalName());
        static::assertEquals($basePath, $config->getBasePath());
        static::assertTrue($config->getIsTheme());
        static::assertEquals(
            'app/storefront/src/main.js',
            $config->getStorefrontEntryFilepath()
        );
        $this->assertFileCollection([
            'app/storefront/src/scss/overrides.scss' => [],
            '@Storefront' => [],
            'app/storefront/src/scss/base.scss' => [
                'vendor' => 'app/storefront/vendor',
            ],
        ], $config->getStyleFiles());
        $this->assertFileCollection([
            '@Storefront' => [],
            'app/storefront/dist/js/main.js' => [],
        ], $config->getScriptFiles());
        static::assertEquals([
            '@Storefront',
            '@Plugins',
            '@SwagTheme',
        ], $config->getViewInheritance());
        static::assertEquals(['app/storefront/dist/assets'], $config->getAssetPaths());
        static::assertEquals('app/storefront/dist/assets/preview.jpg', $config->getPreviewMedia());
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
        static::assertIsString($basePath);
        $bundle = $this->getBundle('SimplePlugin', $basePath);

        $config = $this->configFactory->createFromBundle($bundle);

        $this->assertFileCollection(['app/storefront/src/scss/base.scss' => []], $config->getStyleFiles());
    }

    public function testPluginHasNoScssEntryPoint(): void
    {
        $basePath = realpath(__DIR__ . '/../fixtures/SimplePluginWithoutCompilation');
        static::assertIsString($basePath);

        $bundle = $this->getBundle('SimplePluginWithoutCompilation', $basePath);
        $config = $this->configFactory->createFromBundle($bundle);

        $this->assertFileCollection([], $config->getStyleFiles());
    }

    public function testPluginHasNoScssEntryPointButDifferentScssFiles(): void
    {
        $basePath = realpath(__DIR__ . '/../fixtures/SimpleWithoutStyleEntryPoint');
        static::assertIsString($basePath);

        $bundle = $this->getBundle('SimpleWithoutStyleEntryPoint', $basePath);

        $config = $this->configFactory->createFromBundle($bundle);

        // Style files should still be empty because of missing base.scss
        $this->assertFileCollection([], $config->getStyleFiles());
    }

    private function getBundle(string $name, string $basePath, bool $isTheme = false): Bundle
    {
        if ($isTheme) {
            return new class($name, $basePath) extends Bundle implements ThemeInterface {
                public function __construct(
                    string $name,
                    string $basePath
                ) {
                    $this->name = $name;
                    $this->path = $basePath;
                }
            };
        }

        return new class($name, $basePath) extends Bundle {
            public function __construct(
                string $name,
                string $basePath
            ) {
                $this->name = $name;
                $this->path = $basePath;
            }
        };
    }

    /**
     * @param array<string, array<string, string>> $expected
     */
    private function assertFileCollection(array $expected, FileCollection $files): void
    {
        $flatFiles = [];
        foreach ($files as $file) {
            $flatFiles[$file->getFilepath()] = $file->getResolveMapping();
        }

        static::assertEquals($expected, $flatFiles);
    }

    private function stripProjectDir(string $path): string
    {
        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');

        if (str_starts_with($path, $projectDir)) {
            return substr($path, \strlen($projectDir) + 1);
        }

        return $path;
    }
}
