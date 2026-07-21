<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Notification;

use Mcp\Server\Session\SessionStoreInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotificationSet;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotifier;
use Shopware\Core\Framework\Mcp\Notification\McpSessionRegistry;
use Shopware\Core\Framework\Util\Json;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpListChangedNotifier::class)]
class McpListChangedNotifierTest extends TestCase
{
    public function testQueuesListChangedNotificationsForActiveSessions(): void
    {
        $sessionId = Uuid::v4()->toRfc4122();
        $registry = $this->registry();
        $registry->register($sessionId);

        $store = $this->createMock(SessionStoreInterface::class);
        $store->method('exists')->willReturn(true);
        $store->method('read')->willReturn(Json::encode([
            'initialized' => true,
        ]));
        $store->expects($this->once())
            ->method('write')
            ->with(
                static::callback(static fn (Uuid $uuid): bool => $uuid->toRfc4122() === $sessionId),
                static::callback(static function (string $payload): bool {
                    $data = Json::decodeToArray($payload);

                    $mcpData = $data['_mcp'] ?? null;
                    static::assertIsArray($mcpData);

                    $queue = $mcpData['outgoing_queue'] ?? null;
                    static::assertIsArray($queue);
                    static::assertCount(3, $queue);

                    $messages = [];
                    foreach ($queue as $queued) {
                        static::assertIsArray($queued);
                        static::assertIsString($queued['message'] ?? null);

                        $messages[] = Json::decodeToArray($queued['message']);
                    }

                    static::assertSame([
                        ['jsonrpc' => '2.0', 'method' => 'notifications/tools/list_changed'],
                        ['jsonrpc' => '2.0', 'method' => 'notifications/resources/list_changed'],
                        ['jsonrpc' => '2.0', 'method' => 'notifications/prompts/list_changed'],
                    ], $messages);
                    static::assertIsArray($queue[0]);
                    static::assertSame(['type' => 'notification'], $queue[0]['context']);

                    return true;
                }),
            )
            ->willReturn(true);

        $notifier = new McpListChangedNotifier($store, $registry, new NullLogger());
        $notifier->notify(new McpListChangedNotificationSet(tools: true, resources: true, prompts: true));
    }

    public function testDoesNotWriteWhenNoNotificationTypesChanged(): void
    {
        $registry = $this->registry();
        $registry->register(Uuid::v4()->toRfc4122());

        $store = $this->createMock(SessionStoreInterface::class);
        $store->expects($this->never())->method('write');

        $notifier = new McpListChangedNotifier($store, $registry);
        $notifier->notify(McpListChangedNotificationSet::none());
    }

    public function testDoesNotWriteWhenSessionStoreIsUnavailable(): void
    {
        $registry = $this->registry();
        $registry->register(Uuid::v4()->toRfc4122());

        $notifier = new McpListChangedNotifier(null, $registry);
        $notifier->notify(new McpListChangedNotificationSet(tools: true, resources: false, prompts: false));

        static::assertNotSame([], $registry->all());
    }

    public function testRemovesStaleSessionsFromRegistry(): void
    {
        $sessionId = Uuid::v4()->toRfc4122();
        $registry = $this->registry();
        $registry->register($sessionId);

        $store = $this->createMock(SessionStoreInterface::class);
        $store->method('exists')->willReturn(false);
        $store->expects($this->never())->method('write');

        $notifier = new McpListChangedNotifier($store, $registry);
        $notifier->notify(new McpListChangedNotificationSet(tools: true, resources: false, prompts: false));

        static::assertSame([], $registry->all());
    }

    public function testRemovesInvalidSessionIdsFromRegistry(): void
    {
        $registry = $this->registry();
        $registry->register('not-a-uuid');

        $store = $this->createMock(SessionStoreInterface::class);
        $store->expects($this->never())->method('exists');
        $store->expects($this->never())->method('write');

        $notifier = new McpListChangedNotifier($store, $registry);
        $notifier->notify(new McpListChangedNotificationSet(tools: true, resources: false, prompts: false));

        static::assertSame([], $registry->all());
    }

    public function testSkipsUnreadableSessionData(): void
    {
        $sessionId = Uuid::v4()->toRfc4122();
        $registry = $this->registry();
        $registry->register($sessionId);

        $store = $this->createMock(SessionStoreInterface::class);
        $store->method('exists')->willReturn(true);
        $store->method('read')->willReturn('{broken');
        $store->expects($this->never())->method('write');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $notifier = new McpListChangedNotifier($store, $registry, $logger);
        $notifier->notify(new McpListChangedNotificationSet(tools: true, resources: false, prompts: false));
    }

    public function testNormalizesMalformedOutgoingQueueData(): void
    {
        $sessionId = Uuid::v4()->toRfc4122();
        $registry = $this->registry();
        $registry->register($sessionId);

        $store = $this->createMock(SessionStoreInterface::class);
        $store->method('exists')->willReturn(true);
        $store->method('read')->willReturn(Json::encode([
            '_mcp' => [
                'outgoing_queue' => 'not-a-list',
            ],
        ]));
        $store->expects($this->once())
            ->method('write')
            ->with(
                static::anything(),
                static::callback(static function (string $payload): bool {
                    $data = Json::decodeToArray($payload);

                    static::assertCount(1, $data['_mcp']['outgoing_queue']);

                    return true;
                }),
            )
            ->willReturn(true);

        $notifier = new McpListChangedNotifier($store, $registry);
        $notifier->notify(new McpListChangedNotificationSet(tools: true, resources: false, prompts: false));
    }

    public function testNormalizesMalformedMcpSessionData(): void
    {
        $sessionId = Uuid::v4()->toRfc4122();
        $registry = $this->registry();
        $registry->register($sessionId);

        $store = $this->createMock(SessionStoreInterface::class);
        $store->method('exists')->willReturn(true);
        $store->method('read')->willReturn(Json::encode([
            '_mcp' => 'not-an-array',
        ]));
        $store->expects($this->once())
            ->method('write')
            ->with(
                static::anything(),
                static::callback(static function (string $payload): bool {
                    $data = Json::decodeToArray($payload);

                    static::assertCount(1, $data['_mcp']['outgoing_queue']);

                    return true;
                }),
            )
            ->willReturn(true);

        $notifier = new McpListChangedNotifier($store, $registry);
        $notifier->notify(new McpListChangedNotificationSet(tools: true, resources: false, prompts: false));
    }

    public function testNotifySessionQueuesForOnlyThatSession(): void
    {
        $target = Uuid::v4()->toRfc4122();
        $other = Uuid::v4()->toRfc4122();
        $registry = $this->registry();
        $registry->register($target);
        $registry->register($other);

        $store = $this->createMock(SessionStoreInterface::class);
        $store->method('exists')->willReturn(true);
        $store->method('read')->willReturn(Json::encode(['initialized' => true]));
        $store->expects($this->once())
            ->method('write')
            ->with(
                static::callback(static fn (Uuid $uuid): bool => $uuid->toRfc4122() === $target),
                static::anything(),
            )
            ->willReturn(true);

        $notifier = new McpListChangedNotifier($store, $registry);
        $notifier->notifySession($target, new McpListChangedNotificationSet(tools: true, resources: false, prompts: false));
    }

    public function testNotifySessionDoesNotWriteWhenNoNotificationTypesChanged(): void
    {
        $store = $this->createMock(SessionStoreInterface::class);
        $store->expects($this->never())->method('write');

        $notifier = new McpListChangedNotifier($store, $this->registry());
        $notifier->notifySession(Uuid::v4()->toRfc4122(), McpListChangedNotificationSet::none());
    }

    public function testNotifySessionDoesNotWriteWhenSessionStoreIsUnavailable(): void
    {
        $store = null;

        $notifier = new McpListChangedNotifier($store, $this->registry());
        $notifier->notifySession(Uuid::v4()->toRfc4122(), new McpListChangedNotificationSet(tools: true, resources: false, prompts: false));

        // No exception and nothing to assert on a null store — the call is simply a no-op.
        $this->addToAssertionCount(1);
    }

    private function registry(): McpSessionRegistry
    {
        return new McpSessionRegistry(new Psr16Cache(new ArrayAdapter()));
    }
}
