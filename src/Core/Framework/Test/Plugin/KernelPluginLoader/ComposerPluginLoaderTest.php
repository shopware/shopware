<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin\KernelPluginLoader;

use Composer\InstalledVersions;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\ComposerPluginLoader;
use Shopware\Core\Framework\Plugin\Util\PluginFinder;
use Shopware\Core\Framework\Test\Plugin\PluginIntegrationTestBehaviour;
use SwagTestComposerLoaded\SwagTestComposerLoaded;

/**
 * @internal
 *
 * @phpstan-type ComposerPackages array{root: array{name: string, pretty_version: string, version: string, reference: string|null, type: string, install_path: string, aliases: string[], dev: bool}, versions: array<string, array{pretty_version?: string, version?: string, reference?: string|null, type?: string, install_path?: string, aliases?: string[], dev_requirement: bool, replaced?: string[], provided?: string[]}>}
 */
class ComposerPluginLoaderTest extends TestCase
{
    use PluginIntegrationTestBehaviour;

    /**
     * Backing up current InstalledVersions state to left it out as it was
     *
     * @var ComposerPackages|null
     */
    private ?array $packages = null;

    protected function setUp(): void
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
        $packages = InstalledVersions::getAllRawData();

        $modified = $packages[0];
        static::assertIsArray($modified);
        $modified['versions'] = [
            // Points to path that does not exists
            'swag/broken1' => [
                'dev_requirement' => false,
                'type' => PluginFinder::COMPOSER_TYPE,
                'install_path' => '/tmp/some-random-folder',
            ],
            'swag/broken2' => [
                'dev_requirement' => false,
                'type' => PluginFinder::COMPOSER_TYPE,
                'install_path' => __DIR__ . '/../_fixture/plugins/SwagTestInvalidComposerJson',
            ],
        ];

        InstalledVersions::reload($modified);

        $loader = new ComposerPluginLoader($this->classLoader, null, []);
        $loader->initializePlugins(TEST_PROJECT_DIR);

        static::assertEmpty($loader->getPluginInfos());
        static::assertEmpty($loader->getPluginInstances()->all());
    }

    public function testLoadsPlugins(): void
    {
        // We assume that the class can be found from the autoloader without modifying them
        require_once __DIR__ . '/../_fixture/plugins/SwagTestComposerLoaded/src/SwagTestComposerLoaded.php';

        $packages = InstalledVersions::getAllRawData();

        $modified = $packages[0];
        static::assertIsArray($modified);
        $modified['versions'] = [
            'swag/composer-loaded' => [
                'dev_requirement' => false,
                'type' => PluginFinder::COMPOSER_TYPE,
                'install_path' => __DIR__ . '/../_fixture/plugins/SwagTestComposerLoaded',
            ],
        ];

        InstalledVersions::reload($modified);

        $loader = new ComposerPluginLoader($this->classLoader, null, []);
        $loader->initializePlugins(TEST_PROJECT_DIR);

        static::assertNotEmpty($loader->getPluginInfos());
        $entry = $loader->getPluginInfos()[0];

        static::assertSame('SwagTestComposerLoaded', $entry['name']);
        static::assertSame(SwagTestComposerLoaded::class, $entry['baseClass']);
        static::assertTrue($entry['active']);
    }
}
