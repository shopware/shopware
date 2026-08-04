<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Mcp\Tool;

use Mcp\Capability\Registry;
use Mcp\Schema\Tool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Tool\AbstractToolSearchTool;
use Shopware\Core\Framework\Mcp\Tool\Search\ToolSearch;
use Shopware\Core\System\SalesChannel\Mcp\Tool\StoreApiToolSearchTool;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoreApiToolSearchTool::class)]
#[CoversClass(AbstractToolSearchTool::class)]
class StoreApiToolSearchToolTest extends TestCase
{
    public function testSearchReturnsStoreApiToolDefinitions(): void
    {
        $registry = new Registry();
        $registry->registerTool(self::tool('shopware-store-api-product-search', 'Search products'), 'Acme\\ProductSearchTool');

        $tool = new StoreApiToolSearchTool($registry, new ToolSearch());

        $data = json_decode($tool('product'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame('shopware-store-api-product-search', $data['data'][0]['tool']['name']);
    }

    public function testResultCarriesToolsetEnableUsageHint(): void
    {
        $registry = new Registry();
        $registry->registerTool(self::tool('shopware-store-api-product-search', 'Search products'), 'Acme\\ProductSearchTool');

        $tool = new StoreApiToolSearchTool($registry, new ToolSearch());

        $data = json_decode($tool('product'), true, 512, \JSON_THROW_ON_ERROR);

        // Store API now uses progressive disclosure, so tool-search nudges toward the enable path.
        static::assertArrayHasKey('usage', $data['_meta']);
        static::assertStringContainsString('shopware-toolset-enable', $data['_meta']['usage']);
    }

    public function testInvokeIsDeclaredOnConcreteClassSoDiscoveryBindsToIt(): void
    {
        // The MCP SDK discoverer binds a tool handler to __invoke's declaring class. If __invoke
        // were only inherited from AbstractToolSearchTool, discovery would bind the handler to the
        // non-instantiable abstract base and the tool would fail at runtime.
        $method = new \ReflectionMethod(StoreApiToolSearchTool::class, '__invoke');

        static::assertSame(StoreApiToolSearchTool::class, $method->getDeclaringClass()->getName());
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
