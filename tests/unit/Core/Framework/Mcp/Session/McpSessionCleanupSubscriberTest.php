<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpToolsetSessionStorage;
use Shopware\Core\Framework\Mcp\Notification\McpSessionRegistry;
use Shopware\Core\Framework\Mcp\Session\McpSessionCleanupSubscriber;
use Shopware\Core\Framework\Mcp\ToolResultCacheStorage;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
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
        $toolsetStorage = $this->createMock(McpToolsetSessionStorage::class);
        $toolsetStorage->expects($this->once())
            ->method('deleteForSession')
            ->with('test-session-id');

        $sessionRegistry = new McpSessionRegistry(new Psr16Cache(new ArrayAdapter()));
        $sessionRegistry->register('test-session-id');

        $subscriber = new McpSessionCleanupSubscriber($storage, $toolsetStorage, $sessionRegistry);

        $request = Request::create('/api/_mcp', 'DELETE');
        $request->headers->set('Mcp-Session-Id', 'test-session-id');

        $event = new TerminateEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            new Response(),
        );

        $subscriber->onKernelTerminate($event);

        static::assertSame([], $sessionRegistry->all());
    }

    public function testStoreApiDeleteRemovesFromStoreApiRegistryOnly(): void
    {
        // Both registries share the cache pool but use distinct keys, mirroring production wiring.
        $cache = new Psr16Cache(new ArrayAdapter());
        $adminRegistry = new McpSessionRegistry($cache, 'shopware.mcp.active_session_ids');
        $storeApiRegistry = new McpSessionRegistry($cache, 'shopware.mcp.store_api.active_session_ids');
        $adminRegistry->register('shared-session-id');
        $storeApiRegistry->register('shared-session-id');

        $subscriber = new McpSessionCleanupSubscriber(
            static::createStub(ToolResultCacheStorage::class),
            static::createStub(McpToolsetSessionStorage::class),
            $adminRegistry,
            $storeApiRegistry,
        );

        $request = Request::create('/store-api/_mcp', 'DELETE');
        $request->headers->set('Mcp-Session-Id', 'shared-session-id');

        $subscriber->onKernelTerminate(new TerminateEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            new Response(),
        ));

        static::assertSame(['shared-session-id'], $adminRegistry->all(), 'a store-api DELETE must not touch the Admin registry');
        static::assertSame([], $storeApiRegistry->all(), 'a store-api DELETE must clear the store-api registry');
    }

    public function testIgnoresNonDeleteRequests(): void
    {
        $storage = $this->createMock(ToolResultCacheStorage::class);
        $storage->expects($this->never())->method('deleteForSession');

        $subscriber = new McpSessionCleanupSubscriber(
            $storage,
            static::createStub(McpToolsetSessionStorage::class),
            static::createStub(McpSessionRegistry::class),
        );

        $request = Request::create('/api/_mcp', 'POST');
        $request->headers->set('Mcp-Session-Id', 'test-session-id');

        $event = new TerminateEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            new Response(),
        );

        $subscriber->onKernelTerminate($event);
    }

    public function testIgnoresNonMcpPaths(): void
    {
        $storage = $this->createMock(ToolResultCacheStorage::class);
        $storage->expects($this->never())->method('deleteForSession');

        $subscriber = new McpSessionCleanupSubscriber(
            $storage,
            static::createStub(McpToolsetSessionStorage::class),
            static::createStub(McpSessionRegistry::class),
        );

        $request = Request::create('/api/something-else', 'DELETE');
        $request->headers->set('Mcp-Session-Id', 'test-session-id');

        $event = new TerminateEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            new Response(),
        );

        $subscriber->onKernelTerminate($event);
    }

    public function testIgnoresMissingSessionId(): void
    {
        $storage = $this->createMock(ToolResultCacheStorage::class);
        $storage->expects($this->never())->method('deleteForSession');

        $subscriber = new McpSessionCleanupSubscriber(
            $storage,
            static::createStub(McpToolsetSessionStorage::class),
            static::createStub(McpSessionRegistry::class),
        );

        $request = Request::create('/api/_mcp', 'DELETE');

        $event = new TerminateEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            new Response(),
        );

        $subscriber->onKernelTerminate($event);
    }
}
