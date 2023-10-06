<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin;

use PHPUnit\Framework\TestCase;
use SwagTest\SwagTest;

/**
 * @internal
 */
class PluginTest extends TestCase
{
    /**
     * @var string
     */
    private static $swagTestPluginPath;

    private static string $symlinkedSwagTestPluginPath;

    public static function setUpBeforeClass(): void
    {
        $pluginsDir = __DIR__ . '/_fixture/plugins';
        self::$swagTestPluginPath = $pluginsDir . '/SwagTest';

        self::$symlinkedSwagTestPluginPath = sys_get_temp_dir() . '/SymlinkedSwagTest_' . uniqid();
        symlink(self::$swagTestPluginPath, self::$symlinkedSwagTestPluginPath);

        require_once self::$swagTestPluginPath . '/src/SwagTest.php';
    }

    public static function tearDownAfterClass(): void
    {
        if (file_exists(self::$symlinkedSwagTestPluginPath) && is_link(self::$symlinkedSwagTestPluginPath)) {
            unlink(self::$symlinkedSwagTestPluginPath);
        }
    }

    public function testGetPathWithNonSymlinkedPlugin(): void
    {
        $plugin = new SwagTest(true, self::$swagTestPluginPath);

        static::assertEquals(self::$swagTestPluginPath . '/src', $plugin->getPath());
    }

    public function testGetPathWithSymlinkedPlugin(): void
    {
        $plugin = new SwagTest(true, self::$symlinkedSwagTestPluginPath);

        static::assertEquals(self::$symlinkedSwagTestPluginPath . '/src', $plugin->getPath());
    }

    public function testGetBasePath(): void
    {
        $plugin = new SwagTest(true, self::$symlinkedSwagTestPluginPath);

        static::assertEquals(self::$symlinkedSwagTestPluginPath, $plugin->getBasePath());
    }

    public function testGetBasePathIncludingSlash(): void
    {
        $plugin = new SwagTest(true, 'somePlugin', '/www/');

        static::assertEquals('/www/somePlugin', $plugin->getBasePath());
    }
}
