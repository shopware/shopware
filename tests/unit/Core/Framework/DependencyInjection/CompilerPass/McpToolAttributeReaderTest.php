<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\McpToolAttributeReader;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolAttributeReader::class)]
class McpToolAttributeReaderTest extends TestCase
{
    public function testNonExistentClassReturnsNull(): void
    {
        $result = McpToolAttributeReader::resolveInfo(
            'App\\NonExistent\\ToolClass',
            McpTool::class,
            ['name', 'description']
        );

        static::assertNull($result);
    }

    public function testClassWithNoAttributeOnClassOrInvokeReturnsNull(): void
    {
        $result = McpToolAttributeReader::resolveInfo(
            McpAttributeReaderTestNoAttribute::class,
            McpTool::class,
            ['name', 'description']
        );

        static::assertNull($result);
    }

    public function testClassLevelAttributeReturnsFields(): void
    {
        $result = McpToolAttributeReader::resolveInfo(
            McpAttributeReaderTestClassLevel::class,
            McpTool::class,
            ['name', 'description']
        );

        static::assertNotNull($result);
        static::assertSame('reader-class-level-tool', $result['name']);
        static::assertSame('class-level description', $result['description']);
    }

    public function testMethodLevelAttributeIsUsedAsFallbackWhenNoClassLevelAttribute(): void
    {
        $result = McpToolAttributeReader::resolveInfo(
            McpAttributeReaderTestMethodLevel::class,
            McpTool::class,
            ['name', 'description']
        );

        static::assertNotNull($result);
        static::assertSame('reader-method-level-tool', $result['name']);
        static::assertSame('method-level description', $result['description']);
    }

    public function testClassLevelAttributeTakesPrecedenceOverInvokeLevel(): void
    {
        $result = McpToolAttributeReader::resolveInfo(
            McpAttributeReaderTestBothLevels::class,
            McpTool::class,
            ['name', 'description']
        );

        static::assertNotNull($result);
        static::assertSame('reader-class-wins', $result['name']);
        static::assertSame('class description wins', $result['description']);
    }

    public function testClassWithNoInvokeAndNoClassAttributeReturnsNull(): void
    {
        $result = McpToolAttributeReader::resolveInfo(
            McpAttributeReaderTestNoInvokeNoAttribute::class,
            McpTool::class,
            ['name', 'description']
        );

        static::assertNull($result);
    }

    public function testRequestedFieldNotPresentOnAttributeReturnsNullForThatField(): void
    {
        $result = McpToolAttributeReader::resolveInfo(
            McpAttributeReaderTestClassLevel::class,
            McpTool::class,
            ['name', 'nonExistentField']
        );

        static::assertNotNull($result);
        static::assertSame('reader-class-level-tool', $result['name']);
        static::assertNull($result['nonExistentField']);
    }

    public function testResolveAttributeReturnsNullForNonExistentClass(): void
    {
        static::assertNull(McpToolAttributeReader::resolveAttribute('App\\NonExistent\\ToolClass', McpTool::class));
    }

    public function testResolveAttributeReturnsNullWhenAttributeAbsent(): void
    {
        static::assertNull(McpToolAttributeReader::resolveAttribute(McpAttributeReaderTestNoAttribute::class, McpTool::class));
    }

    public function testResolveAttributeReturnsNullWhenClassHasNoInvokeAndNoAttribute(): void
    {
        static::assertNull(McpToolAttributeReader::resolveAttribute(McpAttributeReaderTestNoInvokeNoAttribute::class, McpTool::class));
    }

    public function testResolveAttributeReturnsClassLevelInstance(): void
    {
        $tool = McpToolAttributeReader::resolveAttribute(McpAttributeReaderTestClassLevel::class, McpTool::class);

        static::assertInstanceOf(McpTool::class, $tool);
        static::assertSame('reader-class-level-tool', $tool->name);
        static::assertSame('class-level description', $tool->description);
    }

    public function testResolveAttributeFallsBackToInvokeLevel(): void
    {
        $tool = McpToolAttributeReader::resolveAttribute(McpAttributeReaderTestMethodLevel::class, McpTool::class);

        static::assertInstanceOf(McpTool::class, $tool);
        static::assertSame('reader-method-level-tool', $tool->name);
    }

    public function testResolveAttributePrefersClassLevelOverInvokeLevel(): void
    {
        $tool = McpToolAttributeReader::resolveAttribute(McpAttributeReaderTestBothLevels::class, McpTool::class);

        static::assertInstanceOf(McpTool::class, $tool);
        static::assertSame('reader-class-wins', $tool->name);
    }
}

/**
 * @internal
 */
class McpAttributeReaderTestNoAttribute
{
    public function __invoke(): string
    {
        return '';
    }
}

/**
 * @internal
 */
#[McpTool(name: 'reader-class-level-tool', description: 'class-level description')]
class McpAttributeReaderTestClassLevel extends McpToolResponse
{
    public function __invoke(): string
    {
        return '';
    }
}

/**
 * @internal
 */
class McpAttributeReaderTestMethodLevel
{
    #[McpTool(name: 'reader-method-level-tool', description: 'method-level description')]
    public function __invoke(): string
    {
        return '';
    }
}

/**
 * @internal
 */
#[McpTool(name: 'reader-class-wins', description: 'class description wins')]
class McpAttributeReaderTestBothLevels extends McpToolResponse
{
    #[McpTool(name: 'reader-method-loses', description: 'method description loses')]
    public function __invoke(): string
    {
        return '';
    }
}

/**
 * @internal
 */
class McpAttributeReaderTestNoInvokeNoAttribute
{
    public function doSomething(): string
    {
        return '';
    }
}
