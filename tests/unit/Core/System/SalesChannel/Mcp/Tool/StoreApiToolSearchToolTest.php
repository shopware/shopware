<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Mcp\Tool;

use Mcp\Capability\Registry;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\Tool;
use Mcp\Server\Stateless\RequestMeta;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;
use Shopware\Core\Framework\Mcp\Tool\Search\ToolSearch;
use Shopware\Core\System\SalesChannel\Mcp\Tool\StoreApiToolSearchTool;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

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

    public function testStatelessResultNamesTheToolsetAndPointsToToolsetsInTheUrl(): void
    {
        $registry = new Registry();
        $registry->registerTool(self::tool('shopware-store-api-product-search', 'Search products'), 'Acme\\ProductSearchTool');
        $registry->registerTool(self::tool(McpToolsetRegistry::ENABLE_TOOLSET_TOOL, 'Enable one product toolset'), 'Acme\\EnableTool');

        $toolsetRegistry = static::createStub(McpToolsetRegistry::class);
        $toolsetRegistry->method('toolsets')->willReturn([
            ['name' => 'store-api', 'title' => 'Store api tools', 'description' => 'Store API', 'tools' => ['shopware-store-api-product-search']],
        ]);

        $requestStack = new RequestStack();
        $requestStack->push(self::statelessRequest());

        $tool = new StoreApiToolSearchTool($registry, new ToolSearch(), null, $toolsetRegistry, $requestStack);

        $data = json_decode($tool('product', 20), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(['shopware-store-api-product-search'], array_map(static fn (array $match): string => $match['tool']['name'], $data['data']));
        static::assertSame('store-api', $data['data'][0]['toolset']);
        static::assertStringContainsString('?toolsets=', $data['_meta']['usage']);
        static::assertStringNotContainsString('shopware-toolset-enable', $data['_meta']['usage']);
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

    private static function statelessRequest(): Request
    {
        return Request::create('/store-api/_mcp', 'POST', content: json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['_meta' => [RequestMeta::PROTOCOL_VERSION => ProtocolVersion::V2026_07_28->value]],
        ], \JSON_THROW_ON_ERROR));
    }
}
