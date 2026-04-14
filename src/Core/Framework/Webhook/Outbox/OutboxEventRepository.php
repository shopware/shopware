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

    public function ensureOutboxEntry(OutboxEntry $entry): void
    {
        RetryableTransaction::retryable($this->connection, function () use ($entry): void {
            $eventLogId = Uuid::fromHexToBytes($entry->webhookEventId);

            try {
                $this->insertEventLog($entry, $eventLogId);
            } catch (UniqueConstraintViolationException) {
                return;
            }

            $this->connection->insert('webhook_delivery', [
                'webhook_event_log_id' => $eventLogId,
                'webhook_id' => Uuid::fromHexToBytes($entry->webhookId),
                'partition_key' => $entry->partitionKey,
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

    public function markRunning(string $eventLogId): void
    {
        $now = $this->clock->now();

        RetryableTransaction::retryable($this->connection, function () use ($eventLogId, $now): void {
            $id = Uuid::fromHexToBytes($eventLogId);

            $this->connection->update('webhook_event_log', [
                'delivery_status' => WebhookEventLogDefinition::STATUS_RUNNING,
                'timestamp' => $now->getTimestamp(),
            ], ['id' => $id]);

            $this->connection->executeStatement(
                'UPDATE webhook_delivery SET delivery_status = :status, execution_count = execution_count + 1, last_attempt_at = :now WHERE webhook_event_log_id = :id',
                [
                    'status' => WebhookEventLogDefinition::STATUS_RUNNING,
                    'now' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'id' => $id,
                ]
            );
        });
    }

    public function markSuccess(string $eventLogId, ?DeliveryResponse $response = null): void
    {
        RetryableTransaction::retryable($this->connection, function () use ($eventLogId, $response): void {
            $id = $this->updateEventLog($eventLogId, WebhookEventLogDefinition::STATUS_SUCCESS, $response);
            $this->deleteDelivery($id);
        });
    }

    /**
     * Resets delivery to QUEUED so the next markRunning() can claim it.
     * Used by the handler and retry subscriber while Messenger owns the retry lifecycle.
     * Will be replaced by outbox-owned retry scheduling (next_retry_at) in a follow-up.
     */
    public function resetForRetry(string $eventLogId, ?DeliveryResponse $response = null): void
    {
        RetryableTransaction::retryable($this->connection, function () use ($eventLogId, $response): void {
            $id = $this->updateEventLog($eventLogId, WebhookEventLogDefinition::STATUS_QUEUED, $response);

            $this->connection->executeStatement(
                'UPDATE webhook_delivery SET delivery_status = :status WHERE webhook_event_log_id = :id',
                ['status' => WebhookEventLogDefinition::STATUS_QUEUED, 'id' => $id]
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

    /**
     * Updates the event log status and optional response data. Returns the binary ID.
     */
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

    private function insertEventLog(OutboxEntry $entry, string $eventLogId): void
    {
        $createdAt = $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $webhookId = Uuid::fromHexToBytes($entry->webhookId);

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
                'serializedMessage' => $entry->serializedMessage,
                'webhookId' => $webhookId,
            ]
        );

        if ($affected === 0) {
            throw WebhookException::webhookNotFound($entry->webhookId);
        }
    }
}
