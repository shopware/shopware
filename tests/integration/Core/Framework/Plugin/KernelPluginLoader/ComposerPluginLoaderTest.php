<?php
declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Plugin\KernelPluginLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Composer\ComposerInfoProvider;
use Shopware\Core\Framework\Adapter\Composer\ComposerPackage;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\ComposerPluginLoader;
use Shopware\Core\Framework\Test\Plugin\PluginIntegrationTestBehaviour;
use SwagTestComposerLoaded\SwagTestComposerLoaded;

/**
 * @internal
 */
#[CoversClass(ComposerPluginLoader::class)]
class ComposerPluginLoaderTest extends TestCase
{
    use PluginIntegrationTestBehaviour;

    protected function tearDown(): void
    {
        parent::tearDown();
        ComposerInfoProvider::reset();
    }

    public function testNoPlugins(): void
    {
        ComposerInfoProvider::fake([]);

        $loader = new ComposerPluginLoader($this->classLoader, null);
        $loader->initializePlugins(TEST_PROJECT_DIR);

        static::assertEmpty($loader->getPluginInfos());
        static::assertEmpty($loader->getPluginInstances()->all());
    }

    public function testWithInvalidPlugins(): void
    {
        ComposerInfoProvider::fake([
            new ComposerPackage(
                name: 'swag/broken1',
                version: '1.0.0',
                prettyVersion: '1.0.0.0',
                path: '/tmp/some-random-folder',
            ),
            new ComposerPackage(
                name: 'swag/broken2',
                version: '1.0.0',
                prettyVersion: '1.0.0.0',
                path: __DIR__ . '/../_fixture/plugins/SwagTestInvalidComposerJson',
            ),
        ]);

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

        $entry = array_find($loader->getPluginInfos(), fn (array $plugin) => $plugin['name'] === 'SwagTestComposerLoaded');
        static::assertNotNull($entry);

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

        $pluginNames = array_column($plugins, 'name');
        static::assertContains('SwagTestComposerLoaded', $pluginNames);

        $pluginBaseClasses = array_column($plugins, 'baseClass');
        static::assertContains(SwagTestComposerLoaded::class, $pluginBaseClasses);
    }

    private function loadComposerLoadedPluginFixture(): void
    {
        // We assume that the class can be found from the autoloader without modifying them
        require_once __DIR__ . '/../_fixtures/plugins/SwagTestComposerLoaded/src/SwagTestComposerLoaded.php';

        ComposerInfoProvider::fake([
            new ComposerPackage(
                name: 'swag/composer-loaded',
                version: '1.0.0',
                prettyVersion: '1.0.0.0',
                path: __DIR__ . '/../_fixtures/plugins/SwagTestComposerLoaded',
            ),
        ]);
    }
}
