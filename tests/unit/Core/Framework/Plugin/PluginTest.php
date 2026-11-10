<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use SwagTestPlugin\SwagTestPlugin;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Plugin::class)]
class PluginTest extends TestCase
{
    private static string $swagTestPluginPath;

    public static function setUpBeforeClass(): void
    {
        $pluginsDir = __DIR__ . '/../../../../../tests/integration/Core/Framework/Plugin/_fixtures/plugins/';
        $swagTestPluginPath = realpath($pluginsDir . '/SwagTestPlugin');
        static::assertIsString($swagTestPluginPath);
        self::$swagTestPluginPath = $swagTestPluginPath;

        require_once self::$swagTestPluginPath . '/src/SwagTestPlugin.php';
    }

    public function testGetPathWithNonSymlinkedPlugin(): void
    {
        $plugin = new SwagTestPlugin(true, self::$swagTestPluginPath);

        static::assertSame(self::$swagTestPluginPath . '/src', $plugin->getPath());
    }

    public function testGetBasePath(): void
    {
        $plugin = new SwagTestPlugin(true, self::$swagTestPluginPath);

        static::assertSame(self::$swagTestPluginPath, $plugin->getBasePath());
    }

    public function testGetBasePathIncludingSlash(): void
    {
        $plugin = new SwagTestPlugin(true, 'somePlugin', '/www/');

        static::assertSame('/www/somePlugin', $plugin->getBasePath());
    }

    public function testGetPathWithTrailingSlashBasePath(): void
    {
        $plugin = new SwagTestPlugin(true, self::$swagTestPluginPath . '/');

        static::assertSame(self::$swagTestPluginPath . '/src', $plugin->getPath());
        static::assertStringNotContainsString('//', $plugin->getPath());
    }
}
