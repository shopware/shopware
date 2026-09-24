<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1790256569AddMcpToolResultCacheCreatedAtIndex extends MigrationStep
{
    private const INDEX_NAME = 'idx.mcp_tool_result_cache.created_at';

    public function getCreationTimestamp(): int
    {
        return 1790256569;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::tableExists($connection, 'mcp_tool_result_cache')) {
            return;
        }

        if (TableHelper::indexExists($connection, 'mcp_tool_result_cache', self::INDEX_NAME)) {
            return;
        }

        $connection->executeStatement(
            'CREATE INDEX `' . self::INDEX_NAME . '` ON `mcp_tool_result_cache` (`created_at`)'
        );
    }
}
