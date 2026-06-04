<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Session\McpSessionCleanupSubscriber;
use Shopware\Core\Framework\Mcp\ToolResultCacheStorage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpSessionCleanupSubscriber::class)]
class McpSessionCleanupSubscriberTest extends TestCase
{
    public function testSubscribesToKernelTerminate(): void
    {
        static::assertArrayHasKey(KernelEvents::TERMINATE, McpSessionCleanupSubscriber::getSubscribedEvents());
    }

    public function testDeletesSessionResultsOnMcpSessionEnd(): void
    {
        $storage = $this->createMock(ToolResultCacheStorage::class);
        $storage->expects($this->once())
            ->method('deleteForSession')
            ->with('test-session-id');

        $subscriber = new McpSessionCleanupSubscriber($storage);

        $request = Request::create('/api/_mcp', 'DELETE');
        $request->headers->set('Mcp-Session-Id', 'test-session-id');

        $event = new TerminateEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            new Response(),
        );

        $subscriber->onKernelTerminate($event);
    }

    public function testIgnoresNonDeleteRequests(): void
    {
        $storage = $this->createMock(ToolResultCacheStorage::class);
        $storage->expects($this->never())->method('deleteForSession');

        $subscriber = new McpSessionCleanupSubscriber($storage);

        $request = Request::create('/api/_mcp', 'POST');
        $request->headers->set('Mcp-Session-Id', 'test-session-id');

        $event = new TerminateEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            new Response(),
        );

        $subscriber->onKernelTerminate($event);
    }

    public function testIgnoresNonMcpPaths(): void
    {
        $storage = $this->createMock(ToolResultCacheStorage::class);
        $storage->expects($this->never())->method('deleteForSession');

        $subscriber = new McpSessionCleanupSubscriber($storage);

        $request = Request::create('/api/something-else', 'DELETE');
        $request->headers->set('Mcp-Session-Id', 'test-session-id');

        $event = new TerminateEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            new Response(),
        );

        $subscriber->onKernelTerminate($event);
    }

    public function testIgnoresMissingSessionId(): void
    {
        $storage = $this->createMock(ToolResultCacheStorage::class);
        $storage->expects($this->never())->method('deleteForSession');

        $subscriber = new McpSessionCleanupSubscriber($storage);

        $request = Request::create('/api/_mcp', 'DELETE');

        $event = new TerminateEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            new Response(),
        );

        $subscriber->onKernelTerminate($event);
    }
}
