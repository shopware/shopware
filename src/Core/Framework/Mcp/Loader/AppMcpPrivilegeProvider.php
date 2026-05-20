<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Loader;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Reads declared required privileges for app MCP tools from the database.
 *
 * @internal
 */
#[Package('framework')]
class AppMcpPrivilegeProvider
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @return array<string, list<string>> tool-name => ['entity:operation', ...]
     */
    public function getAppToolPrivileges(): array
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT CONCAT(a.name, \'-\', t.name) AS tool_name, t.required_privileges
                 FROM app_mcp_tool t
                 INNER JOIN app a ON t.app_id = a.id AND a.active = 1
                 WHERE t.required_privileges IS NOT NULL',
            );
        } catch (\Throwable $e) {
            $this->logger?->error('Failed to load app MCP tool privileges', [
                'exception' => $e->getMessage(),
            ]);

            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $decoded = json_decode((string) $row['required_privileges'], true);
            if (\is_array($decoded)) {
                $map[(string) $row['tool_name']] = array_values($decoded);
            }
        }

        return $map;
    }
}
