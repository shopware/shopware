<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;

/**
 * @internal
 */
#[CoversClass(McpToolGroup::class)]
class McpToolGroupTest extends TestCase
{
    public function testStoresGroupName(): void
    {
        $attribute = new McpToolGroup('catalogue');

        static::assertSame('catalogue', $attribute->group);
    }
}
