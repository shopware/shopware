<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\SystemCheck;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Event\SystemHeartbeatEvent;
use Shopware\Core\Framework\SystemCheck\SystemHeartbeat;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(SystemHeartbeat::class)]
class SystemHeartbeatTest extends TestCase
{
    private EventDispatcher&MockObject $eventDispatcher;

    private CacheItemPoolInterface&MockObject $cachePool;

    private SystemHeartbeat $check;

    protected function setUp(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcher::class);
        $this->cachePool = $this->createMock(CacheItemPoolInterface::class);

        $this->check = new SystemHeartbeat($this->eventDispatcher, $this->cachePool);
    }

    public function testRunDispatchesEventAndReturnsOkOnCacheMiss(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->once())->method('isHit')->willReturn(false);
        $item->expects($this->never())->method('get');

        $this->cachePool->expects($this->once())->method('getItem')->with('system_heartbeat.last_run')->willReturn($item);
        $this->cachePool->expects($this->once())->method('save')->with($item);

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(SystemHeartbeatEvent::class));

        $item->expects($this->once())->method('set')->with(static::isInstanceOf(\DateTimeImmutable::class));

        $result = $this->check->run();

        static::assertSame(Status::OK, $result->status);
        static::assertSame('System Heartbeat indicated successfully.', $result->message);
        static::assertSame('System Heartbeat', $result->name);
    }

    public function testRunDispatchesEventWhenCacheReturnsNullValue(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->once())->method('isHit')->willReturn(true);
        $item->expects($this->once())->method('get')->willReturn(null);

        $this->cachePool
            ->expects($this->once())
            ->method('getItem')
            ->with('system_heartbeat.last_run')
            ->willReturn($item);
        $this->cachePool->expects($this->once())->method('save');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(SystemHeartbeatEvent::class));

        $result = $this->check->run();

        static::assertSame(Status::OK, $result->status);
    }

    public function testRunSavesDateTimeToCache(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->once())->method('isHit')->willReturn(false);
        $item->expects($this->never())->method('get');

        $item->expects($this->once())->method('set')
            ->with(static::isInstanceOf(\DateTimeImmutable::class));

        $this->cachePool->expects($this->once())->method('getItem')->willReturn($item);
        $this->cachePool->expects($this->once())->method('save')->with($item);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(SystemHeartbeatEvent::class));

        $this->check->run();
    }

    public function testRunSkipsWhenLastBeatIsWithinThreshold(): void
    {
        $recentBeat = new \DateTimeImmutable('-6 hours');

        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->once())->method('isHit')->willReturn(true);
        $item->expects($this->once())->method('get')->willReturn($recentBeat);

        $this->cachePool->expects($this->once())->method('getItem')->with('system_heartbeat.last_run')->willReturn($item);
        $this->cachePool->expects($this->never())->method('save');

        $this->eventDispatcher->expects($this->never())->method('dispatch');

        $result = $this->check->run();

        static::assertSame(Status::SKIPPED, $result->status);
        static::assertSame('System Heartbeat skipped due to recent execution.', $result->message);
    }

    public function testRunDispatchesEventWhenLastBeatIsOlderThanThreshold(): void
    {
        $oldBeat = new \DateTimeImmutable('-13 hours');

        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->once())->method('isHit')->willReturn(true);
        $item->expects($this->once())->method('get')->willReturn($oldBeat);

        $this->cachePool->expects($this->once())->method('getItem')->with('system_heartbeat.last_run')->willReturn($item);
        $this->cachePool->expects($this->once())->method('save')->with($item);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(SystemHeartbeatEvent::class));

        $result = $this->check->run();

        static::assertSame(Status::OK, $result->status);
        static::assertSame('System Heartbeat indicated successfully.', $result->message);
    }
}
