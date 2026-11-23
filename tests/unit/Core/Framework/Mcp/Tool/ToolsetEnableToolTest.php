<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Server\Stateless\RequestMeta;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;
use Shopware\Core\Framework\Mcp\McpToolsetSessionStorage;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotifier;
use Shopware\Core\Framework\Mcp\Tool\ToolsetEnableTool;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ToolsetEnableTool::class)]
class ToolsetEnableToolTest extends TestCase
{
    public function testEnablesToolsetForCurrentSessionAndFlagsListChanged(): void
    {
        $registry = $this->createMock(McpToolsetRegistry::class);
        $registry->expects($this->once())
            ->method('find')
            ->with('entity')
            ->willReturn([
                'name' => 'entity',
                'title' => 'Entity tools',
                'description' => 'Entity',
                'tools' => ['shopware-entity-search'],
            ]);

        $storage = $this->createMock(McpToolsetSessionStorage::class);
        $storage->expects($this->once())
            ->method('enable')
            ->with('session-a', 'entity');

        $requestStack = new RequestStack();
        $request = Request::create('/api/_mcp', 'POST');
        $request->headers->set('Mcp-Session-Id', 'session-a');
        $requestStack->push($request);

        $tool = new ToolsetEnableTool($registry, $storage, $requestStack);
        $result = $tool('entity');

        static::assertFalse($result->isError());
        static::assertSame('entity', $result->data['toolset']['name']);
        static::assertTrue($result->meta['listChanged']);
        // The tool records intent; the controller emits the notification after the SDK session save.
        static::assertTrue($request->attributes->getBoolean(McpListChangedNotifier::PENDING_TOOLS_LIST_CHANGED_ATTRIBUTE));
    }

    public function testRejectsUnknownToolset(): void
    {
        $registry = static::createStub(McpToolsetRegistry::class);
        $registry->method('find')->willReturn(null);

        $requestStack = new RequestStack();
        $request = Request::create('/api/_mcp', 'POST');
        $request->headers->set('Mcp-Session-Id', 'session-a');
        $requestStack->push($request);

        $tool = new ToolsetEnableTool(
            $registry,
            static::createStub(McpToolsetSessionStorage::class),
            $requestStack,
        );

        $result = $tool('missing');

        static::assertTrue($result->isError());
        static::assertStringContainsString('Unknown MCP toolset "missing"', $result->error->message);
        static::assertFalse($request->attributes->getBoolean(McpListChangedNotifier::PENDING_TOOLS_LIST_CHANGED_ATTRIBUTE));
    }

    public function testRejectsEnableWithoutActiveSession(): void
    {
        $registry = static::createStub(McpToolsetRegistry::class);
        $registry->method('find')->willReturn([
            'name' => 'entity',
            'title' => 'Entity tools',
            'description' => 'Entity',
            'tools' => ['shopware-entity-search'],
        ]);

        $storage = $this->createMock(McpToolsetSessionStorage::class);
        $storage->expects($this->never())->method('enable');

        $tool = new ToolsetEnableTool($registry, $storage, new RequestStack());

        $result = $tool('entity');

        static::assertTrue($result->isError());
        static::assertStringContainsString('"?toolsets=entity"', $result->error->message);
    }

    public function testIsUnavailableOnTheStatelessEraAndPointsToToolsetsInTheUrl(): void
    {
        $registry = $this->createMock(McpToolsetRegistry::class);
        $registry->expects($this->never())->method('find');

        $storage = $this->createMock(McpToolsetSessionStorage::class);
        $storage->expects($this->never())->method('enable');

        $requestStack = new RequestStack();
        $request = self::statelessRequest('tools/call');
        $requestStack->push($request);

        $result = (new ToolsetEnableTool($registry, $storage, $requestStack))('entity');

        static::assertTrue($result->isError());
        static::assertStringContainsString('2026-07-28', $result->error->message);
        static::assertStringContainsString('"?toolsets=entity"', $result->error->message);
        static::assertStringContainsString(McpToolsetRegistry::TOOL_SEARCH_TOOL, $result->error->message);
        static::assertFalse($request->attributes->getBoolean(McpListChangedNotifier::PENDING_TOOLS_LIST_CHANGED_ATTRIBUTE));
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
