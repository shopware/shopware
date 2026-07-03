<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Mcp\Tool;

use Mcp\Capability\Registry;
use Mcp\Schema\Tool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Mcp\Tool\AbstractToolSearchTool;
use Shopware\Core\Framework\Mcp\Tool\Search\ToolSearch;
use Shopware\Core\System\SalesChannel\Mcp\Tool\StoreApiToolSearchTool;

/**
 * @internal
 */
#[CoversClass(StoreApiToolSearchTool::class)]
#[CoversClass(AbstractToolSearchTool::class)]
class StoreApiToolSearchToolTest extends TestCase
{
    public function testSearchReturnsStoreApiToolDefinitions(): void
    {
        $registry = new Registry();
        $registry->registerTool(self::tool('shopware-store-api-product-search', 'Search products'), 'Acme\\ProductSearchTool', true);

        $tool = new StoreApiToolSearchTool($registry, new ToolSearch());

        $data = json_decode($tool('product'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame('shopware-store-api-product-search', $data['data'][0]['tool']['name']);
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
