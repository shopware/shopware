<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\A2A;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Persists A2A message responses (for `messageId`-based idempotency) and
 * task state (for `tasks/get` and `tasks/cancel`). Backed by
 * `ucp_a2a_task`. Rows live 24h; expired rows are purged opportunistically.
 *
 * @internal
 */
#[Package('framework')]
class A2ATaskStore
{
    public const RETENTION_HOURS = 24;
    public const CLAIM_FRESH = 'fresh';
    public const CLAIM_REPLAY = 'replay';
    public const CLAIM_IN_FLIGHT = 'in_flight';

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMessageResponse(string $salesChannelId, string $messageId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT state, message_response FROM ucp_a2a_task
             WHERE sales_channel_id = ? AND message_id = ? AND expires_at > NOW(3) LIMIT 1',
            [Uuid::fromHexToBytes($salesChannelId), $messageId]
        );
        if (!\is_array($row) || $row['state'] !== 'completed' || !\is_string($row['message_response'])) {
            return null;
        }

        $decoded = json_decode($row['message_response'], true);

        return \is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array{status: string, response: array<string, mixed>|null}
     */
    public function claimMessage(string $salesChannelId, string $messageId, string $contextId): array
    {
        $now = new \DateTimeImmutable();
        try {
            $this->connection->insert('ucp_a2a_task', [
                'id' => Uuid::randomBytes(),
                'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
                'message_id' => $messageId,
                'task_id' => null,
                'context_id' => $contextId,
                'state' => 'pending',
                'message_response' => '{}',
                'created_at' => $now->format('Y-m-d H:i:s.v'),
                'expires_at' => $now->modify('+' . self::RETENTION_HOURS . ' hours')->format('Y-m-d H:i:s.v'),
            ]);

            return ['status' => self::CLAIM_FRESH, 'response' => null];
        } catch (UniqueConstraintViolationException) {
            $row = $this->connection->fetchAssociative(
                'SELECT state, message_response FROM ucp_a2a_task
                 WHERE sales_channel_id = ? AND message_id = ? AND expires_at > NOW(3) LIMIT 1',
                [Uuid::fromHexToBytes($salesChannelId), $messageId]
            );
            if (!\is_array($row)) {
                return ['status' => self::CLAIM_IN_FLIGHT, 'response' => null];
            }
            if ($row['state'] !== 'completed') {
                return ['status' => self::CLAIM_IN_FLIGHT, 'response' => null];
            }

            $decoded = json_decode((string) $row['message_response'], true);

            return ['status' => self::CLAIM_REPLAY, 'response' => \is_array($decoded) ? $decoded : null];
        }
    }

    /**
     * @param array<string, mixed> $response
     */
    public function recordMessageResponse(string $salesChannelId, string $messageId, array $response): void
    {
        $now = new \DateTimeImmutable();
        $this->connection->update('ucp_a2a_task', [
            'state' => 'completed',
            'message_response' => json_encode($response, \JSON_THROW_ON_ERROR),
            'expires_at' => $now->modify('+' . self::RETENTION_HOURS . ' hours')->format('Y-m-d H:i:s.v'),
        ], [
            'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
            'message_id' => $messageId,
        ]);
    }

    public function abortMessage(string $salesChannelId, string $messageId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM ucp_a2a_task WHERE sales_channel_id = ? AND message_id = ? AND state = ?',
            [Uuid::fromHexToBytes($salesChannelId), $messageId, 'pending']
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getTask(string $salesChannelId, string $taskId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT task_id, context_id, state, message_response FROM ucp_a2a_task
             WHERE sales_channel_id = ? AND task_id = ? AND expires_at > NOW(3) LIMIT 1',
            [Uuid::fromHexToBytes($salesChannelId), $taskId]
        );
        if (!\is_array($row)) {
            return null;
        }

        return [
            'id' => $row['task_id'],
            'contextId' => $row['context_id'],
            'state' => $row['state'],
            'status' => [
                'state' => $row['state'],
            ],
            'history' => [
                json_decode((string) $row['message_response'], true) ?: null,
            ],
        ];
    }

    public function cancelTask(string $salesChannelId, string $taskId): void
    {
        $this->connection->executeStatement(
            'UPDATE ucp_a2a_task SET state = ? WHERE sales_channel_id = ? AND task_id = ?',
            ['cancelled', Uuid::fromHexToBytes($salesChannelId), $taskId]
        );
    }
}
