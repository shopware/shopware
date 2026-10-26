<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Mcp\Tool;

use Mcp\Capability\Registry;
use Mcp\Schema\Tool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Tool\Search\ToolSearch;
use Shopware\Core\System\SalesChannel\Mcp\Tool\StoreApiToolSearchTool;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoreApiToolSearchTool::class)]
class StoreApiToolSearchToolTest extends TestCase
{
    public function testSearchReturnsStoreApiToolDefinitions(): void
    {
        $registry = new Registry();
        $registry->registerTool(self::tool('shopware-store-api-product-search', 'Search products'), 'Acme\\ProductSearchTool');

        $tool = new StoreApiToolSearchTool($registry, new ToolSearch());

        $data = $tool('product');

        static::assertFalse($data->isError());
        static::assertIsArray($data->data);
        $match = $data->data[0]['tool'];
        static::assertInstanceOf(Tool::class, $match);
        static::assertSame('shopware-store-api-product-search', $match->name);
    }

    public function testResultCarriesToolsetEnableUsageHint(): void
    {
        $registry = new Registry();
        $registry->registerTool(self::tool('shopware-store-api-product-search', 'Search products'), 'Acme\\ProductSearchTool');

        $tool = new StoreApiToolSearchTool($registry, new ToolSearch());

        $data = $tool('product');

        // Store API now uses progressive disclosure, so tool-search nudges toward the enable path.
        static::assertArrayHasKey('usage', $data->meta);
        static::assertStringContainsString('shopware-toolset-enable', $data->meta['usage']);
    }

    private static function tool(string $name, string $description): Tool
    {
        return new Tool(
            name: $name,
            title: null,
            inputSchema: ['type' => 'object', 'properties' => [], 'required' => []],
            description: $description,
            annotations: null,
        );
    }
}
