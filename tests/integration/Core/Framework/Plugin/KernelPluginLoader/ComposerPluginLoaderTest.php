<?php
declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Plugin\KernelPluginLoader;

use Composer\InstalledVersions;
use PHPUnit\Framework\Attributes\CoversClass;
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
#[CoversClass(ComposerPluginLoader::class)]
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

        $loader = new ComposerPluginLoader($this->classLoader, null);
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

        $loader = new ComposerPluginLoader($this->classLoader, null);
        $loader->initializePlugins(TEST_PROJECT_DIR);

        static::assertEmpty($loader->getPluginInfos());
        static::assertEmpty($loader->getPluginInstances()->all());
    }

    public function testLoadsPlugins(): void
    {
        $this->loadComposerLoadedPluginFixture();

        $loader = new ComposerPluginLoader($this->classLoader, null);
        $loader->initializePlugins(TEST_PROJECT_DIR);

        static::assertNotEmpty($loader->getPluginInfos());
        $entry = $loader->getPluginInfos()[0];

        static::assertSame('SwagTestComposerLoaded', $entry['name']);
        static::assertSame(SwagTestComposerLoaded::class, $entry['baseClass']);
        static::assertTrue($entry['active']);
    }

    public function testFetchPluginInfos(): void
    {
        $this->loadComposerLoadedPluginFixture();

        $loader = new ComposerPluginLoader($this->classLoader, null);
        $plugins = $loader->fetchPluginInfos();

        static::assertNotEmpty($plugins);

        static::assertSame('SwagTestComposerLoaded', $plugins[0]['name']);
        static::assertSame(SwagTestComposerLoaded::class, $plugins[0]['baseClass']);
        static::assertTrue($plugins[0]['active']);
    }

    private function loadComposerLoadedPluginFixture(): void
    {
        // We assume that the class can be found from the autoloader without modifying them
        require_once __DIR__ . '/../_fixtures/plugins/SwagTestComposerLoaded/src/SwagTestComposerLoaded.php';

        $packages = InstalledVersions::getAllRawData();

        $modified = $packages[0];
        static::assertIsArray($modified);
        $modified['versions'] = [
            'swag/composer-loaded' => [
                'dev_requirement' => false,
                'type' => PluginFinder::COMPOSER_TYPE,
                'install_path' => __DIR__ . '/../_fixtures/plugins/SwagTestComposerLoaded',
            ],
        ];

        InstalledVersions::reload($modified);
    }
}
