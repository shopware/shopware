<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @experimental stableVersion:v6.8.0
 *
 * Persists large tool results in the DB. Each stored result is scoped to a session ID so it cannot
 * be read by other sessions. Rows are removed on DELETE of the session and by age, see
 * {@see \Shopware\Core\Framework\Mcp\ScheduledTask\McpToolResultCacheCleanupTaskHandler}.
 */
#[Package('framework')]
class ToolResultCacheStorage
{
    /**
     * Default of `shopware.mcp.tool_result_cache_ttl`: seconds a stored result is kept after `created_at`.
     */
    public const DEFAULT_TTL_SECONDS = 86400;

    /**
     * Every row holds more than 100 KB (`McpToolResponse::MAX_RESPONSE_SIZE`), so a batch stays around
     * 10 MB of row data, well below transaction size limits such as Group Replication's.
     */
    private const CLEANUP_BATCH_SIZE = 100;

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Stores content and returns the hex UUID that identifies it.
     */
    public function store(string $sessionId, string $content, string $mimeType = 'application/json'): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('mcp_tool_result_cache', [
            'id' => $id,
            'session_id' => $sessionId,
            'mime_type' => $mimeType,
            'content' => $content,
            'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return Uuid::fromBytesToHex($id);
    }

    /**
     * Returns the stored result for $id if it belongs to $sessionId, null otherwise.
     *
     * @return array{content: string, mimeType: string}|null
     */
    public function read(string $id, string $sessionId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT `content`, `mime_type` FROM `mcp_tool_result_cache` WHERE `id` = :id AND `session_id` = :sessionId',
            [
                'id' => Uuid::fromHexToBytes($id),
                'sessionId' => $sessionId,
            ],
        );

        if ($row === false) {
            return null;
        }

        return [
            'content' => (string) $row['content'],
            'mimeType' => (string) $row['mime_type'],
        ];
    }

    /**
     * Deletes all cached results for the given session — called on session end.
     */
    public function deleteForSession(string $sessionId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `mcp_tool_result_cache` WHERE `session_id` = :sessionId',
            ['sessionId' => $sessionId],
        );
    }

    /**
     * Deletes rows created at or before `$threshold`, in batches of CLEANUP_BATCH_SIZE.
     *
     * @return int Number of deleted rows
     */
    public function deleteOlderThan(\DateTimeInterface $threshold): int
    {
        $formattedThreshold = $threshold->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $deleted = 0;

        do {
            $result = (int) $this->connection->executeStatement(
                'DELETE FROM `mcp_tool_result_cache` WHERE `created_at` <= :threshold LIMIT :limit',
                [
                    'threshold' => $formattedThreshold,
                    'limit' => self::CLEANUP_BATCH_SIZE,
                ],
                [
                    'limit' => ParameterType::INTEGER,
                ],
            );
            $deleted += $result;
        } while ($result >= self::CLEANUP_BATCH_SIZE);

        return $deleted;
    }
}
