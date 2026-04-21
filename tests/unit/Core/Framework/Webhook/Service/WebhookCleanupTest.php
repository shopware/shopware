<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Webhook\Outbox\StreamLockService;
use Shopware\Core\Framework\Webhook\Service\WebhookCleanup;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[CoversClass(WebhookCleanup::class)]
class WebhookCleanupTest extends TestCase
{
    public function testNothingIsRemovedIfLifetimeIsMinus1(): void
    {
        $config = $this->createMock(SystemConfigService::class);
        $config->expects($this->once())
            ->method('getInt')
            ->with('core.webhook.entryLifetimeSeconds')
            ->willReturn(-1);

        $conn = $this->createMock(Connection::class);
        $conn->expects($this->never())
            ->method('executeStatement');

        $streamLockService = $this->createMock(StreamLockService::class);
        $streamLockService->expects($this->never())->method('deleteOrphanedStreams');

        $cleaner = new WebhookCleanup($config, $conn, $streamLockService, new MockClock());
        $cleaner->removeOldLogs();
    }

    public function testOldRecordsAreRemovedInBatches(): void
    {
        $config = $this->createMock(SystemConfigService::class);
        $config->expects($this->once())
            ->method('getInt')
            ->with('core.webhook.entryLifetimeSeconds')
            ->willReturn(86400);

        $conn = $this->createMock(Connection::class);
        $conn->expects($this->exactly(3))
            ->method('executeStatement')
            ->willReturnOnConsecutiveCalls(500, 302, 300);

        $streamLockService = $this->createMock(StreamLockService::class);
        $streamLockService->expects($this->once())->method('deleteOrphanedStreams')->willReturn(0);

        $cleaner = new WebhookCleanup($config, $conn, $streamLockService, new MockClock(new \DateTimeImmutable('2 January 2023 13:04')));
        $cleaner->removeOldLogs();
    }

    public function testOrphanStreamsAreLoopedUntilDrained(): void
    {
        $config = $this->createMock(SystemConfigService::class);
        $config->expects($this->once())
            ->method('getInt')
            ->with('core.webhook.entryLifetimeSeconds')
            ->willReturn(86400);

        $conn = $this->createMock(Connection::class);
        $conn->method('executeStatement')->willReturn(0);

        $streamLockService = $this->createMock(StreamLockService::class);
        $streamLockService->expects($this->exactly(3))
            ->method('deleteOrphanedStreams')
            ->willReturnOnConsecutiveCalls(500, 500, 42);

        $cleaner = new WebhookCleanup($config, $conn, $streamLockService, new MockClock(new \DateTimeImmutable('2 January 2023 13:04')));
        $cleaner->removeOldLogs();
    }
}
