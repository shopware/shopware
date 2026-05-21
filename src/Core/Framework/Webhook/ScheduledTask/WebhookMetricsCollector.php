<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\ScheduledTask;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;

/**
 * @internal
 *
 * @codeCoverageIgnore Integration tested with \Shopware\Tests\Integration\Core\Framework\Webhook\ScheduledTask\WebhookMetricsCollectorTest
 */
#[Package('framework')]
class WebhookMetricsCollector
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    public function snapshotQueueRowsByStatus(): WebhookQueueSnapshot
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT
                    SUM(delivery_status = :queued) AS queued_count,
                    SUM(delivery_status = :pending_retry) AS pending_retry_count,
                    SUM(delivery_status = :running) AS running_count,
                    TIMESTAMPDIFF(SECOND, MIN(CASE WHEN delivery_status = :queued THEN created_at END), :now) AS queued_age,
                    TIMESTAMPDIFF(SECOND, MIN(CASE WHEN delivery_status = :pending_retry AND next_retry_at <= :now THEN next_retry_at END), :now) AS pending_retry_age,
                    TIMESTAMPDIFF(SECOND, MIN(CASE WHEN delivery_status = :running THEN COALESCE(last_attempt_at, created_at) END), :now) AS running_age
                FROM webhook_delivery
            SQL,
            [
                'queued' => WebhookEventLogDefinition::STATUS_QUEUED,
                'pending_retry' => WebhookEventLogDefinition::STATUS_PENDING_RETRY,
                'running' => WebhookEventLogDefinition::STATUS_RUNNING,
                'now' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        ) ?: [];

        return new WebhookQueueSnapshot(
            queued: new WebhookQueueGauge(
                rows: (int) ($row['queued_count'] ?? 0),
                oldestAgeSeconds: max(0, (int) ($row['queued_age'] ?? 0)),
            ),
            pendingRetry: new WebhookQueueGauge(
                rows: (int) ($row['pending_retry_count'] ?? 0),
                oldestAgeSeconds: max(0, (int) ($row['pending_retry_age'] ?? 0)),
            ),
            running: new WebhookQueueGauge(
                rows: (int) ($row['running_count'] ?? 0),
                oldestAgeSeconds: max(0, (int) ($row['running_age'] ?? 0)),
            ),
        );
    }

    public function countStaleStreams(): int
    {
        $value = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM webhook_stream
             WHERE locked_by IS NOT NULL
               AND (lock_expires_at IS NULL OR lock_expires_at <= :now)',
            ['now' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT)]
        );

        return $value === false ? 0 : (int) $value;
    }

    /**
     * Excludes terminal event-log mirrors so cleanup-pending rows don't show up.
     */
    public function countStuckInflight(): WebhookStuckInflightCounts
    {
        $now = $this->clock->now();
        $cutoff15m = $now->modify('-15 minutes')->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $cutoff1h = $now->modify('-1 hour')->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $cutoff24h = $now->modify('-24 hours')->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT
                    SUM(
                        (d.delivery_status = :queued AND d.created_at <= :cutoff15m)
                        OR (d.delivery_status = :pending_retry AND d.next_retry_at <= :cutoff15m)
                        OR (d.delivery_status = :running AND d.last_attempt_at <= :cutoff15m)
                    ) AS bucket_15m,
                    SUM(
                        (d.delivery_status = :queued AND d.created_at <= :cutoff1h)
                        OR (d.delivery_status = :pending_retry AND d.next_retry_at <= :cutoff1h)
                        OR (d.delivery_status = :running AND d.last_attempt_at <= :cutoff1h)
                    ) AS bucket_1h,
                    SUM(
                        (d.delivery_status = :queued AND d.created_at <= :cutoff24h)
                        OR (d.delivery_status = :pending_retry AND d.next_retry_at <= :cutoff24h)
                        OR (d.delivery_status = :running AND d.last_attempt_at <= :cutoff24h)
                    ) AS bucket_24h
                FROM webhook_delivery d
                JOIN webhook_event_log el ON el.id = d.webhook_event_log_id
                WHERE el.delivery_status NOT IN (:success, :failed)
            SQL,
            [
                'queued' => WebhookEventLogDefinition::STATUS_QUEUED,
                'pending_retry' => WebhookEventLogDefinition::STATUS_PENDING_RETRY,
                'running' => WebhookEventLogDefinition::STATUS_RUNNING,
                'success' => WebhookEventLogDefinition::STATUS_SUCCESS,
                'failed' => WebhookEventLogDefinition::STATUS_FAILED,
                'cutoff15m' => $cutoff15m,
                'cutoff1h' => $cutoff1h,
                'cutoff24h' => $cutoff24h,
            ]
        ) ?: [];

        return new WebhookStuckInflightCounts(
            fifteenMinutes: (int) ($row['bucket_15m'] ?? 0),
            oneHour: (int) ($row['bucket_1h'] ?? 0),
            twentyFourHours: (int) ($row['bucket_24h'] ?? 0),
        );
    }
}
