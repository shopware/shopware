<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1772626151AppMcpTool extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1772626151;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `app_mcp_tool` (
                `id` BINARY(16) NOT NULL,
                `app_id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `url` VARCHAR(2048) NOT NULL,
                `input_schema` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.app_mcp_tool.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `uniq.app_mcp_tool.app_id_name` UNIQUE (`app_id`, `name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `app_mcp_tool_translation` (
                `app_mcp_tool_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `label` VARCHAR(255) NULL,
                `description` LONGTEXT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`app_mcp_tool_id`, `language_id`),
                CONSTRAINT `fk.app_mcp_tool_translation.app_mcp_tool_id` FOREIGN KEY (`app_mcp_tool_id`) REFERENCES `app_mcp_tool` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.app_mcp_tool_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
