<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class Migration1777535833McpToolResultCache extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1777535833;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `mcp_tool_result_cache` (
                `id`         BINARY(16)     NOT NULL,
                `session_id` VARCHAR(255)   NOT NULL,
                `mime_type`  VARCHAR(127)   NOT NULL DEFAULT \'application/json\',
                `content`    LONGTEXT       NOT NULL,
                `created_at` DATETIME(3)    NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_mcp_tool_result_session` (`session_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
