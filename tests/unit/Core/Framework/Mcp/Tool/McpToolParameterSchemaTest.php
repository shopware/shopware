<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use Mcp\Capability\Discovery\DocBlockParser;
use Mcp\Capability\Discovery\SchemaGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Mcp\Tool\EntityAggregateTool;
use Shopware\Core\Framework\Mcp\Tool\EntityReadTool;
use Shopware\Core\Framework\Mcp\Tool\EntitySearchTool;
use Shopware\Core\Framework\Mcp\Tool\EntityUpsertTool;
use Shopware\Storefront\Mcp\Tool\ThemeConfigTool;

/**
 * @internal
 */
#[CoversClass(EntityAggregateTool::class)]
#[CoversClass(EntityReadTool::class)]
#[CoversClass(EntitySearchTool::class)]
#[CoversClass(EntityUpsertTool::class)]
#[CoversClass(ThemeConfigTool::class)]
class McpToolParameterSchemaTest extends TestCase
{
    /**
     * @param class-string $toolClass
     */
    #[DataProvider('toolProvider')]
    #[TestDox('Every $toolClass parameter carries a description into the SDK-generated input schema')]
    public function testEveryParameterIsDescribedInTheInputSchema(string $toolClass): void
    {
        $method = new \ReflectionMethod($toolClass, '__invoke');
        $schema = (new SchemaGenerator(new DocBlockParser()))->generate($method);

        static::assertIsArray($schema['properties']);
        static::assertCount(\count($method->getParameters()), $schema['properties']);

        foreach ($schema['properties'] as $name => $property) {
            static::assertIsArray($property);
            static::assertArrayHasKey('description', $property, \sprintf('%s::$%s has no description', $toolClass, $name));
            static::assertIsString($property['description']);
            static::assertNotSame('', $property['description'], \sprintf('%s::$%s has an empty description', $toolClass, $name));
        }
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function toolProvider(): iterable
    {
        yield 'entity-search' => [EntitySearchTool::class];
        yield 'entity-read' => [EntityReadTool::class];
        yield 'entity-aggregate' => [EntityAggregateTool::class];
        yield 'entity-upsert' => [EntityUpsertTool::class];
        yield 'theme-config' => [ThemeConfigTool::class];
    }
}
