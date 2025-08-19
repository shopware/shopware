<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\DataTransfer\PluginMapping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\DataTransfer\PluginMapping\PluginMapping;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(PluginMapping::class)]
class PluginMappingTest extends TestCase
{
    public function testConstruction(): void
    {
        $pluginMapping = new PluginMapping('TestPlugin', 'TestTranslation');

        static::assertSame('TestPlugin', $pluginMapping->pluginName);
        static::assertSame('TestTranslation', $pluginMapping->snippetName);
    }
}
