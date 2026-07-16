<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;
use Shopware\Core\Framework\Mcp\McpToolsetSessionStorage;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotificationSet;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotifier;
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

        $notifier = $this->createMock(McpListChangedNotifier::class);
        $notifier->expects($this->never())->method('notify');
        $notifier->expects($this->once())
            ->method('notifySession')
            ->with(
                'session-a',
                static::callback(static fn (McpListChangedNotificationSet $notification): bool => $notification->tools && !$notification->resources && !$notification->prompts),
            );

        $requestStack = new RequestStack();
        $request = Request::create('/api/_mcp', 'POST');
        $request->headers->set('Mcp-Session-Id', 'session-a');
        $requestStack->push($request);

        $tool = new ToolsetEnableTool($registry, $storage, $notifier, $requestStack);
        $result = json_decode($tool('entity'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($result['success']);
        static::assertSame('entity', $result['data']['toolset']['name']);
        static::assertTrue($result['_meta']['listChanged']);
    }

    public function testRejectsUnknownToolset(): void
    {
        $registry = static::createStub(McpToolsetRegistry::class);
        $registry->method('find')->willReturn(null);

        $tool = new ToolsetEnableTool(
            $registry,
            static::createStub(McpToolsetSessionStorage::class),
            static::createStub(McpListChangedNotifier::class),
            new RequestStack(),
        );

        $result = json_decode($tool('missing'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($result['success']);
        static::assertStringContainsString('Unknown MCP toolset "missing"', $result['error']);
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

        $notifier = $this->createMock(McpListChangedNotifier::class);
        $notifier->expects($this->never())->method('notify');
        $notifier->expects($this->never())->method('notifySession');

        $tool = new ToolsetEnableTool($registry, $storage, $notifier, new RequestStack());

        $result = json_decode($tool('entity'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($result['success']);
        static::assertSame('Cannot enable an MCP toolset without an active MCP session.', $result['error']);
    }
}
