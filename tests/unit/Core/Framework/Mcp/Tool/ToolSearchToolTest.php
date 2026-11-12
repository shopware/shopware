<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use Mcp\Capability\Registry;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\Tool;
use Mcp\Server\Stateless\RequestMeta;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;
use Shopware\Core\Framework\Mcp\Tool\Search\ToolSearch;
use Shopware\Core\Framework\Mcp\Tool\ToolSearchTool;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ToolSearchTool::class)]
class ToolSearchToolTest extends TestCase
{
    public function testSearchReturnsMatchingToolDefinitions(): void
    {
        $tool = new ToolSearchTool($this->registry(), new ToolSearch());

        $data = json_decode($tool('read entity'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame('shopware-entity-read', $data['data'][0]['tool']['name']);
        static::assertSame(2, $data['_meta']['totalCandidates']);
    }

    public function testResultCarriesToolsetEnableUsageHint(): void
    {
        $tool = new ToolSearchTool($this->registry(), new ToolSearch());

        $data = json_decode($tool('read entity'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('usage', $data['_meta']);
        static::assertStringContainsString('shopware-toolset-enable', $data['_meta']['usage']);
    }

    public function testHandshakeResultNamesNoToolset(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/api/_mcp', 'POST'));

        $tool = new ToolSearchTool($this->registry(), new ToolSearch(), null, $this->toolsetRegistry(), $requestStack);

        $data = json_decode($tool('read entity'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayNotHasKey('toolset', $data['data'][0]);
        static::assertStringContainsString('shopware-toolset-enable', $data['_meta']['usage']);
    }

    public function testStatelessResultNamesTheToolsetAndPointsToToolsetsInTheUrl(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(self::statelessRequest('tools/call'));

        $tool = new ToolSearchTool($this->registry(), new ToolSearch(), null, $this->toolsetRegistry(), $requestStack);

        $data = json_decode($tool('read entity', 20), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('shopware-entity-read', $data['data'][0]['tool']['name']);
        static::assertSame('entity', $data['data'][0]['toolset']);
        static::assertSame(2, $data['_meta']['totalCandidates']);
        static::assertStringContainsString('?toolsets=', $data['_meta']['usage']);
        static::assertStringNotContainsString('shopware-toolset-enable', $data['_meta']['usage']);
        static::assertStringNotContainsString('shopware-toolsets-list', $data['_meta']['usage']);
    }

    public function testStatelessSearchDoesNotFindTheToolsetSessionTools(): void
    {
        $registry = $this->registry();
        $registry->registerTool(self::tool(McpToolsetRegistry::ENABLE_TOOLSET_TOOL, 'Enable one MCP toolset'), 'Acme\\EnableTool');
        $registry->registerTool(self::tool(McpToolsetRegistry::LIST_TOOLSETS_TOOL, 'List the MCP toolsets'), 'Acme\\ListTool');

        $requestStack = new RequestStack();
        $requestStack->push(self::statelessRequest('tools/call'));

        $tool = new ToolSearchTool($registry, new ToolSearch(), null, $this->toolsetRegistry(), $requestStack);

        $data = json_decode($tool('toolset', 20), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame([], $data['data']);
        static::assertSame(2, $data['_meta']['totalCandidates']);
    }

    private function toolsetRegistry(): McpToolsetRegistry
    {
        $registry = static::createStub(McpToolsetRegistry::class);
        $registry->method('toolsets')->willReturn([
            ['name' => 'entity', 'title' => 'Entity tools', 'description' => 'Entity', 'tools' => ['shopware-entity-read', 'shopware-entity-search']],
        ]);

        return $registry;
    }

    private function registry(): Registry
    {
        $registry = new Registry();
        $registry->registerTool(self::tool('shopware-entity-search', 'Search entities'), 'Acme\\SearchTool');
        $registry->registerTool(self::tool('shopware-entity-read', 'Read one entity by ID'), 'Acme\\ReadTool');

        return $registry;
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

    private static function statelessRequest(string $method): Request
    {
        return Request::create('/api/_mcp', 'POST', content: json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => ['_meta' => [RequestMeta::PROTOCOL_VERSION => ProtocolVersion::V2026_07_28->value]],
        ], \JSON_THROW_ON_ERROR));
    }
}
