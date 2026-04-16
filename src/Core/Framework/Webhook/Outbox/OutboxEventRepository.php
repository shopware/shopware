<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Outbox;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\WebhookException;

/**
 * Persistence layer for outbox tables (webhook_event_log + webhook_delivery).
 * Pure data operations — no business decisions.
 *
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
                $this->insertEventLog($insert, $eventLogId);
            } catch (UniqueConstraintViolationException) {
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
        });
    }

    public function hasDeliveryRow(string $eventLogId): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => Uuid::fromHexToBytes($eventLogId)]
        );
    }

    /**
     * Claims a QUEUED delivery row for processing (first-attempt path).
     *
     * For retry path (row already RUNNING from a future receiver), the UPDATE
     * matches 0 rows and the SELECT returns current values without double-increment.
     */
    public function markRunning(string $eventLogId): ?OutboxEntry
    {
        $now = $this->clock->now();
        $id = Uuid::fromHexToBytes($eventLogId);
        $nowFormatted = $now->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        RetryableTransaction::retryable($this->connection, function () use ($id, $now, $nowFormatted): void {
            // Only transition event_log if not already RUNNING (idempotent for retry path
            // where a future receiver may have already claimed the row).
            $this->connection->executeStatement(
                'UPDATE webhook_event_log SET delivery_status = :status, timestamp = :ts WHERE id = :id AND delivery_status != :status',
                [
                    'status' => WebhookEventLogDefinition::STATUS_RUNNING,
                    'ts' => $now->getTimestamp(),
                    'id' => $id,
                ]
            );

            // Only increment execution_count for queued rows (first-attempt).
            // Retry rows are already RUNNING with incremented count from a future receiver.
            $this->connection->executeStatement(
                'UPDATE webhook_delivery SET delivery_status = :status, execution_count = execution_count + 1, last_attempt_at = :now, updated_at = :now WHERE webhook_event_log_id = :id AND delivery_status = :queued',
                [
                    'status' => WebhookEventLogDefinition::STATUS_RUNNING,
                    'now' => $nowFormatted,
                    'id' => $id,
                    'queued' => WebhookEventLogDefinition::STATUS_QUEUED,
                ]
            );
        });

        $row = $this->connection->fetchAssociative(
            'SELECT execution_count, id FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $id]
        );

        if ($row === false) {
            return null;
        }

        return new OutboxEntry(
            executionCount: (int) $row['execution_count'],
            sequence: (int) $row['id'],
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

    private function insertEventLog(OutboxInsert $insert, string $eventLogId): void
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

        if ($affected === 0) {
            throw WebhookException::webhookNotFound($insert->webhookId);
        }
    }
}
