<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Persists large tool results in the DB for the duration of an MCP session.
 * Each stored result is scoped to a session ID so it cannot be read by other sessions.
 * Rows are removed when the MCP session ends (DELETE /api/_mcp).
 */
#[Package('framework')]
class ToolResultCacheStorage
{
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
}
