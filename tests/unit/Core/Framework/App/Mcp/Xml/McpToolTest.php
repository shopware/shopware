<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Mcp\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Mcp\Mcp;
use Shopware\Core\Framework\App\Mcp\Xml\McpTool;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(McpTool::class)]
#[Package('framework')]
class McpToolTest extends TestCase
{
    public function testFromXmlParsesAllFields(): void
    {
        $mcp = Mcp::createFromXmlFile(__DIR__ . '/../_fixtures/mcp.xml');

        $tools = $mcp->getTools();
        static::assertNotNull($tools);

        $tool = $tools->getTools()[0];
        static::assertSame('sync-orders', $tool->getName());
        static::assertSame('https://app.example.com/mcp/sync', $tool->getUrl());
        static::assertSame([
            'en-GB' => 'Sync Orders',
            'de-DE' => 'Bestellungen synchronisieren',
        ], $tool->getLabel());
        static::assertSame([
            'en-GB' => 'Synchronize orders from external ERP',
            'de-DE' => 'Bestellungen vom externen ERP synchronisieren',
        ], $tool->getDescription());
    }

    public function testFromXmlParsesInputSchema(): void
    {
        $mcp = Mcp::createFromXmlFile(__DIR__ . '/../_fixtures/mcp.xml');

        $tools = $mcp->getTools();
        static::assertNotNull($tools);

        $schema = $tools->getTools()[0]->getInputSchema();
        static::assertNotNull($schema);
        static::assertCount(2, $schema);

        static::assertSame('string', $schema['since']['type']);
        static::assertArrayHasKey('description', $schema['since']);
        static::assertSame('ISO 8601 date', $schema['since']['description']);
        static::assertArrayHasKey('required', $schema['since']);
        static::assertTrue($schema['since']['required']);

        static::assertSame('integer', $schema['limit']['type']);
        static::assertArrayHasKey('description', $schema['limit']);
        static::assertSame('Max number of orders', $schema['limit']['description']);
        static::assertArrayNotHasKey('required', $schema['limit']);
    }

    public function testToolWithoutInputSchemaReturnsNull(): void
    {
        $mcp = Mcp::createFromXmlFile(__DIR__ . '/../_fixtures/mcp.xml');

        $tools = $mcp->getTools();
        static::assertNotNull($tools);

        $tool = $tools->getTools()[1];
        static::assertSame('stock-check', $tool->getName());
        static::assertNull($tool->getInputSchema());
    }

    public function testToolWithoutDescriptionReturnsEmptyArray(): void
    {
        $mcp = Mcp::createFromXmlFile(__DIR__ . '/../_fixtures/mcp.xml');

        $tools = $mcp->getTools();
        static::assertNotNull($tools);

        $tool = $tools->getTools()[1];
        static::assertSame([], $tool->getDescription());
    }

    public function testToArrayContainsTranslations(): void
    {
        $tool = McpTool::fromArray([
            'name' => 'my-tool',
            'url' => 'https://example.com/mcp',
            'label' => ['en-GB' => 'My Tool', 'de-DE' => 'Mein Werkzeug'],
            'description' => ['en-GB' => 'Desc'],
        ]);

        $data = $tool->toArray('en-GB');

        static::assertSame('my-tool', $data['name']);
        static::assertSame('https://example.com/mcp', $data['url']);
        static::assertSame('My Tool', $data['label']['en-GB']);
        static::assertSame('Mein Werkzeug', $data['label']['de-DE']);
        static::assertSame('Desc', $data['description']['en-GB']);
    }

    public function testFromXmlParsesRequiredPrivileges(): void
    {
        $mcp = Mcp::createFromXmlFile(__DIR__ . '/../_fixtures/mcp.xml');

        $tools = $mcp->getTools();
        static::assertNotNull($tools);

        $tool = $tools->getTools()[0];
        static::assertSame(['product:read', 'order:read'], $tool->getRequiredPrivileges());
    }

    public function testToolWithoutRequiredPrivilegesReturnsEmptyArray(): void
    {
        $mcp = Mcp::createFromXmlFile(__DIR__ . '/../_fixtures/mcp.xml');

        $tools = $mcp->getTools();
        static::assertNotNull($tools);

        $tool = $tools->getTools()[1];
        static::assertSame([], $tool->getRequiredPrivileges());
    }

    public function testFromArraySetsProperties(): void
    {
        $tool = McpTool::fromArray([
            'name' => 'test',
            'url' => 'https://test.example.com',
            'label' => ['en-GB' => 'Test'],
            'description' => [],
            'inputSchema' => ['orderId' => ['type' => 'string', 'required' => true]],
        ]);

        static::assertSame('test', $tool->getName());
        static::assertSame('https://test.example.com', $tool->getUrl());
        static::assertSame(['en-GB' => 'Test'], $tool->getLabel());
        static::assertNotNull($tool->getInputSchema());
        static::assertArrayHasKey('orderId', $tool->getInputSchema());
    }
}
