<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;
use Shopware\Core\Framework\Mcp\McpToolsetSessionStorage;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotifier;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Shopware\Core\Framework\Mcp\Tool\ToolsetEnableTool;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ToolsetEnableTool::class)]
#[CoversClass(McpToolResponse::class)]
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
        $result = json_decode($tool('entity'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($result['success']);
        static::assertSame('entity', $result['data']['toolset']['name']);
        static::assertTrue($result['_meta']['listChanged']);
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

        $result = json_decode($tool('missing'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($result['success']);
        static::assertStringContainsString('Unknown MCP toolset "missing"', $result['error']);
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

        $result = json_decode($tool('entity'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($result['success']);
        static::assertSame('Cannot enable an MCP toolset without an active MCP session.', $result['error']);
    }
}
