<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Mcp\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Mcp\Mcp;
use Shopware\Core\Framework\App\Mcp\Xml\McpTool;
use Shopware\Core\Framework\App\Mcp\Xml\McpTools;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(McpTools::class)]
#[Package('framework')]
class McpToolsTest extends TestCase
{
    public function testFromXmlParsesMultipleTools(): void
    {
        $mcp = Mcp::createFromXmlFile(__DIR__ . '/../_fixtures/mcp.xml');

        $tools = $mcp->getTools();
        static::assertNotNull($tools);
        static::assertCount(2, $tools->getTools());

        static::assertContainsOnlyInstancesOf(McpTool::class, $tools->getTools());
    }

    public function testGetToolsReturnsToolsInOrder(): void
    {
        $mcp = Mcp::createFromXmlFile(__DIR__ . '/../_fixtures/mcp.xml');

        $tools = $mcp->getTools();
        static::assertNotNull($tools);

        $names = array_map(
            static fn (McpTool $t) => $t->getName(),
            $tools->getTools(),
        );

        static::assertSame(['sync-orders', 'stock-check'], $names);
    }

    public function testFromArrayCreatesTools(): void
    {
        $tool = McpTool::fromArray([
            'name' => 'test-tool',
            'url' => 'https://example.com',
            'label' => ['en-GB' => 'Test'],
            'description' => [],
        ]);

        $tools = McpTools::fromArray(['tools' => [$tool]]);

        static::assertCount(1, $tools->getTools());
        static::assertSame('test-tool', $tools->getTools()[0]->getName());
    }
}
