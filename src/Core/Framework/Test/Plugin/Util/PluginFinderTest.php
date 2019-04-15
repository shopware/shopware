<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin\Util;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Exception\PluginComposerJsonInvalidException;
use Shopware\Core\Framework\Plugin\Util\PluginFinder;

class PluginFinderTest extends TestCase
{
    public function testFailsOnMissingRootComposerFile(): void
    {
        $this->expectException(PluginComposerJsonInvalidException::class);
        (new PluginFinder())->findPlugins(__DIR__, __DIR__);
    }

    public function testLocalLoadsTheComposerJsonContents(): void
    {
        $plugins = (new PluginFinder())->findPlugins(__DIR__ . '/_fixture', TEST_PROJECT_DIR);

        static::assertCount(1, $plugins);
        static::assertSame($plugins['Works\Works']->getName(), 'Works\Works');
    }
}
