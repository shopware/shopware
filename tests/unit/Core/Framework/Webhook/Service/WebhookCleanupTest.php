<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
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
        $config->expects(static::once())
            ->method('getInt')
            ->with('core.webhook.entryLifetimeSeconds')
            ->willReturn(-1);

        $conn = $this->createMock(Connection::class);
        $conn->expects(static::never())
            ->method('executeStatement');

        $cleaner = new WebhookCleanup($config, $conn, new MockClock());
        $cleaner->removeOldLogs();
    }

    public function testOldRecordsAreRemoved(): void
    {
        $config = $this->createMock(SystemConfigService::class);
        $config->expects(static::once())
            ->method('getInt')
            ->with('core.webhook.entryLifetimeSeconds')
            ->willReturn(86400);

        $conn = $this->createMock(Connection::class);
        $conn->expects(static::once())
            ->method('executeStatement')
            ->with(
                'DELETE FROM `webhook_event_log` WHERE `created_at` < :before AND (`delivery_status` = :success OR `delivery_status` = :failed) LIMIT :limit',
                [
                    'before' => '2023-01-01 13:04:00.000',
                    'success' => WebhookEventLogDefinition::STATUS_SUCCESS,
                    'failed' => WebhookEventLogDefinition::STATUS_FAILED,
                    'limit' => 500,
                ]
            );

        $cleaner = new WebhookCleanup($config, $conn, new MockClock(new \DateTimeImmutable('2 January 2023 13:04')));
        $cleaner->removeOldLogs();
    }

    public function testOldRecordsAreRemovedInBatched(): void
    {
        $config = $this->createMock(SystemConfigService::class);
        $config->expects(static::once())
            ->method('getInt')
            ->with('core.webhook.entryLifetimeSeconds')
            ->willReturn(86400);

        $conn = $this->createMock(Connection::class);
        $conn->expects(static::exactly(2))
            ->method('executeStatement')
            ->with(
                'DELETE FROM `webhook_event_log` WHERE `created_at` < :before AND (`delivery_status` = :success OR `delivery_status` = :failed) LIMIT :limit',
                [
                    'before' => '2023-01-01 13:04:00.000',
                    'success' => WebhookEventLogDefinition::STATUS_SUCCESS,
                    'failed' => WebhookEventLogDefinition::STATUS_FAILED,
                    'limit' => 500,
                ]
            )
            ->willReturnOnConsecutiveCalls(500, 302);

        $cleaner = new WebhookCleanup($config, $conn, new MockClock(new \DateTimeImmutable('2 January 2023 13:04')));
        $cleaner->removeOldLogs();
    }
}
