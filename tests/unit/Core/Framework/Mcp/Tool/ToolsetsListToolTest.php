<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use Mcp\Capability\Registry;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\Tool;
use Mcp\Server\Stateless\RequestMeta;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Loader\AppMcpPrivilegeProvider;
use Shopware\Core\Framework\Mcp\McpCapabilityCatalog;
use Shopware\Core\Framework\Mcp\McpRequestedToolsetResolver;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;
use Shopware\Core\Framework\Mcp\McpToolsetSessionStorage;
use Shopware\Core\Framework\Mcp\Tool\ToolsetsListTool;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ToolsetsListTool::class)]
class ToolsetsListToolTest extends TestCase
{
    public function testListsToolsetsWithEnabledSessionState(): void
    {
        $registry = static::createStub(McpToolsetRegistry::class);
        $registry->method('toolsets')->willReturn([
            [
                'name' => 'entity',
                'title' => 'Entity tools',
                'description' => 'Entity',
                'tools' => ['shopware-entity-search'],
            ],
            [
                'name' => 'order',
                'title' => 'Order tools',
                'description' => 'Order',
                'tools' => ['shopware-order-state'],
            ],
        ]);

        $storage = $this->createMock(McpToolsetSessionStorage::class);
        $storage->expects($this->once())
            ->method('enabledToolsets')
            ->with('session-a')
            ->willReturn(['entity']);

        $requestStack = new RequestStack();
        $request = Request::create('/api/_mcp', 'POST');
        $request->headers->set('Mcp-Session-Id', 'session-a');
        $requestStack->push($request);

        $tool = new ToolsetsListTool($registry, $storage, $requestStack, new McpRequestedToolsetResolver($requestStack));
        $result = json_decode($tool(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($result['success']);
        static::assertTrue($result['data']['toolsets'][0]['enabled']);
        static::assertFalse($result['data']['toolsets'][1]['enabled']);
        static::assertSame('tool-groups', $result['_meta']['taxonomy']);
        static::assertSame('Toolsets are derived from explicit MCP tool group metadata.', $result['_meta']['note']);
    }

    public function testListsToolsetsWithoutSessionState(): void
    {
        $registry = static::createStub(McpToolsetRegistry::class);
        $registry->method('toolsets')->willReturn([
            [
                'name' => 'entity',
                'title' => 'Entity tools',
                'description' => 'Entity',
                'tools' => ['shopware-entity-search'],
            ],
        ]);

        $storage = $this->createMock(McpToolsetSessionStorage::class);
        $storage->expects($this->never())->method('enabledToolsets');

        $tool = new ToolsetsListTool($registry, $storage, new RequestStack(), new McpRequestedToolsetResolver(new RequestStack()));
        $result = json_decode($tool(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($result['success']);
        static::assertFalse($result['data']['toolsets'][0]['enabled']);
    }

    public function testListsTheAuthoredToolsetDescription(): void
    {
        $mcpRegistry = new Registry();
        foreach (['shopware-entity-search', 'shopware-order-state'] as $toolName) {
            $mcpRegistry->registerTool(new Tool($toolName, null, ['type' => 'object', 'properties' => [], 'required' => []], null, null), 'Acme\\Tool');
        }

        $privilegeProvider = static::createStub(AppMcpPrivilegeProvider::class);
        $privilegeProvider->method('getAppToolPrivileges')->willReturn([]);
        $privilegeProvider->method('getAppToolGroups')->willReturn([]);

        $toolsetRegistry = new McpToolsetRegistry(
            new McpCapabilityCatalog($mcpRegistry, $privilegeProvider, toolGroups: [
                'shopware-entity-search' => 'entity',
                'shopware-order-state' => 'order',
            ]),
            toolsetDescriptions: ['entity' => 'Find and change shop data.'],
        );

        $tool = new ToolsetsListTool($toolsetRegistry, static::createStub(McpToolsetSessionStorage::class), new RequestStack(), new McpRequestedToolsetResolver(new RequestStack()));
        $result = json_decode($tool(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame([
            'entity' => 'Find and change shop data.',
            'order' => 'Tools explicitly assigned to the "order" MCP tool group.',
        ], array_column($result['data']['toolsets'], 'description', 'name'));
    }

    public function testIsUnavailableOnTheStatelessEraAndPointsToToolsetsInTheUrl(): void
    {
        $registry = $this->createMock(McpToolsetRegistry::class);
        $registry->expects($this->never())->method('toolsets');

        $requestStack = new RequestStack();
        $requestStack->push(self::statelessRequest('tools/call'));

        $tool = new ToolsetsListTool($registry, static::createStub(McpToolsetSessionStorage::class), $requestStack, new McpRequestedToolsetResolver($requestStack));
        $result = json_decode($tool(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($result['success']);
        static::assertStringContainsString('"?toolsets=<toolset>"', $result['error']);
        static::assertStringContainsString(McpToolsetRegistry::TOOL_SEARCH_TOOL, $result['error']);
    }

    /**
     * @param list<string> $sessionToolsets
     * @param array<string, bool> $expected
     */
    #[DataProvider('connectUrlProvider')]
    public function testReportsToolsetsSelectedInTheConnectUrlAsEnabled(string $query, array $sessionToolsets, array $expected): void
    {
        $registry = static::createStub(McpToolsetRegistry::class);
        $registry->method('toolsets')->willReturn([
            ['name' => 'entity', 'title' => 'Entity tools', 'description' => 'Entity', 'tools' => ['shopware-entity-search']],
            ['name' => 'media', 'title' => 'Media tools', 'description' => 'Media', 'tools' => ['shopware-media-upload']],
            ['name' => 'order', 'title' => 'Order tools', 'description' => 'Order', 'tools' => ['shopware-order-state']],
        ]);

        $storage = static::createStub(McpToolsetSessionStorage::class);
        $storage->method('enabledToolsets')->willReturn($sessionToolsets);

        $requestStack = new RequestStack();
        $request = Request::create('/api/_mcp?' . $query, 'POST');
        $request->headers->set('Mcp-Session-Id', 'session-a');
        $requestStack->push($request);

        $tool = new ToolsetsListTool($registry, $storage, $requestStack, new McpRequestedToolsetResolver($requestStack));
        $result = json_decode($tool(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame($expected, array_column($result['data']['toolsets'], 'enabled', 'name'));
    }

    /**
     * @return iterable<string, array{string, list<string>, array<string, bool>}>
     */
    public static function connectUrlProvider(): iterable
    {
        yield 'one toolset in the URL' => ['toolsets=order', [], ['entity' => false, 'media' => false, 'order' => true]];
        yield 'all toolsets in the URL' => ['toolsets=all', [], ['entity' => true, 'media' => true, 'order' => true]];
        yield 'URL and session together' => ['toolsets=order', ['media'], ['entity' => false, 'media' => true, 'order' => true]];
        yield 'malformed parameter' => ['toolsets[]=order', [], ['entity' => false, 'media' => false, 'order' => false]];
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
