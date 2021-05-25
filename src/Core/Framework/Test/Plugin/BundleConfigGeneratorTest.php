<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\BundleConfigGenerator;
use Shopware\Core\Framework\Plugin\BundleConfigGeneratorInterface;
use Shopware\Core\Framework\Test\App\AppSystemTestBehaviour;
use Shopware\Core\Framework\Test\App\StorefrontPluginRegistryTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class BundleConfigGeneratorTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AppSystemTestBehaviour;
    use StorefrontPluginRegistryTestBehaviour;

    /**
     * @var BundleConfigGeneratorInterface
     */
    private $configGenerator;

    public function setUp(): void
    {
        $this->configGenerator = $this->getContainer()->get(BundleConfigGenerator::class);
    }

    public function testGenerateAppConfigWithThemeAndScriptAndStylePaths(): void
    {
        $appPath = __DIR__ . '/_fixture/apps/theme/';
        $this->loadAppsFromDir($appPath);
        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');

        if (mb_strpos($appPath, $projectDir) === 0) {
            // make relative
            $appPath = ltrim(mb_substr($appPath, mb_strlen($projectDir)), '/');
        }

        $configs = $this->configGenerator->getConfig();

        static::assertArrayHasKey('SwagApp', $configs);

        $appConfig = $configs['SwagApp'];
        static::assertEquals(
            $appPath,
            $appConfig['basePath']
        );
        static::assertEquals(['Resources/views'], $appConfig['views']);
        static::assertEquals('swag-app', $appConfig['technicalName']);
        static::assertArrayNotHasKey('administration', $appConfig);

        static::assertArrayHasKey('storefront', $appConfig);
        $storefrontConfig = $appConfig['storefront'];

        static::assertEquals('Resources/app/storefront/src', $storefrontConfig['path']);
        static::assertEquals('Resources/app/storefront/src/main.js', $storefrontConfig['entryFilePath']);
        static::assertNull($storefrontConfig['webpack']);

        $expectedStyles = [
            $appPath . 'Resources/app/storefront/src/scss/base.scss',
            $appPath . 'Resources/app/storefront/src/scss/overrides.scss',
        ];

        static::assertEquals([], array_diff($expectedStyles, $storefrontConfig['styleFiles']));
    }

    public function testGenerateAppConfigWithPluginAndScriptAndStylePaths(): void
    {
        $appPath = __DIR__ . '/_fixture/apps/plugin/';
        $this->loadAppsFromDir($appPath);

        $configs = $this->configGenerator->getConfig();
        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');

        static::assertArrayHasKey('SwagApp', $configs);

        $appConfig = $configs['SwagApp'];
        static::assertEquals(
            $appPath,
            $projectDir . '/' . $appConfig['basePath']
        );
        static::assertEquals(['Resources/views'], $appConfig['views']);
        static::assertEquals('swag-app', $appConfig['technicalName']);
        static::assertArrayNotHasKey('administration', $appConfig);

        static::assertArrayHasKey('storefront', $appConfig);
        $storefrontConfig = $appConfig['storefront'];

        static::assertEquals('Resources/app/storefront/src', $storefrontConfig['path']);
        static::assertEquals('Resources/app/storefront/src/main.js', $storefrontConfig['entryFilePath']);
        static::assertNull($storefrontConfig['webpack']);

        if (mb_strpos($appPath, $projectDir) === 0) {
            // make relative
            $appPath = ltrim(mb_substr($appPath, mb_strlen($projectDir)), '/');
        }

        // Only base.scss from /_fixture/apps/plugin/ should be included
        $expectedStyles = [
            $appPath . 'Resources/app/storefront/src/scss/base.scss',
        ];

        static::assertEquals($expectedStyles, $storefrontConfig['styleFiles']);
    }

    public function testGenerateAppConfigIgnoresInactiveApps(): void
    {
        $appPath = __DIR__ . '/_fixture/apps/theme/';
        $this->loadAppsFromDir($appPath, false);

        $configs = $this->configGenerator->getConfig();

        static::assertArrayNotHasKey('SwagApp', $configs);
    }

    public function testGenerateAppConfigWithWebpackConfig(): void
    {
        $appPath = __DIR__ . '/_fixture/apps/with-webpack/';
        $this->loadAppsFromDir($appPath);

        $configs = $this->configGenerator->getConfig();

        static::assertArrayHasKey('SwagTest', $configs);

        $appConfig = $configs['SwagTest'];
        static::assertEquals(
            $appPath,
            $this->getContainer()->getParameter('kernel.project_dir') . '/' . $appConfig['basePath']
        );
        static::assertEquals(['Resources/views'], $appConfig['views']);
        static::assertEquals('swag-test', $appConfig['technicalName']);
        static::assertArrayNotHasKey('administration', $appConfig);

        static::assertArrayHasKey('storefront', $appConfig);
        $storefrontConfig = $appConfig['storefront'];

        static::assertEquals('Resources/app/storefront/src', $storefrontConfig['path']);
        static::assertNull($storefrontConfig['entryFilePath']);
        static::assertEquals('Resources/app/storefront/build/webpack.config.js', $storefrontConfig['webpack']);
        static::assertEquals([], $storefrontConfig['styleFiles']);
    }
}
