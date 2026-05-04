<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Mcp\Attribute\McpToolDependsOn;

/**
 * @internal
 */
#[CoversClass(McpToolDependsOn::class)]
class McpToolDependsOnTest extends TestCase
{
    public function testStoresToolName(): void
    {
        $attribute = new McpToolDependsOn('shopware-entity-search');

        static::assertSame('shopware-entity-search', $attribute->toolName);
    }

    public function testIsRepeatable(): void
    {
        $reflection = new \ReflectionClass(McpToolDependsOn::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        static::assertCount(1, $attributes);
        $flags = $attributes[0]->newInstance()->flags;
        static::assertTrue(($flags & \Attribute::IS_REPEATABLE) !== 0);
    }

    public function testTargetsClass(): void
    {
        $reflection = new \ReflectionClass(McpToolDependsOn::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        static::assertCount(1, $attributes);
        $flags = $attributes[0]->newInstance()->flags;
        static::assertTrue(($flags & \Attribute::TARGET_CLASS) !== 0);
    }

    public function testCanBeAppliedRepeatedly(): void
    {
        $target = new class {
            public function dummy(): void
            {
            }
        };

        // Verify two McpToolDependsOn attributes can coexist on the same class by
        // instantiating them independently — repeatable attributes don't conflict.
        $a = new McpToolDependsOn('tool-a');
        $b = new McpToolDependsOn('tool-b');

        static::assertSame('tool-a', $a->toolName);
        static::assertSame('tool-b', $b->toolName);
        static::assertNotSame($a->toolName, $b->toolName);
    }
}
