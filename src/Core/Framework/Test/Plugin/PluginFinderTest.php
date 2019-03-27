<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Helper\PluginFinder;

class PluginFinderTest extends TestCase
{
    public function testFailsOnMissingRootComposerFile()
    {
        $this->expectException(\InvalidArgumentException::class);
        (new PluginFinder())->findPlugins(__DIR__, __DIR__);
    }

    public function testLocalLoadsTheComposerJsonContents()
    {
        $plugins = (new PluginFinder())->findPlugins(__DIR__ . '/_finderFixtures', TEST_PROJECT_DIR);

        static::assertCount(2, $plugins);
        static::assertSame($plugins[0]->getName(), 'Works');
        static::assertSame($plugins[1]->getName(), 'Fallback');
    }
}
