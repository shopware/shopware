<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service\Outbox;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Service\Outbox\Dto\StreamContext;

#[Package('framework')]
class StreamLockService
{
    private int $lockTtl;

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        OutboxConfig $config
    ) {
        // Calculate lock TTL based on worst-case execution time for a full batch
        // (connect + request) * batch items + small buffer
        $singleRequestTime = $config->connectTimeout + $config->requestTimeout;
        $this->lockTtl = ($singleRequestTime * $config->batchSize) + 5;
    }

    public function claimNext(string $workerId): ?StreamContext
    {
        $this->connection->beginTransaction();

        try {
            // Find an unlocked stream that has pending work (queued or valid retry)
            $stream = $this->connection->fetchAssociative(
                <<<'SQL'
                    SELECT ws.partition_key
                    FROM webhook_stream ws
                    WHERE (ws.locked_by IS NULL OR ws.lock_expires_at <= NOW(3))
                      AND EXISTS (
                          SELECT 1 FROM webhook_event_log wel
                          WHERE wel.partition_key = ws.partition_key
                            AND (
                                wel.delivery_status = :queued
                                OR (wel.delivery_status = :pending AND (wel.next_retry_at IS NULL OR wel.next_retry_at <= NOW(3)))
                            )
                      )
                    LIMIT 1
                    FOR UPDATE SKIP LOCKED
                SQL,
                [
                    'queued' => WebhookEventLogDefinition::STATUS_QUEUED,
                    'pending' => WebhookEventLogDefinition::STATUS_PENDING_RETRY,
                ]
            );

            if ($stream === false) {
                $this->connection->commit();

                return null;
            }

            $this->connection->executeStatement(
                'UPDATE webhook_stream SET locked_by = :workerId, lock_expires_at = DATE_ADD(NOW(3), INTERVAL :ttl SECOND) WHERE partition_key = :key',
                [
                    'workerId' => $workerId,
                    'ttl' => $this->lockTtl,
                    'key' => $stream['partition_key'],
                ],
                [
                    'key' => ParameterType::BINARY,
                ]
            );

            $this->connection->commit();

            return new StreamContext($stream['partition_key'], $workerId);
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function heartbeat(StreamContext $stream): bool
    {
        $count = $this->connection->executeStatement(
            <<<'SQL'
                UPDATE webhook_stream
                SET lock_expires_at = DATE_ADD(NOW(3), INTERVAL :ttl SECOND)
                WHERE partition_key = :key
                  AND locked_by = :workerId
            SQL,
            [
                'ttl' => $this->lockTtl,
                'key' => $stream->partitionKey,
                'workerId' => $stream->workerId,
            ],
            [
                'key' => ParameterType::BINARY,
            ]
        );

        return $count > 0;
    }

    public function release(StreamContext $stream): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                UPDATE webhook_stream
                SET locked_by = NULL,
                    lock_expires_at = NULL
                WHERE partition_key = :key AND locked_by = :workerId
            SQL,
            [
                'key' => $stream->partitionKey,
                'workerId' => $stream->workerId,
            ],
            [
                'key' => ParameterType::BINARY,
            ]
        );
    }
}
