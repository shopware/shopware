<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Plugin;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use SwagTestPlugin\SwagTestPlugin;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class PluginTest extends TestCase
{
    private Filesystem $filesystem;

    private string $symlinkedPluginPath;

    protected function setUp(): void
    {
        $pluginPath = realpath(__DIR__ . '/_fixtures/plugins/SwagTestPlugin');
        static::assertIsString($pluginPath);
        require_once $pluginPath . '/src/SwagTestPlugin.php';

        $this->filesystem = new Filesystem();
        $this->symlinkedPluginPath = sys_get_temp_dir() . '/' . uniqid('SymlinkedSwagTest_', true);
        $this->filesystem->symlink($pluginPath, $this->symlinkedPluginPath);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->symlinkedPluginPath);
    }

    public function testGetPathWithSymlinkedPlugin(): void
    {
        $plugin = new SwagTestPlugin(true, $this->symlinkedPluginPath);

        static::assertSame($this->symlinkedPluginPath . '/src', $plugin->getPath());
        static::assertSame($this->symlinkedPluginPath, $plugin->getBasePath());
    }
}
