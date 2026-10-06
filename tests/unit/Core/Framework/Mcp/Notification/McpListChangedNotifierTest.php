<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Notification;

use Mcp\Server\Session\InMemorySessionStore;
use Mcp\Server\Session\SessionStoreInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotificationSet;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotifier;
use Shopware\Core\Framework\Mcp\Notification\McpListVersions;
use Shopware\Core\Framework\Util\Json;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpListChangedNotifier::class)]
class McpListChangedNotifierTest extends TestCase
{
    private const TOOLS_CHANGED = 'notifications/tools/list_changed';
    private const RESOURCES_CHANGED = 'notifications/resources/list_changed';
    private const PROMPTS_CHANGED = 'notifications/prompts/list_changed';

    private InMemorySessionStore $store;

    /**
     * @var array{tools: int, resources: int, prompts: int}
     */
    private array $versions = ['tools' => 0, 'resources' => 0, 'prompts' => 0];

    protected function setUp(): void
    {
        $this->store = new InMemorySessionStore();
    }

    public function testNotifyBumpsTheListVersions(): void
    {
        $listVersions = $this->createMock(McpListVersions::class);
        $changes = new McpListChangedNotificationSet(tools: true, resources: false, prompts: true);
        $listVersions->expects($this->once())->method('bump')->with($changes);

        (new McpListChangedNotifier($this->store, $listVersions))->notify($changes);
    }

    public function testNotifyWithoutChangesDoesNothing(): void
    {
        $listVersions = $this->createMock(McpListVersions::class);
        $listVersions->expects($this->never())->method('bump');

        (new McpListChangedNotifier($this->store, $listVersions))->notify(McpListChangedNotificationSet::none());
    }

    public function testANewSessionStartsAtTheCurrentVersionsWithoutANotification(): void
    {
        $this->versions = ['tools' => 3, 'resources' => 1, 'prompts' => 0];
        $sessionId = $this->session();

        $this->notifier()->syncSession($sessionId);

        static::assertSame([], $this->queuedMethods($sessionId));
        static::assertSame($this->versions, $this->sessionData($sessionId)['shopware_list_versions']);
    }

    public function testASessionIsNotifiedAboutTheListsThatChangedSinceItLastLooked(): void
    {
        $sessionId = $this->session();
        $notifier = $this->notifier();
        $notifier->syncSession($sessionId);

        $this->versions = ['tools' => 1, 'resources' => 0, 'prompts' => 1];
        $notifier->syncSession($sessionId);

        static::assertSame([self::TOOLS_CHANGED, self::PROMPTS_CHANGED], $this->queuedMethods($sessionId));
        static::assertSame($this->versions, $this->sessionData($sessionId)['shopware_list_versions']);
    }

    public function testEachChangeIsQueuedOnlyOnce(): void
    {
        $sessionId = $this->session();
        $notifier = $this->notifier();
        $notifier->syncSession($sessionId);

        $this->versions['resources'] = 1;
        $notifier->syncSession($sessionId);
        $notifier->syncSession($sessionId);

        static::assertSame([self::RESOURCES_CHANGED], $this->queuedMethods($sessionId));
    }

    public function testSyncKeepsTheDataTheSdkStoredInTheSession(): void
    {
        $sessionId = $this->session(['initialized' => true, '_mcp' => ['outgoing_queue' => [['message' => 'queued', 'context' => []]]]]);
        $notifier = $this->notifier();
        $notifier->syncSession($sessionId);

        $this->versions['tools'] = 1;
        $notifier->syncSession($sessionId);

        $data = $this->sessionData($sessionId);
        static::assertTrue($data['initialized']);
        static::assertCount(2, $data['_mcp']['outgoing_queue']);
        static::assertSame('queued', $data['_mcp']['outgoing_queue'][0]['message']);
    }

    public function testSyncIgnoresUnknownAndInvalidSessions(): void
    {
        $store = $this->createMock(SessionStoreInterface::class);
        $store->method('exists')->willReturn(false);
        $store->expects($this->never())->method('write');

        $notifier = new McpListChangedNotifier($store, $this->listVersions());
        $notifier->syncSession(Uuid::v4()->toRfc4122());
        $notifier->syncSession('not-a-uuid');
    }

    public function testSyncDoesNothingWithoutListVersions(): void
    {
        $sessionId = $this->session();

        (new McpListChangedNotifier($this->store))->syncSession($sessionId);

        static::assertArrayNotHasKey('shopware_list_versions', $this->sessionData($sessionId));
    }

    public function testSkipsUnreadableSessionData(): void
    {
        $store = $this->createMock(SessionStoreInterface::class);
        $store->method('exists')->willReturn(true);
        $store->method('read')->willReturn('{broken');
        $store->expects($this->never())->method('write');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        (new McpListChangedNotifier($store, $this->listVersions(), $logger))->syncSession(Uuid::v4()->toRfc4122());
    }

    public function testNotifySessionQueuesForThatSessionOnly(): void
    {
        $target = $this->session();
        $other = $this->session();

        $this->notifier()->notifySession($target, new McpListChangedNotificationSet(tools: true, resources: true, prompts: true));

        static::assertSame([self::TOOLS_CHANGED, self::RESOURCES_CHANGED, self::PROMPTS_CHANGED], $this->queuedMethods($target));
        static::assertSame([], $this->queuedMethods($other));
    }

    public function testNotifySessionNormalizesMalformedQueueData(): void
    {
        $queueNotAList = $this->session(['_mcp' => ['outgoing_queue' => 'not-a-list']]);
        $mcpNotAnArray = $this->session(['_mcp' => 'not-an-array']);
        $notifier = $this->notifier();

        foreach ([$queueNotAList, $mcpNotAnArray] as $sessionId) {
            $notifier->notifySession($sessionId, new McpListChangedNotificationSet(tools: true, resources: false, prompts: false));

            static::assertSame([self::TOOLS_CHANGED], $this->queuedMethods($sessionId));
        }
    }

    public function testNotifySessionDoesNothingWithoutChangesOrStore(): void
    {
        $store = $this->createMock(SessionStoreInterface::class);
        $store->expects($this->never())->method('write');

        (new McpListChangedNotifier($store))->notifySession(Uuid::v4()->toRfc4122(), McpListChangedNotificationSet::none());
        (new McpListChangedNotifier(null))->notifySession(Uuid::v4()->toRfc4122(), new McpListChangedNotificationSet(tools: true, resources: false, prompts: false));
    }

    private function notifier(): McpListChangedNotifier
    {
        return new McpListChangedNotifier($this->store, $this->listVersions());
    }

    private function listVersions(): McpListVersions
    {
        $listVersions = static::createStub(McpListVersions::class);
        $listVersions->method('current')->willReturnCallback(fn (): array => $this->versions);

        return $listVersions;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function session(array $data = ['initialized' => true]): string
    {
        $uuid = Uuid::v4();
        $this->store->write($uuid, Json::encode($data));

        return $uuid->toRfc4122();
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionData(string $sessionId): array
    {
        $raw = $this->store->read(Uuid::fromString($sessionId));
        static::assertIsString($raw);

        return Json::decodeToArray($raw);
    }

    /**
     * @return list<string>
     */
    private function queuedMethods(string $sessionId): array
    {
        $queue = $this->sessionData($sessionId)['_mcp']['outgoing_queue'] ?? [];

        return array_values(array_map(
            static fn (array $queued): string => Json::decodeToArray($queued['message'])['method'],
            array_filter($queue, static fn (array $queued): bool => ($queued['context']['type'] ?? null) === 'notification'),
        ));
    }
}
