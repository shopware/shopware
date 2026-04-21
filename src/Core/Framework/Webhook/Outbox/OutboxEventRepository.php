<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Outbox;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;

/**
 * @internal
 *
 * @codeCoverageIgnore Integration tested with \Shopware\Tests\Integration\Core\Framework\Webhook\Outbox\OutboxEventRepositoryTest
 */
#[Package('framework')]
class OutboxEventRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    public function ensureOutboxEntry(OutboxInsert $insert): void
    {
        RetryableTransaction::retryable($this->connection, function () use ($insert): void {
            $eventLogId = Uuid::fromHexToBytes($insert->webhookEventId);

            try {
                $inserted = $this->insertEventLog($insert, $eventLogId);
            } catch (UniqueConstraintViolationException) {
                return;
            }

            if (!$inserted) {
                return;
            }

            $this->connection->insert('webhook_delivery', [
                'webhook_event_log_id' => $eventLogId,
                'webhook_id' => Uuid::fromHexToBytes($insert->webhookId),
                'partition_key' => $insert->partitionKey,
                'delivery_status' => WebhookEventLogDefinition::STATUS_QUEUED,
                'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);

            $sequence = (int) $this->connection->lastInsertId();

            $this->connection->executeStatement(
                'UPDATE webhook_event_log SET sequence = :sequence WHERE id = :id',
                ['sequence' => $sequence, 'id' => $eventLogId]
            );

            $this->connection->executeStatement(
                'INSERT IGNORE INTO webhook_stream (id, partition_key, created_at) VALUES (:id, :pk, :now)',
                [
                    'id' => Uuid::randomBytes(),
                    'pk' => $insert->partitionKey,
                    'now' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        });
    }

    /**
     * Returns up to $budget deliveries for the given partition whose current status is in
     * $statuses and whose next_retry_at has passed (or is NULL). Ordered by webhook_delivery.id ASC.
     *
     * @param non-empty-list<WebhookEventLogDefinition::STATUS_QUEUED|WebhookEventLogDefinition::STATUS_PENDING_RETRY> $statuses
     *
     * @return list<OutboxEntry>
     */
    public function fetchDue(string $partitionKey, array $statuses, int $budget): array
    {
        $sql = <<<'SQL'
            SELECT d.id, d.webhook_event_log_id, d.execution_count, d.delivery_status,
                   el.serialized_webhook_message
            FROM webhook_delivery d
            JOIN webhook_event_log el ON el.id = d.webhook_event_log_id
            WHERE d.partition_key = :pk
              AND d.delivery_status IN (:statuses) AND (d.next_retry_at IS NULL OR d.next_retry_at <= :now)
            ORDER BY d.id ASC
            LIMIT :budget
            SQL;

        $rows = $this->connection->fetchAllAssociative(
            $sql,
            [
                'pk' => $partitionKey,
                'now' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'budget' => max(1, $budget),
                'statuses' => $statuses,
            ],
            [
                'budget' => Types::INTEGER,
                'statuses' => ArrayParameterType::STRING,
            ]
        );

        return array_map(
            static fn (array $row) => new OutboxEntry(
                webhookEventId: Uuid::fromBytesToHex($row['webhook_event_log_id']),
                sequence: (int) $row['id'],
                executionCount: (int) $row['execution_count'],
                deliveryStatus: (string) $row['delivery_status'],
                serializedWebhookMessage: (string) $row['serialized_webhook_message'],
            ),
            $rows
        );
    }

    public function hasDeliveryRow(string $eventLogId): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => Uuid::fromHexToBytes($eventLogId)]
        );
    }

    /**
     * Transitions the delivery row to RUNNING (idempotent on already-RUNNING).
     * Returns null if the delivery row does not exist.
     */
    public function markRunning(string $eventLogId): ?OutboxEntry
    {
        $now = $this->clock->now();
        $id = Uuid::fromHexToBytes($eventLogId);
        $nowFormatted = $now->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        RetryableTransaction::retryable($this->connection, function () use ($id, $now, $nowFormatted): void {
            $affected = (int) $this->connection->executeStatement(
                'UPDATE webhook_delivery SET delivery_status = :status, execution_count = execution_count + 1, last_attempt_at = :now, updated_at = :now WHERE webhook_event_log_id = :id AND delivery_status != :status',
                [
                    'status' => WebhookEventLogDefinition::STATUS_RUNNING,
                    'now' => $nowFormatted,
                    'id' => $id,
                ]
            );

            if ($affected > 0) {
                $this->connection->executeStatement(
                    'UPDATE webhook_event_log SET delivery_status = :status, timestamp = :ts WHERE id = :id',
                    [
                        'status' => WebhookEventLogDefinition::STATUS_RUNNING,
                        'ts' => $now->getTimestamp(),
                        'id' => $id,
                    ]
                );
            }
        });

        $row = $this->connection->fetchAssociative(
            'SELECT execution_count, id FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $id]
        );

        if ($row === false) {
            return null;
        }

        return new OutboxEntry(
            webhookEventId: $eventLogId,
            sequence: (int) $row['id'],
            executionCount: (int) $row['execution_count'],
            deliveryStatus: WebhookEventLogDefinition::STATUS_RUNNING,
        );
    }

    public function markSuccess(string $eventLogId, ?DeliveryResponse $response = null): void
    {
        RetryableTransaction::retryable($this->connection, function () use ($eventLogId, $response): void {
            $id = $this->updateEventLog($eventLogId, WebhookEventLogDefinition::STATUS_SUCCESS, $response);
            $this->deleteDelivery($id);
        });
    }

    /**
     * Schedules a retry at the given time. The caller owns delay computation;
     * the repository just persists the state.
     */
    public function markPendingRetry(string $eventLogId, \DateTimeImmutable $retryAt, ?DeliveryResponse $response = null): void
    {
        RetryableTransaction::retryable($this->connection, function () use ($eventLogId, $retryAt, $response): void {
            $id = $this->updateEventLog($eventLogId, WebhookEventLogDefinition::STATUS_PENDING_RETRY, $response);

            $this->connection->executeStatement(
                'UPDATE webhook_delivery SET delivery_status = :status, next_retry_at = :nextRetryAt, updated_at = :now WHERE webhook_event_log_id = :id',
                [
                    'status' => WebhookEventLogDefinition::STATUS_PENDING_RETRY,
                    'nextRetryAt' => $retryAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'now' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'id' => $id,
                ]
            );
        });
    }

    /**
     * Resets delivery to QUEUED so the next markRunning() can claim it.
     * Used while Messenger owns the retry lifecycle (feature flag OFF).
     */
    public function resetForRetry(string $eventLogId, ?DeliveryResponse $response = null): void
    {
        RetryableTransaction::retryable($this->connection, function () use ($eventLogId, $response): void {
            $id = $this->updateEventLog($eventLogId, WebhookEventLogDefinition::STATUS_QUEUED, $response);

            $this->connection->executeStatement(
                'UPDATE webhook_delivery SET delivery_status = :status, updated_at = :now WHERE webhook_event_log_id = :id',
                [
                    'status' => WebhookEventLogDefinition::STATUS_QUEUED,
                    'now' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'id' => $id,
                ]
            );
        });
    }

    public function markFailed(string $eventLogId, ?DeliveryResponse $response = null): void
    {
        RetryableTransaction::retryable($this->connection, function () use ($eventLogId, $response): void {
            $id = $this->updateEventLog($eventLogId, WebhookEventLogDefinition::STATUS_FAILED, $response);
            $this->deleteDelivery($id);
        });
    }

    private function updateEventLog(string $eventLogId, string $status, ?DeliveryResponse $response): string
    {
        $id = Uuid::fromHexToBytes($eventLogId);

        $data = $response !== null ? $response->toArray() : [];
        $data['delivery_status'] = $status;

        $this->connection->update('webhook_event_log', $data, ['id' => $id]);

        return $id;
    }

    private function deleteDelivery(string $binaryEventLogId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $binaryEventLogId]
        );
    }

    private function insertEventLog(OutboxInsert $insert, string $eventLogId): bool
    {
        $createdAt = $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $webhookId = Uuid::fromHexToBytes($insert->webhookId);

        $affected = $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO webhook_event_log (
                    id, app_name, delivery_status, webhook_name, event_name,
                    app_version, url, only_live_version, created_at,
                    serialized_webhook_message
                )
                SELECT
                    :id, a.name, :status, w.name, w.event_name,
                    a.version, w.url, w.only_live_version, :createdAt,
                    :serializedMessage
                FROM webhook w
                LEFT JOIN app a ON (a.id = w.app_id)
                WHERE w.id = :webhookId
            SQL,
            [
                'id' => $eventLogId,
                'status' => WebhookEventLogDefinition::STATUS_QUEUED,
                'createdAt' => $createdAt,
                'serializedMessage' => $insert->serializedMessage,
                'webhookId' => $webhookId,
            ]
        );

        return $affected > 0;
    }
}
