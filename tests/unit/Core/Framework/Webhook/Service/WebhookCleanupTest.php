<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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

        $cleaner = new WebhookCleanup($config, $conn, new MockClock());
        $cleaner->removeOldLogs();
    }

    public function testOldRecordsAreRemoved(): void
    {
        $config = $this->createMock(SystemConfigService::class);
        $config->expects($this->once())
            ->method('getInt')
            ->with('core.webhook.entryLifetimeSeconds')
            ->willReturn(86400);

        $conn = $this->createMock(Connection::class);
        $conn->expects($this->exactly(2))
            ->method('executeStatement');

        $cleaner = new WebhookCleanup($config, $conn, new MockClock(new \DateTimeImmutable('2 January 2023 13:04')));
        $cleaner->removeOldLogs();
    }

    public function testOldRecordsAreRemovedInBatched(): void
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

        $cleaner = new WebhookCleanup($config, $conn, new MockClock(new \DateTimeImmutable('2 January 2023 13:04')));
        $cleaner->removeOldLogs();
    }
}
