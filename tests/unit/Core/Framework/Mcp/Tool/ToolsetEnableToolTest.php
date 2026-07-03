<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Mcp\McpToolListChangedNotifier;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;
use Shopware\Core\Framework\Mcp\McpToolsetSessionStorage;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Shopware\Core\Framework\Mcp\Tool\ToolsetEnableTool;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(ToolsetEnableTool::class)]
#[CoversClass(McpToolResponse::class)]
class ToolsetEnableToolTest extends TestCase
{
    public function testEnablesToolsetForCurrentSessionAndNotifiesListChanged(): void
    {
        $registry = $this->createMock(McpToolsetRegistry::class);
        $registry->expects($this->once())
            ->method('find')
            ->with('shopware-entity')
            ->willReturn([
                'name' => 'shopware-entity',
                'title' => 'Entity tools',
                'description' => 'Entity',
                'tools' => ['shopware-entity-search'],
                'enabledByDefault' => false,
            ]);

        $storage = $this->createMock(McpToolsetSessionStorage::class);
        $storage->expects($this->once())
            ->method('enable')
            ->with('session-a', 'shopware-entity');

        $notifier = $this->createMock(McpToolListChangedNotifier::class);
        $notifier->expects($this->once())->method('notify');

        $requestStack = new RequestStack();
        $request = Request::create('/api/_mcp', 'POST');
        $request->headers->set('Mcp-Session-Id', 'session-a');
        $requestStack->push($request);

        $tool = new ToolsetEnableTool($registry, $storage, $notifier, $requestStack);
        $result = json_decode($tool('shopware-entity'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($result['success']);
        static::assertSame('shopware-entity', $result['data']['toolset']['name']);
        static::assertTrue($result['_meta']['listChanged']);
    }

    public function testRejectsUnknownToolset(): void
    {
        $registry = static::createStub(McpToolsetRegistry::class);
        $registry->method('find')->willReturn(null);

        $tool = new ToolsetEnableTool(
            $registry,
            static::createStub(McpToolsetSessionStorage::class),
            static::createStub(McpToolListChangedNotifier::class),
            new RequestStack(),
        );

        $result = json_decode($tool('missing'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($result['success']);
        static::assertStringContainsString('Unknown MCP toolset "missing"', $result['error']);
    }
}
