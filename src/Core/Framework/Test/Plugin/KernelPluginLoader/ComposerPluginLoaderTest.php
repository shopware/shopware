<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin\KernelPluginLoader;

use Composer\InstalledVersions;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\ComposerPluginLoader;
use Shopware\Core\Framework\Plugin\Util\PluginFinder;
use Shopware\Core\Framework\Test\Plugin\PluginIntegrationTestBehaviour;

class ComposerPluginLoaderTest extends TestCase
{
    use PluginIntegrationTestBehaviour;

    /**
     * Backing up current InstalledVersions state to left it out as it was
     */
    private ?array $packages = null;

    public function setUp(): void
    {
        if (
            !method_exists(InstalledVersions::class, 'getInstalledPackagesByType')
            || !method_exists(InstalledVersions::class, 'getInstallPath')
        ) {
            static::markTestSkipped('FallbackPluginLoader does only work with Composer 2.1 or higher');
        }
        parent::setUp();

        $this->packages = InstalledVersions::getAllRawData()[0];
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->packages !== null) {
            InstalledVersions::reload($this->packages);
        }
    }

    public function testNoPlugins(): void
    {
        $before = InstalledVersions::getAllRawData();

        $modified = $before;
        $modified[0]['versions'] = [];
        InstalledVersions::reload($modified[0]);

        $loader = new ComposerPluginLoader($this->classLoader, null, []);
        $loader->initializePlugins(TEST_PROJECT_DIR);

        static::assertEmpty($loader->getPluginInfos());
        static::assertEmpty($loader->getPluginInstances()->all());
    }

    public function testWithInvalidPlugins(): void
    {
        $before = InstalledVersions::getAllRawData();

        $modified = $before;
        $modified[0]['versions'] = [
            // Points to path that does not exists
            [
                'name' => 'swag/broken1',
                'type' => PluginFinder::COMPOSER_TYPE,
                'install_path' => '/tmp/some-random-folder',
            ],
            [
                'name' => 'swag/broken1',
                'type' => PluginFinder::COMPOSER_TYPE,
                'install_path' => __DIR__ . '/../_fixture/plugins/SwagTestInvalidComposerJson',
            ],
        ];
        InstalledVersions::reload($modified[0]);

        $loader = new ComposerPluginLoader($this->classLoader, null, []);
        $loader->initializePlugins(TEST_PROJECT_DIR);

        static::assertEmpty($loader->getPluginInfos());
        static::assertEmpty($loader->getPluginInstances()->all());
    }

    public function testLoadsPlugins(): void
    {
        $before = InstalledVersions::getAllRawData();

        // We assume that the class can be find from the autoloader without modifying them
        require_once __DIR__ . '/../_fixture/plugins/SwagTestComposerLoaded/src/SwagTestComposerLoaded.php';

        $modified = $before;
        $modified[0]['versions'] = [
            [
                'name' => 'swag/composer-loaded',
                'type' => PluginFinder::COMPOSER_TYPE,
                'install_path' => __DIR__ . '/../_fixture/plugins/SwagTestComposerLoaded',
            ],
        ];
        InstalledVersions::reload($modified[0]);

        $loader = new ComposerPluginLoader($this->classLoader, null, []);
        $loader->initializePlugins(TEST_PROJECT_DIR);

        static::assertNotEmpty($loader->getPluginInfos());
        $entry = $loader->getPluginInfos()[0];

        static::assertSame('SwagTestComposerLoaded', $entry['name']);
        static::assertSame('SwagTestComposerLoaded\SwagTestComposerLoaded', $entry['baseClass']);
        static::assertTrue($entry['active']);
    }
}
