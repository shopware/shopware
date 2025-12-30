<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service\Outbox;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Service\Outbox\Dto\DeliveryOutcome;
use Shopware\Core\Framework\Webhook\WebhookException;
use Shopware\Core\Framework\Webhook\Service\Outbox\Dto\OutboxEntry;

#[Package('framework')]
class OutboxEventRepository
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly OutboxConfig $config
    ) {
    }

    public function hasPendingWork(): bool
    {
        $count = $this->connection->fetchOne(
            <<<'SQL'
                SELECT 1
                FROM webhook_event_log
                WHERE delivery_status IN (:statuses)
                  AND (next_retry_at IS NULL OR next_retry_at <= NOW(3))
                LIMIT 1
            SQL,
            ['statuses' => [WebhookEventLogDefinition::STATUS_QUEUED, WebhookEventLogDefinition::STATUS_PENDING_RETRY]],
            ['statuses' => ArrayParameterType::STRING]
        );

        return $count !== false;
    }

    public function getEarliestRetryTime(): ?\DateTimeImmutable
    {
        $timeString = $this->connection->fetchOne(
            <<<'SQL'
                SELECT MIN(next_retry_at)
                FROM webhook_event_log
                WHERE delivery_status = :pending
                  AND next_retry_at > NOW(3)
            SQL,
            ['pending' => WebhookEventLogDefinition::STATUS_PENDING_RETRY]
        );

        if ($timeString === false || $timeString === null) {
            return null;
        }

        return new \DateTimeImmutable($timeString);
    }

    /**
     * @return list<OutboxEntry>
     */
    public function fetchPendingRetries(string $partitionKey, int $limit): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT id, serialized_webhook_message, execution_count, sequence, next_retry_at
                FROM webhook_event_log
                WHERE partition_key = :key
                  AND delivery_status = :pending
                  AND (
                    (next_retry_at IS NULL OR next_retry_at <= NOW(3))
                    OR
                    (last_attempt_at <= DATE_SUB(NOW(3), INTERVAL :min_backoff SECOND))
                  )
                ORDER BY sequence ASC
                LIMIT :limit
            SQL,
            [
                'key' => $partitionKey,
                'pending' => WebhookEventLogDefinition::STATUS_PENDING_RETRY,
                'limit' => $limit,
                'min_backoff' => $this->config->inlineRetryMinBackoffSeconds,
            ],
            [
                'limit' => ParameterType::INTEGER,
                'min_backoff' => ParameterType::INTEGER,
                'key' => ParameterType::BINARY,
            ]
        );

        return $this->hydrateEntries($rows);
    }

    /**
     * @return list<OutboxEntry>
     */
    public function fetchQueued(string $partitionKey, int $limit): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT id, serialized_webhook_message, execution_count, sequence, next_retry_at
                FROM webhook_event_log
                WHERE partition_key = :key
                  AND delivery_status = :queued
                ORDER BY sequence ASC
                LIMIT :limit
            SQL,
            [
                'key' => $partitionKey,
                'queued' => WebhookEventLogDefinition::STATUS_QUEUED,
                'limit' => $limit,
            ],
            [
                'limit' => ParameterType::INTEGER,
                'key' => ParameterType::BINARY,
            ]
        );

        return $this->hydrateEntries($rows);
    }

    /**
     * @param list<string> $ids
     * @param list<string> $eventNames
     *
     * @return list<OutboxEntry>
     */
    public function fetchForFlush(array $ids, array $eventNames): array
    {
        if (empty($ids)) {
            return [];
        }

        $binaryIds = array_map(fn($id) => Uuid::fromHexToBytes($id), $ids);

        // Step 1: Find the max sequence from the requested IDs as the boundary
        $maxSequence = $this->connection->fetchOne(
            'SELECT MAX(sequence) FROM webhook_event_log WHERE id IN (:ids)',
            ['ids' => $binaryIds],
            ['ids' => ArrayParameterType::STRING]
        );

        if ($maxSequence === false) {
            return [];
        }

        $this->connection->beginTransaction();

        try {
            $rows = $this->connection->fetchAllAssociative(
                <<<'SQL'
                    SELECT id, serialized_webhook_message, execution_count, sequence, next_retry_at
                    FROM webhook_event_log
                    WHERE event_name IN (:event_names)
                      AND sequence <= :max_sequence
                      AND delivery_status IN (:statuses)
                      AND (last_attempt_at IS NULL OR last_attempt_at <= DATE_SUB(NOW(3), INTERVAL :min_backoff SECOND))
                    ORDER BY sequence ASC
                    FOR UPDATE SKIP LOCKED
                SQL,
                [
                    'event_names' => $eventNames,
                    'max_sequence' => $maxSequence,
                    'statuses' => [WebhookEventLogDefinition::STATUS_QUEUED, WebhookEventLogDefinition::STATUS_PENDING_RETRY],
                    'min_backoff' => $this->config->inlineRetryMinBackoffSeconds,
                ],
                [
                    'event_names' => ArrayParameterType::STRING,
                    'statuses' => ArrayParameterType::STRING,
                    'min_backoff' => ParameterType::INTEGER,
                ]
            );

            if (!empty($rows)) {
                $this->connection->executeStatement(
                    'UPDATE webhook_event_log SET delivery_status = :status, last_attempt_at = :now WHERE id IN (:ids)',
                    [
                        'status' => WebhookEventLogDefinition::STATUS_RUNNING,
                        'now' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                        'ids' => array_map(fn($e) => $e['id'], $rows),
                    ],
                    [
                        'ids' => ArrayParameterType::BINARY,
                    ]
                );
            }

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }

        return $this->hydrateEntries($rows);
    }

    public function markRunning(OutboxEntry $entry): void
    {
        $this->updateLog($entry->id, [
            'delivery_status' => WebhookEventLogDefinition::STATUS_RUNNING,
            'execution_count' => $entry->executionCount + 1,
            'last_attempt_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    public function markSuccess(OutboxEntry $entry, DeliveryOutcome $outcome): void
    {
        $this->updateLog($entry->id, [
            'delivery_status' => WebhookEventLogDefinition::STATUS_SUCCESS,
            'processing_time' => $outcome->processingTime,
            'request_content' => json_encode($outcome->requestData),
            'response_content' => json_encode($outcome->responseData),
            'response_status_code' => $outcome->responseStatusCode,
            'response_reason_phrase' => $outcome->responseReasonPhrase,
            'next_retry_at' => null,
        ]);
    }

    public function markPendingRetry(OutboxEntry $entry, DeliveryOutcome $outcome): void
    {
        $this->updateLog($entry->id, [
            'delivery_status' => WebhookEventLogDefinition::STATUS_PENDING_RETRY,
            'processing_time' => $outcome->processingTime,
            'request_content' => json_encode($outcome->requestData),
            'response_content' => json_encode($outcome->responseData),
            'response_status_code' => $outcome->responseStatusCode,
            'response_reason_phrase' => $outcome->responseReasonPhrase,
            'next_retry_at' => $outcome->nextRetryAt?->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    public function markFailed(OutboxEntry $entry, DeliveryOutcome $outcome): void
    {
        $this->updateLog($entry->id, [
            'delivery_status' => WebhookEventLogDefinition::STATUS_FAILED,
            'processing_time' => $outcome->processingTime,
            'request_content' => json_encode($outcome->requestData),
            'response_content' => json_encode($outcome->responseData),
            'response_status_code' => $outcome->responseStatusCode,
            'response_reason_phrase' => $outcome->responseReasonPhrase,
            'next_retry_at' => null,
        ]);
    }

    public function resetStaleEntries(): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                UPDATE webhook_event_log
                SET delivery_status = :pending,
                    next_retry_at = :now
                WHERE delivery_status = :running
                  AND last_attempt_at < DATE_SUB(NOW(3), INTERVAL 5 MINUTE)
            SQL,
            [
                'pending' => WebhookEventLogDefinition::STATUS_PENDING_RETRY,
                'running' => WebhookEventLogDefinition::STATUS_RUNNING,
                'now' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }

    /**
     * @return array{active: bool, error_count: int}|null
     */
    public function getWebhookInfo(string $webhookId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT active, error_count FROM webhook WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($webhookId)]
        );

        if ($row === false) {
            return null;
        }

        return [
            'active' => (bool) $row['active'],
            'error_count' => (int) $row['error_count'],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function updateLog(string $id, array $data): void
    {
        $this->connection->update('webhook_event_log', $data, ['id' => Uuid::fromHexToBytes($id)]);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<OutboxEntry>
     */
    private function hydrateEntries(array $rows): array
    {
        $entries = [];
        foreach ($rows as $row) {
            $payload = $row['serialized_webhook_message'] ?? null;

            if ($payload === null) {
                throw WebhookException::invalidSerializedMessage(Uuid::fromBytesToHex($row['id']), 'No serialized message found.');
            }

            $message = unserialize($payload, ['allowed_classes' => [WebhookEventMessage::class]]);

            if (!$message instanceof WebhookEventMessage) {
                throw WebhookException::invalidSerializedMessage(
                    Uuid::fromBytesToHex($row['id']),
                    sprintf('Expected %s, got %s.', WebhookEventMessage::class, get_debug_type($message))
                );
            }

            $entries[] = new OutboxEntry(
                Uuid::fromBytesToHex($row['id']),
                $message,
                (int) ($row['execution_count'] ?? 0),
                isset($row['next_retry_at']) ? new \DateTimeImmutable($row['next_retry_at']) : null
            );
        }

        return $entries;
    }
}
