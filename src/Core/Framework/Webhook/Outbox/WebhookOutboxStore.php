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
use Shopware\Tests\Integration\Core\Framework\Webhook\Outbox\WebhookOutboxStoreTest;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see WebhookOutboxStoreTest
 */
#[Package('framework')]
class WebhookOutboxStore
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    public function recordOutboxEntry(OutboxInsert $insert): ?OutboxEntry
    {
        return $this->recordOutboxEntryWithStatus($insert, WebhookEventLogDefinition::STATUS_QUEUED);
    }

    /**
     * Writes an inline admin-worker delivery directly as RUNNING before the HTTP call starts.
     *
     * @deprecated tag:v6.8.0 - Only used for the inline admin-worker delivery path that existed before WEBHOOKS_REWORK.
     * To be removed when the admin_worker for webhooks becomes a transport concern.
     */
    public function recordInflightOutboxEntry(OutboxInsert $insert): ?OutboxEntry
    {
        return $this->recordOutboxEntryWithStatus($insert, WebhookEventLogDefinition::STATUS_RUNNING);
    }

    /**
     * Creates the missing webhook_delivery and webhook_stream rows for a legacy
     * webhook_event_log that is still in its initial QUEUED state. No-op for any other
     * status (RUNNING / PENDING_RETRY / SUCCESS / FAILED) or if a delivery row already
     * exists.
     *
     * @deprecated tag:v6.8.0 — rollout-compat for `async` envelopes that were
     * serialized before WEBHOOKS_REWORK shipped. Remove alongside the flag-OFF path in WebhookEventMessageHandler.
     */
    public function backfillDelivery(OutboxInsert $insert): ?OutboxEntry
    {
        return RetryableTransaction::retryable($this->connection, function () use ($insert): ?OutboxEntry {
            $eventLogId = Uuid::fromHexToBytes($insert->webhookEventId);

            if ($this->hasDeliveryRow($insert->webhookEventId)) {
                return null;
            }

            $eventLogStatus = $this->connection->fetchOne(
                'SELECT delivery_status FROM webhook_event_log WHERE id = :id FOR UPDATE',
                ['id' => $eventLogId]
            );

            if ($eventLogStatus !== WebhookEventLogDefinition::STATUS_QUEUED) {
                return null;
            }

            try {
                $entry = $this->insertDeliveryAndStream($insert, $eventLogId, WebhookEventLogDefinition::STATUS_QUEUED);
            } catch (UniqueConstraintViolationException) {
                // Two workers racing the same backfill — first commit wins, drop the duplicate.
                return null;
            }

            // Backfilled rows have no dispatch-order sequence — null signals.
            $this->connection->executeStatement(
                'UPDATE webhook_event_log SET sequence = NULL WHERE id = :id',
                ['id' => $eventLogId]
            );

            return $entry;
        });
    }

    /**
     * Returns up to $budget deliveries for the given partition whose current status is in
     * $statuses and whose next_retry_at has passed (or is NULL). Ordered by webhook_delivery.id ASC.
     *
     * Ordering contract:
     *   - First-attempt deliveries (QUEUED rows; `next_retry_at` is always NULL for QUEUED
     *     because markRunning never sets it) are yielded strictly in sequence order.
     *   - Once a row is in retry (PENDING_RETRY with a future `next_retry_at`), it is skipped
     *     until due. A later QUEUED row therefore can be yielded ahead of an earlier pending
     *     retry. This is deliberate — retry-side ordering is best-effort to avoid
     *     head-of-line blocking when a single endpoint is slow. The consumer contract
     *     (`X-Shopware-Sequence`) lets receivers reconcile on their side.
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
              AND el.delivery_status NOT IN (:successStatus, :failedStatus)
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
                'successStatus' => WebhookEventLogDefinition::STATUS_SUCCESS,
                'failedStatus' => WebhookEventLogDefinition::STATUS_FAILED,
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
     * Transitions the delivery row to RUNNING and returns the updated entry. Returns
     * null when the transition did not happen — either the row doesn't exist, or it
     * was already RUNNING (another caller owns the delivery on this attempt). Callers
     * that get null must not deliver: the owner of the transition handles it.
     */
    public function markRunning(string $eventLogId): ?OutboxEntry
    {
        $now = $this->clock->now();
        $id = Uuid::fromHexToBytes($eventLogId);
        $nowFormatted = $now->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $affected = RetryableTransaction::retryable($this->connection, function () use ($id, $now, $nowFormatted): int {
            // EXISTS guards blue/green deployment drift: a trunk runner can flip event_log to FAILED without touching webhook_delivery.
            $affected = (int) $this->connection->executeStatement(
                'UPDATE webhook_delivery
                 SET delivery_status = :runningStatus,
                     execution_count = execution_count + 1,
                     next_retry_at = NULL,
                     last_attempt_at = :now,
                     updated_at = :now
                 WHERE webhook_event_log_id = :id
	                   AND delivery_status IN (:queuedStatus, :pendingRetryStatus)
	                   AND EXISTS (
	                       SELECT 1
	                       FROM webhook_event_log el
	                       WHERE el.id = webhook_delivery.webhook_event_log_id
	                         AND el.delivery_status NOT IN (:successStatus, :failedStatus)
	                   )',
                [
                    'runningStatus' => WebhookEventLogDefinition::STATUS_RUNNING,
                    'queuedStatus' => WebhookEventLogDefinition::STATUS_QUEUED,
                    'pendingRetryStatus' => WebhookEventLogDefinition::STATUS_PENDING_RETRY,
                    'successStatus' => WebhookEventLogDefinition::STATUS_SUCCESS,
                    'failedStatus' => WebhookEventLogDefinition::STATUS_FAILED,
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

            return $affected;
        });

        if ($affected === 0) {
            return null;
        }

        // Row was just updated inside the transaction; it must still exist here.
        $row = $this->connection->fetchAssociative(
            'SELECT execution_count, id FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $id]
        );
        \assert($row !== false);

        return new OutboxEntry(
            webhookEventId: $eventLogId,
            sequence: (int) $row['id'],
            executionCount: (int) $row['execution_count'],
            deliveryStatus: WebhookEventLogDefinition::STATUS_RUNNING,
        );
    }

    public function markSuccess(OutboxEntry $entry, ?DeliveryResponse $response): bool
    {
        return RetryableTransaction::retryable($this->connection, function () use ($entry, $response): bool {
            if (!$this->ownsRunningAttempt($entry)) {
                return false;
            }

            $this->updateEventLog($entry->webhookEventId, WebhookEventLogDefinition::STATUS_SUCCESS, $response);
            $this->deleteDelivery($entry->webhookEventId);

            return true;
        });
    }

    /**
     * Schedules a retry at the given time. The caller owns delay computation;
     * the repository just persists the state.
     */
    public function markPendingRetry(OutboxEntry $entry, \DateTimeImmutable $retryAt, ?DeliveryResponse $response): bool
    {
        return RetryableTransaction::retryable($this->connection, function () use ($entry, $retryAt, $response): bool {
            if (!$this->ownsRunningAttempt($entry)) {
                return false;
            }

            $this->updateEventLog($entry->webhookEventId, WebhookEventLogDefinition::STATUS_PENDING_RETRY, $response);

            $this->connection->executeStatement(
                'UPDATE webhook_delivery SET delivery_status = :status, next_retry_at = :nextRetryAt, updated_at = :now WHERE webhook_event_log_id = :id',
                [
                    'status' => WebhookEventLogDefinition::STATUS_PENDING_RETRY,
                    'nextRetryAt' => $retryAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'now' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'id' => Uuid::fromHexToBytes($entry->webhookEventId),
                ]
            );

            return true;
        });
    }

    /**
     * Resets delivery to QUEUED so the next markRunning() can claim it.
     * Used while Messenger owns the retry lifecycle (feature flag OFF).
     *
     * @deprecated tag:v6.8.0 - Only used for the Messenger-owned retry lifecycle that existed before WEBHOOKS_REWORK. Remove alongside the flag-OFF path in WebhookEventMessageHandler.
     *
     * @phpstan-ignore shopware.deprecatedMethod (called on the flag-OFF retry path; deprecation notice would pollute logs)
     */
    public function resetForRetry(OutboxEntry $entry, ?DeliveryResponse $response): bool
    {
        return RetryableTransaction::retryable($this->connection, function () use ($entry, $response): bool {
            if (!$this->ownsRunningAttempt($entry)) {
                return false;
            }

            $this->updateEventLog($entry->webhookEventId, WebhookEventLogDefinition::STATUS_QUEUED, $response);

            $this->connection->executeStatement(
                'UPDATE webhook_delivery SET delivery_status = :status, updated_at = :now WHERE webhook_event_log_id = :id',
                [
                    'status' => WebhookEventLogDefinition::STATUS_QUEUED,
                    'now' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'id' => Uuid::fromHexToBytes($entry->webhookEventId),
                ]
            );

            return true;
        });
    }

    public function markFailed(OutboxEntry $entry, ?DeliveryResponse $response): bool
    {
        return RetryableTransaction::retryable($this->connection, function () use ($entry, $response): bool {
            if (!$this->ownsRunningAttempt($entry)) {
                return false;
            }

            $this->updateEventLog($entry->webhookEventId, WebhookEventLogDefinition::STATUS_FAILED, $response);
            $this->deleteDelivery($entry->webhookEventId);

            return true;
        });
    }

    /**
     * Called when the retry budget is exhausted on a pre-rework envelope (no partition key).
     * Always marks the event failed and deletes the delivery row — pre-rework messages have
     * no other worker that could still be handling them.
     *
     * @deprecated tag:v6.8.0 — pre-rework envelopes are drained once WEBHOOKS_REWORK is
     * permanent. Remove with the flag.
     */
    public function markLegacyFailedAfterRetryExhausted(string $eventLogId): bool
    {
        return RetryableTransaction::retryable(
            $this->connection,
            fn (): bool => $this->markFailedAfterRetryExhausted($eventLogId)
        );
    }

    /**
     * Called when the retry budget is exhausted on a rework-shape envelope during a rolling
     * deploy. Marks the event failed only if no other worker is currently delivering or has
     * a retry scheduled — otherwise leaves the row untouched so the other worker can finish.
     *
     * @deprecated tag:v6.8.0 — only used during the rollout window. Once WEBHOOKS_REWORK is
     * permanent, the subscriber early-returns and this method is unreachable. Remove with the flag.
     */
    public function markFailedAfterRetryExhaustedIfIdle(string $eventLogId): bool
    {
        return RetryableTransaction::retryable($this->connection, function () use ($eventLogId): bool {
            $id = Uuid::fromHexToBytes($eventLogId);

            $delivery = $this->connection->fetchAssociative(
                'SELECT delivery_status, next_retry_at FROM webhook_delivery WHERE webhook_event_log_id = :id FOR UPDATE',
                ['id' => $id]
            );

            if ($delivery !== false) {
                $deliveryStatus = (string) $delivery['delivery_status'];
                $nextRetryAt = $delivery['next_retry_at'];

                // Another worker is mid-flight on this delivery. Leave it alone.
                // @deprecated tag:v6.8.0 — remove with the flag.
                if ($deliveryStatus === WebhookEventLogDefinition::STATUS_RUNNING) {
                    return false;
                }

                // Another worker has already scheduled the next retry. Don't cancel it.
                // @deprecated tag:v6.8.0 — remove with the flag.
                if ($deliveryStatus === WebhookEventLogDefinition::STATUS_PENDING_RETRY
                    && $nextRetryAt !== null
                    && $nextRetryAt > $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT)
                ) {
                    return false;
                }
            }

            return $this->markFailedAfterRetryExhausted($eventLogId);
        });
    }

    public function markUndeliverableFetchedEntryFailed(OutboxEntry $entry): bool
    {
        return RetryableTransaction::retryable($this->connection, function () use ($entry): bool {
            $ownsFetchedEntry = (bool) $this->connection->fetchOne(
                'SELECT 1
                 FROM webhook_delivery
                 WHERE webhook_event_log_id = :id
                   AND id = :sequence
                   AND execution_count = :executionCount
                   AND delivery_status IN (:queuedStatus, :pendingRetryStatus)
                 FOR UPDATE',
                [
                    'id' => Uuid::fromHexToBytes($entry->webhookEventId),
                    'sequence' => $entry->sequence,
                    'executionCount' => $entry->executionCount,
                    'queuedStatus' => WebhookEventLogDefinition::STATUS_QUEUED,
                    'pendingRetryStatus' => WebhookEventLogDefinition::STATUS_PENDING_RETRY,
                ],
                [
                    'sequence' => Types::INTEGER,
                    'executionCount' => Types::INTEGER,
                ]
            );

            if (!$ownsFetchedEntry) {
                return false;
            }

            $markedFailed = $this->updateEventLog($entry->webhookEventId, WebhookEventLogDefinition::STATUS_FAILED, null);
            $this->deleteDelivery($entry->webhookEventId);

            return $markedFailed;
        });
    }

    /**
     * Resets RUNNING rows in the partition with `last_attempt_at` older than
     * `$staleAfterSeconds` back to PENDING_RETRY. Return value is the multi-table affected
     * count; assertions should check row state, not the count.
     */
    public function resetRunningForPartition(string $partitionKey, int $staleAfterSeconds): int
    {
        $now = $this->clock->now();
        $nowFormatted = $now->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $cutoff = $now->modify(\sprintf('-%d seconds', $staleAfterSeconds))->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        // Multi-table UPDATE acquires locks on both webhook_delivery and webhook_event_log;
        // the terminal writers (markSuccess / markFailed / markPendingRetry) lock the same
        // pair via two separate statements. A deadlock between the two paths is plausible
        // under InnoDB depending on the optimizer plan. Wrap in RetryableTransaction so
        // InnoDB aborts are retried in-place instead of bubbling into the receiver's
        // consecutive-deadlock fuse.
        return RetryableTransaction::retryable(
            $this->connection,
            fn (): int => (int) $this->connection->executeStatement(
                'UPDATE webhook_delivery d
                 JOIN webhook_event_log el ON el.id = d.webhook_event_log_id
                 SET d.delivery_status = :new,
                     d.next_retry_at   = :now,
                     d.updated_at      = :now,
                     el.delivery_status = :new,
                     el.timestamp       = :ts
	                 WHERE d.partition_key = :pk
	                   AND d.delivery_status = :old
	                   AND d.last_attempt_at <= :cutoff
	                   AND el.delivery_status NOT IN (:successStatus, :failedStatus)',
                [
                    'old' => WebhookEventLogDefinition::STATUS_RUNNING,
                    'new' => WebhookEventLogDefinition::STATUS_PENDING_RETRY,
                    'now' => $nowFormatted,
                    'ts' => $now->getTimestamp(),
                    'pk' => $partitionKey,
                    'cutoff' => $cutoff,
                    'successStatus' => WebhookEventLogDefinition::STATUS_SUCCESS,
                    'failedStatus' => WebhookEventLogDefinition::STATUS_FAILED,
                ]
            )
        );
    }

    /**
     * @param WebhookEventLogDefinition::STATUS_QUEUED|WebhookEventLogDefinition::STATUS_RUNNING $initialStatus
     */
    private function recordOutboxEntryWithStatus(OutboxInsert $insert, string $initialStatus): ?OutboxEntry
    {
        // an old message before the rework, would have only an event_log record. We need to handle that gracefully.
        return RetryableTransaction::retryable($this->connection, function () use ($insert, $initialStatus): ?OutboxEntry {
            $eventLogId = Uuid::fromHexToBytes($insert->webhookEventId);

            try {
                $inserted = $this->insertEventLog($insert, $eventLogId, $initialStatus);
            } catch (UniqueConstraintViolationException) {
                return null;
            }

            if (!$inserted) {
                return null;
            }

            return $this->insertDeliveryAndStream($insert, $eventLogId, $initialStatus);
        });
    }

    private function insertDeliveryAndStream(OutboxInsert $insert, string $eventLogId, string $initialStatus): OutboxEntry
    {
        $now = $this->clock->now();
        $nowFormatted = $now->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $isRunning = $initialStatus === WebhookEventLogDefinition::STATUS_RUNNING;
        $executionCount = $isRunning ? 1 : 0;

        $webhookId = $this->connection->fetchOne(
            'SELECT id FROM webhook WHERE id = :webhookId',
            ['webhookId' => Uuid::fromHexToBytes($insert->webhookId)]
        );

        $this->connection->insert('webhook_delivery', [
            'webhook_event_log_id' => $eventLogId,
            'webhook_id' => $webhookId === false ? null : $webhookId,
            'partition_key' => $insert->partitionKey,
            'delivery_status' => $initialStatus,
            'execution_count' => $executionCount,
            'last_attempt_at' => $isRunning ? $nowFormatted : null,
            'created_at' => $nowFormatted,
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
                'now' => $nowFormatted,
            ]
        );

        return new OutboxEntry(
            webhookEventId: $insert->webhookEventId,
            sequence: $sequence,
            executionCount: $executionCount,
            deliveryStatus: $initialStatus,
        );
    }

    /**
     * CAS-guarded event-log update: never rolls back a terminal status. A later reject
     * or retry write that races a concurrent markSuccess / markFailed must not overwrite
     * the winner's outcome.
     */
    private function updateEventLog(string $eventLogId, string $status, ?DeliveryResponse $response): bool
    {
        $id = Uuid::fromHexToBytes($eventLogId);

        if ($response === null) {
            $affected = (int) $this->connection->executeStatement(
                'UPDATE webhook_event_log
                 SET delivery_status = :status
                 WHERE id = :id
                   AND delivery_status NOT IN (:successStatus, :failedStatus)',
                [
                    'status' => $status,
                    'id' => $id,
                    'successStatus' => WebhookEventLogDefinition::STATUS_SUCCESS,
                    'failedStatus' => WebhookEventLogDefinition::STATUS_FAILED,
                ]
            );

            return $affected > 0;
        }

        $affected = (int) $this->connection->executeStatement(
            'UPDATE webhook_event_log
             SET delivery_status = :status,
                 request_content = :requestContent,
                 processing_time = :processingTime,
                 response_content = :responseContent,
                 response_status_code = :responseStatusCode,
                 response_reason_phrase = :responseReasonPhrase
             WHERE id = :id
               AND delivery_status NOT IN (:successStatus, :failedStatus)',
            [
                'status' => $status,
                'requestContent' => $response->requestContent,
                'processingTime' => $response->processingTimeSeconds,
                'responseContent' => $response->responseContent,
                'responseStatusCode' => $response->responseStatusCode,
                'responseReasonPhrase' => $response->responseReasonPhrase,
                'id' => $id,
                'successStatus' => WebhookEventLogDefinition::STATUS_SUCCESS,
                'failedStatus' => WebhookEventLogDefinition::STATUS_FAILED,
            ]
        );

        return $affected > 0;
    }

    /**
     * True only if the row is still RUNNING with the same (sequence, execution_count)
     * the caller got from markRunning(). A stuck worker whose lease was stolen and
     * re-claimed under a higher execution_count will get false and back off here.
     */
    private function ownsRunningAttempt(OutboxEntry $entry): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1
             FROM webhook_delivery
             WHERE webhook_event_log_id = :id
               AND id = :sequence
               AND execution_count = :executionCount
               AND delivery_status = :status
             FOR UPDATE',
            [
                'id' => Uuid::fromHexToBytes($entry->webhookEventId),
                'sequence' => $entry->sequence,
                'executionCount' => $entry->executionCount,
                'status' => WebhookEventLogDefinition::STATUS_RUNNING,
            ],
            [
                'sequence' => Types::INTEGER,
                'executionCount' => Types::INTEGER,
            ]
        );
    }

    private function deleteDelivery(string $eventLogId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => Uuid::fromHexToBytes($eventLogId)]
        );
    }

    private function markFailedAfterRetryExhausted(string $eventLogId): bool
    {
        $markedFailed = $this->updateEventLog($eventLogId, WebhookEventLogDefinition::STATUS_FAILED, null);
        $this->deleteDelivery($eventLogId);

        return $markedFailed;
    }

    private function insertEventLog(OutboxInsert $insert, string $eventLogId, string $status): bool
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
                'status' => $status,
                'createdAt' => $createdAt,
                'serializedMessage' => $insert->serializedMessage,
                'webhookId' => $webhookId,
            ]
        );

        return $affected > 0;
    }
}
