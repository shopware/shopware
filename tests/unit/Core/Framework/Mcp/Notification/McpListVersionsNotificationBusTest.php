<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Notification;

use Mcp\Schema\JsonRpc\Notification;
use Mcp\Schema\Notification\PromptListChangedNotification;
use Mcp\Schema\Notification\ResourceListChangedNotification;
use Mcp\Schema\Notification\ResourceUpdatedNotification;
use Mcp\Schema\Notification\ToolListChangedNotification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotificationSet;
use Shopware\Core\Framework\Mcp\Notification\McpListVersions;
use Shopware\Core\Framework\Mcp\Notification\McpListVersionsNotificationBus;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpListVersionsNotificationBus::class)]
class McpListVersionsNotificationBusTest extends TestCase
{
    /**
     * @var array{tools: int, resources: int, prompts: int}
     */
    private array $versions = ['tools' => 0, 'resources' => 0, 'prompts' => 0];

    private int $reads = 0;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock();
    }

    public function testTheCursorIsTheSameWhileNoListChanges(): void
    {
        $this->versions = ['tools' => 4, 'resources' => 2, 'prompts' => 7];
        $bus = $this->bus();

        $cursor = $bus->cursor();

        static::assertSame([[], $cursor], $bus->since($cursor));
        static::assertGreaterThanOrEqual(0, $cursor);
    }

    /**
     * @param array{tools: int, resources: int, prompts: int} $next
     * @param list<string> $expected
     */
    #[DataProvider('changedListsProvider')]
    public function testSinceAnnouncesOnlyTheListsWhoseVersionMoved(array $next, array $expected): void
    {
        $this->versions = ['tools' => 4, 'resources' => 2, 'prompts' => 7];
        $bus = $this->bus();
        $cursor = $bus->cursor();

        $this->versions = $next;
        $this->clock->sleep(McpListVersionsNotificationBus::CACHE_TTL + 1);

        [$notifications, $nextCursor] = $bus->since($cursor);

        static::assertSame($expected, self::methods($notifications));
        static::assertNotSame($cursor, $nextCursor);
        static::assertSame([[], $nextCursor], $bus->since($nextCursor), 'a change is announced once');
    }

    /**
     * @return iterable<string, array{array{tools: int, resources: int, prompts: int}, list<string>}>
     */
    public static function changedListsProvider(): iterable
    {
        yield 'tools' => [['tools' => 5, 'resources' => 2, 'prompts' => 7], ['notifications/tools/list_changed']];
        yield 'resources' => [['tools' => 4, 'resources' => 3, 'prompts' => 7], ['notifications/resources/list_changed']];
        yield 'prompts' => [['tools' => 4, 'resources' => 2, 'prompts' => 9], ['notifications/prompts/list_changed']];
        yield 'all three' => [
            ['tools' => 5, 'resources' => 3, 'prompts' => 8],
            ['notifications/tools/list_changed', 'notifications/resources/list_changed', 'notifications/prompts/list_changed'],
        ];
        yield 'a version beyond the bits kept in the cursor' => [
            ['tools' => (1 << 21) + 4, 'resources' => 2, 'prompts' => 8],
            ['notifications/prompts/list_changed'],
        ];
    }

    public function testTheVersionsAreReadAtMostOncePerCacheLifetime(): void
    {
        $bus = $this->bus();
        $cursor = $bus->cursor();

        $this->versions = ['tools' => 1, 'resources' => 0, 'prompts' => 0];
        static::assertSame([[], $cursor], $bus->since($cursor), 'the cached versions are still fresh');
        static::assertSame([[], $cursor], $bus->since($cursor));
        static::assertSame(1, $this->reads);

        $this->clock->sleep(McpListVersionsNotificationBus::CACHE_TTL + 1);

        static::assertSame(['notifications/tools/list_changed'], self::methods($bus->since($cursor)[0]));
        static::assertSame(2, $this->reads);
    }

    #[DataProvider('publishedNotificationProvider')]
    public function testPublishBumpsTheListTheNotificationNames(Notification $notification, McpListChangedNotificationSet $expected): void
    {
        $listVersions = $this->createMock(McpListVersions::class);
        $listVersions->expects($this->once())->method('bump')->with($expected);

        (new McpListVersionsNotificationBus($listVersions, new ArrayAdapter(), new NullLogger()))->publish($notification);
    }

    /**
     * @return iterable<string, array{Notification, McpListChangedNotificationSet}>
     */
    public static function publishedNotificationProvider(): iterable
    {
        yield 'tools' => [new ToolListChangedNotification(), new McpListChangedNotificationSet(tools: true, resources: false, prompts: false)];
        yield 'resources' => [new ResourceListChangedNotification(), new McpListChangedNotificationSet(tools: false, resources: true, prompts: false)];
        yield 'prompts' => [new PromptListChangedNotification(), new McpListChangedNotificationSet(tools: false, resources: false, prompts: true)];
    }

    public function testANotificationThatNamesNoListIsDropped(): void
    {
        $listVersions = $this->createMock(McpListVersions::class);
        $listVersions->expects($this->never())->method('bump');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('notice');

        (new McpListVersionsNotificationBus($listVersions, new ArrayAdapter(), $logger))
            ->publish(new ResourceUpdatedNotification('shopware://entities'));
    }

    public function testAFailingReadKeepsTheStreamAtItsCursor(): void
    {
        $listVersions = static::createStub(McpListVersions::class);
        $listVersions->method('current')->willThrowException(new \RuntimeException('database down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $bus = new McpListVersionsNotificationBus($listVersions, new ArrayAdapter(), $logger);

        static::assertSame([[], 42], $bus->since(42));
    }

    public function testAnUnreadableCacheEntryCountsAsVersionZero(): void
    {
        $cache = new ArrayAdapter();
        $cache->get(McpListVersionsNotificationBus::CACHE_KEY, static fn (): string => 'garbage');

        $bus = new McpListVersionsNotificationBus(static::createStub(McpListVersions::class), $cache, new NullLogger());

        static::assertSame(0, $bus->cursor());
    }

    private function bus(): McpListVersionsNotificationBus
    {
        $listVersions = static::createStub(McpListVersions::class);
        $listVersions->method('current')->willReturnCallback(function (): array {
            ++$this->reads;

            return $this->versions;
        });

        return new McpListVersionsNotificationBus($listVersions, new ArrayAdapter(clock: $this->clock), new NullLogger());
    }

    /**
     * @param list<Notification> $notifications
     *
     * @return list<string>
     */
    private static function methods(array $notifications): array
    {
        return array_map(static fn (Notification $notification): string => $notification::getMethod(), $notifications);
    }
}
