<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Loader;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * Reads runtime metadata for app MCP tools from the database: declared required
 * privileges and the toolset group each tool belongs to.
 *
 * @internal
 */
#[Package('framework')]
class AppMcpPrivilegeProvider
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
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
            $this->logger->error('Failed to load app MCP tool privileges', [
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

    /**
     * Runtime app tools carry no compile-time #[McpToolGroup]; without a group they would fall to
     * the catch-all "other" bucket. Grouping each app's tools under the owning app's technical name
     * gives them a real, enable-able toolset so clients without inline tool promotion can reach them.
     *
     * @return array<string, string> tool-name => group (the owning app's technical name)
     */
    public function getAppToolGroups(): array
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT CONCAT(a.name, \'-\', t.name) AS tool_name, a.name AS group_name
                 FROM app_mcp_tool t
                 INNER JOIN app a ON t.app_id = a.id AND a.active = 1',
            );
        } catch (\Throwable $e) {
            $this->logger->error('Failed to load app MCP tool groups', [
                'exception' => $e->getMessage(),
            ]);

            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['tool_name']] = (string) $row['group_name'];
        }

        return $map;
    }
}
