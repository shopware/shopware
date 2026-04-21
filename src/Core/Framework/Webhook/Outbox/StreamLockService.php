<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Outbox;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class StreamLockService
{
    public const ORPHAN_GRACE_SECONDS = 60;

    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Atomically claims the next partition with at least one due delivery.
     * Returns null when nothing is claimable (either no streams, no due rows,
     * or all candidate rows are locked by other workers).
     *
     * @param non-empty-list<WebhookEventLogDefinition::STATUS_QUEUED|WebhookEventLogDefinition::STATUS_PENDING_RETRY> $statuses
     */
    public function claimNext(string $workerId, int $leaseSeconds, array $statuses): ?StreamLease
    {
        return $this->connection->transactional(function () use ($workerId, $leaseSeconds, $statuses): ?StreamLease {
            $now = $this->clock->now();
            $nowFormatted = $now->format(Defaults::STORAGE_DATE_TIME_FORMAT);

            // SKIP LOCKED returns empty on contention; caller retries on next tick.
            $sql = <<<'SQL'
                SELECT s.partition_key FROM webhook_stream s
                WHERE (s.locked_by IS NULL OR s.lock_expires_at <= :now)
                  AND EXISTS (
                      SELECT 1 FROM webhook_delivery d
                      WHERE d.partition_key = s.partition_key
                        AND d.delivery_status IN (:statuses) AND (d.next_retry_at IS NULL OR d.next_retry_at <= :now)
                  )
                ORDER BY s.last_claimed_at ASC, s.partition_key ASC
                LIMIT 1
                FOR UPDATE SKIP LOCKED
                SQL;

            $row = $this->connection->fetchAssociative(
                $sql,
                [
                    'now' => $nowFormatted,
                    'statuses' => $statuses,
                ],
                [
                    'statuses' => ArrayParameterType::STRING,
                ]
            );

            if ($row === false) {
                return null;
            }

            $partitionKey = $row['partition_key'];
            $expiresAt = $now->modify(\sprintf('+%d seconds', $leaseSeconds));

            $this->connection->executeStatement(
                'UPDATE webhook_stream SET locked_by = :workerId, lock_expires_at = :expiresAt, last_claimed_at = :now WHERE partition_key = :pk',
                [
                    'workerId' => $workerId,
                    'expiresAt' => $expiresAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'now' => $nowFormatted,
                    'pk' => $partitionKey,
                ]
            );

            return new StreamLease(
                partitionKey: $partitionKey,
                workerId: $workerId,
                expiresAt: \DateTimeImmutable::createFromInterface($expiresAt),
            );
        });
    }

    /**
     * Refreshes the lease expiration. Returns the renewed lease (with updated expiresAt)
     * on success, or null if the lease was stolen (another worker's locked_by is on the row now).
     */
    public function heartbeat(StreamLease $lease, int $leaseSeconds): ?StreamLease
    {
        $expiresAt = \DateTimeImmutable::createFromInterface(
            $this->clock->now()->modify(\sprintf('+%d seconds', $leaseSeconds))
        );

        $affected = (int) $this->connection->executeStatement(
            'UPDATE webhook_stream SET lock_expires_at = :expiresAt WHERE partition_key = :pk AND locked_by = :workerId',
            [
                'expiresAt' => $expiresAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'pk' => $lease->partitionKey,
                'workerId' => $lease->workerId,
            ]
        );

        if ($affected === 0) {
            return null;
        }

        return new StreamLease(
            partitionKey: $lease->partitionKey,
            workerId: $lease->workerId,
            expiresAt: $expiresAt,
        );
    }

    /**
     * Releases the lease by clearing locked_by / lock_expires_at.
     * No-op if the lease was already stolen.
     */
    public function release(StreamLease $lease): void
    {
        $this->connection->executeStatement(
            'UPDATE webhook_stream SET locked_by = NULL, lock_expires_at = NULL WHERE partition_key = :pk AND locked_by = :workerId',
            [
                'pk' => $lease->partitionKey,
                'workerId' => $lease->workerId,
            ]
        );
    }

    /**
     * Deletes webhook_stream rows that have no corresponding webhook_delivery rows,
     * are unlocked or past their lease, and are older than {@see self::ORPHAN_GRACE_SECONDS}.
     */
    public function deleteOrphanedStreams(int $batchSize): int
    {
        $batchSize = max(1, $batchSize);
        $now = $this->clock->now();

        return (int) $this->connection->executeStatement(
            \sprintf(
                'DELETE FROM webhook_stream WHERE NOT EXISTS (SELECT 1 FROM webhook_delivery d WHERE d.partition_key = webhook_stream.partition_key) AND (locked_by IS NULL OR lock_expires_at <= :now) AND created_at < :cutoff LIMIT %d',
                $batchSize
            ),
            [
                'now' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'cutoff' => $now->modify(\sprintf('-%d seconds', self::ORPHAN_GRACE_SECONDS))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }
}
