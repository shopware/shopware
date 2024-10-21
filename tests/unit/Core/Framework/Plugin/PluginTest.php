<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SwagTestPlugin\SwagTestPlugin;

/**
 * @internal
 */
#[CoversClass(SwagTestPlugin::class)]
class PluginTest extends TestCase
{
    private static string $swagTestPluginPath;

    private static string $symlinkedSwagTestPluginPath;

    public static function setUpBeforeClass(): void
    {
        $pluginsDir = __DIR__ . '/../../../../../src/Core/Framework/Test/Plugin/_fixture/plugins/';
        self::$swagTestPluginPath = $pluginsDir . '/SwagTestPlugin';

        self::$symlinkedSwagTestPluginPath = sys_get_temp_dir() . '/SymlinkedSwagTest_' . uniqid();
        symlink(self::$swagTestPluginPath, self::$symlinkedSwagTestPluginPath);

        require_once self::$swagTestPluginPath . '/src/SwagTestPlugin.php';
    }

    public static function tearDownAfterClass(): void
    {
        if (file_exists(self::$symlinkedSwagTestPluginPath) && is_link(self::$symlinkedSwagTestPluginPath)) {
            unlink(self::$symlinkedSwagTestPluginPath);
        }
    }

    public function testGetPathWithNonSymlinkedPlugin(): void
    {
        $plugin = new SwagTestPlugin(true, self::$swagTestPluginPath);

        static::assertEquals(self::$swagTestPluginPath . '/src', $plugin->getPath());
    }

    public function testGetPathWithSymlinkedPlugin(): void
    {
        $plugin = new SwagTestPlugin(true, self::$symlinkedSwagTestPluginPath);

        static::assertEquals(self::$symlinkedSwagTestPluginPath . '/src', $plugin->getPath());
    }

    public function testGetBasePath(): void
    {
        $plugin = new SwagTestPlugin(true, self::$symlinkedSwagTestPluginPath);

        static::assertEquals(self::$symlinkedSwagTestPluginPath, $plugin->getBasePath());
    }

    public function testGetBasePathIncludingSlash(): void
    {
        $plugin = new SwagTestPlugin(true, 'somePlugin', '/www/');

        static::assertEquals('/www/somePlugin', $plugin->getBasePath());
    }
}
