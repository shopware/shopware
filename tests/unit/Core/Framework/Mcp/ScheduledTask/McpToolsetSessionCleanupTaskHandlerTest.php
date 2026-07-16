<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\ScheduledTask;

use Mcp\Server\Session\SessionStoreInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Mcp\McpToolsetSessionStorage;
use Shopware\Core\Framework\Mcp\ScheduledTask\McpToolsetSessionCleanupTaskHandler;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\AbstractUid;

/**
 * @internal
 */
#[CoversClass(McpToolsetSessionCleanupTaskHandler::class)]
class McpToolsetSessionCleanupTaskHandlerTest extends TestCase
{
    private const ALIVE_SESSION = 'aaaaaaaa-bbbb-4ccc-8ddd-000000000001';
    private const EXPIRED_SESSION = 'aaaaaaaa-bbbb-4ccc-8ddd-000000000002';
    private const MALFORMED_SESSION = 'not-a-uuid';

    public function testRunAlwaysPurgesRowsOlderThanHardRetention(): void
    {
        $now = new \DateTimeImmutable('2026-07-16 12:00:00');

        $storage = $this->createMock(McpToolsetSessionStorage::class);
        $storage->expects($this->once())
            ->method('deleteCreatedBefore')
            ->with(static::callback(static fn (\DateTimeInterface $before): bool => $before->format('Y-m-d H:i:s') === '2026-07-15 12:00:00'));
        $storage->method('sessionIds')->willReturn([]);

        $handler = new McpToolsetSessionCleanupTaskHandler(
            static::createStub(EntityRepository::class),
            new NullLogger(),
            $storage,
            new MockClock($now),
            static::createStub(SessionStoreInterface::class),
        );

        $handler->run();
    }

    public function testRunDeletesRowsForExpiredAndMalformedSessionsOnly(): void
    {
        $storage = $this->createMock(McpToolsetSessionStorage::class);
        $storage->expects($this->once())->method('deleteCreatedBefore');
        $storage->method('sessionIds')->willReturn([self::ALIVE_SESSION, self::EXPIRED_SESSION, self::MALFORMED_SESSION]);

        $sessionStore = static::createStub(SessionStoreInterface::class);
        $sessionStore->method('exists')->willReturnCallback(
            static fn (AbstractUid $uuid): bool => $uuid->toRfc4122() === self::ALIVE_SESSION,
        );

        $deleted = [];
        $storage->method('deleteForSession')->willReturnCallback(static function (string $sessionId) use (&$deleted): void {
            $deleted[] = $sessionId;
        });

        $handler = new McpToolsetSessionCleanupTaskHandler(
            static::createStub(EntityRepository::class),
            new NullLogger(),
            $storage,
            new MockClock(),
            $sessionStore,
        );

        $handler->run();

        sort($deleted);
        static::assertSame([self::EXPIRED_SESSION, self::MALFORMED_SESSION], $deleted);
    }

    public function testRunSkipsSessionStoreScanWhenStoreIsUnavailable(): void
    {
        $storage = $this->createMock(McpToolsetSessionStorage::class);
        $storage->expects($this->once())->method('deleteCreatedBefore');
        $storage->expects($this->never())->method('sessionIds');
        $storage->expects($this->never())->method('deleteForSession');

        $handler = new McpToolsetSessionCleanupTaskHandler(
            static::createStub(EntityRepository::class),
            new NullLogger(),
            $storage,
            new MockClock(),
            null,
        );

        $handler->run();
    }
}
