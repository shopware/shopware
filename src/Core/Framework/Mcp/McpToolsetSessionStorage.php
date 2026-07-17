<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * Persists enabled MCP toolsets for the duration of one MCP session.
 * Rows are removed when the MCP session ends (DELETE /api/_mcp) and, for sessions that are
 * abandoned without a DELETE, by the periodic McpToolsetSessionCleanupTask.
 */
#[Package('framework')]
class McpToolsetSessionStorage
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    public function enable(string $sessionId, string $toolsetName): void
    {
        $this->connection->executeStatement(
            'INSERT IGNORE INTO `mcp_toolset_session` (`session_id`, `toolset_name`, `created_at`) VALUES (:sessionId, :toolsetName, :createdAt)',
            [
                'sessionId' => $sessionId,
                'toolsetName' => $toolsetName,
                'createdAt' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
        );
    }

    /**
     * @return list<string>
     */
    public function enabledToolsets(string $sessionId): array
    {
        return array_values(array_map(
            static fn (mixed $toolsetName): string => (string) $toolsetName,
            $this->connection->fetchFirstColumn(
                'SELECT `toolset_name` FROM `mcp_toolset_session` WHERE `session_id` = :sessionId ORDER BY `toolset_name` ASC',
                ['sessionId' => $sessionId],
            ),
        ));
    }

    public function deleteForSession(string $sessionId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `mcp_toolset_session` WHERE `session_id` = :sessionId',
            ['sessionId' => $sessionId],
        );
    }

    /**
     * @return list<string>
     */
    public function sessionIds(): array
    {
        return array_values(array_map(
            static fn (mixed $sessionId): string => (string) $sessionId,
            $this->connection->fetchFirstColumn('SELECT DISTINCT `session_id` FROM `mcp_toolset_session`'),
        ));
    }
}
