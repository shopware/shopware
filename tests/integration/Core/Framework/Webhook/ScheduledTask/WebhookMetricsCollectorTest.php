<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\ScheduledTask;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\ScheduledTask\WebhookMetricsCollector;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Clock\MockClock;

/**
 * Pins the SQL aggregate logic the snapshot/audit handlers depend on. The handlers themselves
 * are thin wrappers that just re-emit the captured scalars — the non-trivial bit is the SQL.
 *
 * @internal
 */
class WebhookMetricsCollectorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private MockClock $clock;

    private WebhookMetricsCollector $collector;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->clock = new MockClock(new \DateTimeImmutable('2026-04-30 12:00:00'));
        $this->collector = new WebhookMetricsCollector($this->connection, $this->clock);
        $this->ids = new IdsCollection();
        $this->connection->executeStatement('DELETE FROM webhook_delivery');
        $this->connection->executeStatement('DELETE FROM webhook_event_log');
        $this->connection->executeStatement('DELETE FROM webhook_stream');
    }

    public function testStuckInflightBucketsAreCumulative(): void
    {
        // Three RUNNING rows aged 16m, 65m, 25h — cumulative buckets must report
        // {15m: 3, 1h: 2, 24h: 1}. The 24h-stuck row also counts in 1h and 15m.
        $this->insertStuckRow('evt-16m', WebhookEventLogDefinition::STATUS_RUNNING, '-16 minutes');
        $this->insertStuckRow('evt-65m', WebhookEventLogDefinition::STATUS_RUNNING, '-65 minutes');
        $this->insertStuckRow('evt-25h', WebhookEventLogDefinition::STATUS_RUNNING, '-25 hours');

        // A fresh RUNNING row stays out of all three buckets.
        $this->insertStuckRow('evt-fresh', WebhookEventLogDefinition::STATUS_RUNNING, '-30 seconds');

        // Terminal mirrors must NOT be counted regardless of age.
        $this->insertStuckRow('evt-terminal-success', WebhookEventLogDefinition::STATUS_SUCCESS, '-25 hours');
        $this->insertStuckRow('evt-terminal-failed', WebhookEventLogDefinition::STATUS_FAILED, '-25 hours');

        $counts = $this->collector->countStuckInflight();

        static::assertSame(3, $counts->fifteenMinutes);
        static::assertSame(2, $counts->oneHour);
        static::assertSame(1, $counts->twentyFourHours);
    }

    public function testSnapshotPendingRetryReportsOldestOverdueAndIgnoresFutureRetries(): void
    {
        // Two PENDING_RETRY rows: one overdue (next_retry_at in the past), one future-only.
        // The gauge must reflect the overdue one's age and exclude the future one.
        $this->insertPendingRetryRow('evt-overdue', '-12 minutes');
        $this->insertPendingRetryRow('evt-future', '+5 minutes');

        $snapshot = $this->collector->snapshotQueueRowsByStatus();

        static::assertSame(2, $snapshot->pendingRetry->rows);
        // 12 minutes = 720 seconds, allow ±2s for clock drift in test setup
        static::assertEqualsWithDelta(720, $snapshot->pendingRetry->oldestAgeSeconds, 2);

        // Drop the overdue row → only the future row remains → gauge clamps to 0.
        $this->connection->executeStatement(
            'DELETE FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $this->ids->getBytes('evt-overdue')]
        );
        $this->connection->executeStatement(
            'DELETE FROM webhook_event_log WHERE id = :id',
            ['id' => $this->ids->getBytes('evt-overdue')]
        );

        $snapshot = $this->collector->snapshotQueueRowsByStatus();
        static::assertSame(1, $snapshot->pendingRetry->rows);
        static::assertSame(0, $snapshot->pendingRetry->oldestAgeSeconds);
    }

    private function insertStuckRow(string $eventKey, string $deliveryStatus, string $relativeAge): void
    {
        $timestamp = $this->clock->now()->modify($relativeAge)->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $eventLogStatus = match ($deliveryStatus) {
            WebhookEventLogDefinition::STATUS_SUCCESS, WebhookEventLogDefinition::STATUS_FAILED => $deliveryStatus,
            default => WebhookEventLogDefinition::STATUS_RUNNING,
        };

        $this->connection->insert('webhook_event_log', [
            'id' => $this->ids->getBytes($eventKey),
            'delivery_status' => $eventLogStatus,
            'webhook_name' => 'test-hook',
            'event_name' => 'product.written',
            'url' => 'https://example.com/webhook',
            'created_at' => $timestamp,
        ]);
        $this->connection->insert('webhook_delivery', [
            'webhook_event_log_id' => $this->ids->getBytes($eventKey),
            'webhook_id' => null,
            'partition_key' => Hasher::hashBinary('app-' . $eventKey, 'xxh128'),
            'delivery_status' => $deliveryStatus,
            'execution_count' => 1,
            'last_attempt_at' => $deliveryStatus === WebhookEventLogDefinition::STATUS_RUNNING ? $timestamp : null,
            'next_retry_at' => null,
            'created_at' => $timestamp,
        ]);
    }

    private function insertPendingRetryRow(string $eventKey, string $relativeRetryAt): void
    {
        $now = $this->clock->now();
        $nextRetryAt = $now->modify($relativeRetryAt)->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $createdAt = $now->modify('-30 minutes')->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('webhook_event_log', [
            'id' => $this->ids->getBytes($eventKey),
            'delivery_status' => WebhookEventLogDefinition::STATUS_PENDING_RETRY,
            'webhook_name' => 'test-hook',
            'event_name' => 'product.written',
            'url' => 'https://example.com/webhook',
            'created_at' => $createdAt,
        ]);
        $this->connection->insert('webhook_delivery', [
            'webhook_event_log_id' => $this->ids->getBytes($eventKey),
            'webhook_id' => null,
            'partition_key' => Hasher::hashBinary('app-' . $eventKey, 'xxh128'),
            'delivery_status' => WebhookEventLogDefinition::STATUS_PENDING_RETRY,
            'execution_count' => 2,
            'last_attempt_at' => $now->modify('-15 minutes')->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'next_retry_at' => $nextRetryAt,
            'created_at' => $createdAt,
        ]);
    }
}
