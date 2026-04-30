<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\ScheduledTask;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Telemetry\WebhookAuditAgeBucket;

/**
 * @internal
 */
#[Package('framework')]
class WebhookMetricsCollector
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return array{
     *     queued_rows: int,
     *     pending_retry_rows: int,
     *     running_rows: int,
     *     queued_oldest_age_seconds: int,
     *     pending_retry_oldest_age_seconds: int,
     *     running_oldest_age_seconds: int
     * }
     */
    public function snapshotQueueRowsByStatus(): array
    {
        $now = $this->clock->now();
        $nowFormatted = $now->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT
                    SUM(delivery_status = :queued) AS queued_count,
                    SUM(delivery_status = :pending_retry) AS pending_retry_count,
                    SUM(delivery_status = :running) AS running_count,
                    MIN(CASE WHEN delivery_status = :queued THEN created_at END) AS queued_oldest,
                    MIN(CASE WHEN delivery_status = :pending_retry AND next_retry_at <= :now THEN next_retry_at END) AS pending_retry_oldest,
                    MIN(CASE WHEN delivery_status = :running THEN COALESCE(last_attempt_at, created_at) END) AS running_oldest
                FROM webhook_delivery
            SQL,
            [
                'queued' => WebhookEventLogDefinition::STATUS_QUEUED,
                'pending_retry' => WebhookEventLogDefinition::STATUS_PENDING_RETRY,
                'running' => WebhookEventLogDefinition::STATUS_RUNNING,
                'now' => $nowFormatted,
            ]
        );

        $nowTs = $now->getTimestamp();

        return [
            'queued_rows' => $row !== false ? (int) ($row['queued_count'] ?? 0) : 0,
            'pending_retry_rows' => $row !== false ? (int) ($row['pending_retry_count'] ?? 0) : 0,
            'running_rows' => $row !== false ? (int) ($row['running_count'] ?? 0) : 0,
            'queued_oldest_age_seconds' => $this->ageSeconds($row !== false ? $row['queued_oldest'] ?? null : null, $nowTs),
            'pending_retry_oldest_age_seconds' => $this->ageSeconds($row !== false ? $row['pending_retry_oldest'] ?? null : null, $nowTs),
            'running_oldest_age_seconds' => $this->ageSeconds($row !== false ? $row['running_oldest'] ?? null : null, $nowTs),
        ];
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
     * Cumulative bucket count: a row stuck for 24h is also counted in the 1h and 15m buckets.
     * Excludes terminal event-log mirrors so cleanup-pending rows don't show up.
     *
     * @return array{'15m': int, '1h': int, '24h': int}
     */
    public function countStuckInflight(): array
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
        );

        return [
            WebhookAuditAgeBucket::FIFTEEN_MINUTES->value => $row !== false ? (int) ($row['bucket_15m'] ?? 0) : 0,
            WebhookAuditAgeBucket::ONE_HOUR->value => $row !== false ? (int) ($row['bucket_1h'] ?? 0) : 0,
            WebhookAuditAgeBucket::TWENTY_FOUR_HOURS->value => $row !== false ? (int) ($row['bucket_24h'] ?? 0) : 0,
        ];
    }

    private function ageSeconds(mixed $rawTimestamp, int $nowTs): int
    {
        if ($rawTimestamp === null) {
            return 0;
        }

        return max(0, $nowTs - (new \DateTimeImmutable((string) $rawTimestamp))->getTimestamp());
    }
}
