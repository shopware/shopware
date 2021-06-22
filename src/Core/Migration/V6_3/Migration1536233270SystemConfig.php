<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536233270SystemConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233270;
    }

    public function update(Connection $connection): void
    {
        $query = <<<'SQL'
            CREATE TABLE IF NOT EXISTS `system_config` (
                `id` BINARY(16) NOT NULL,
                `configuration_key` VARCHAR(255) NOT NULL,
                `configuration_value` JSON NOT NULL,
                `sales_channel_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `json.system_config.configuration_value` CHECK (JSON_VALID(`configuration_value`)),
                CONSTRAINT `uniq.system_config.configuration_key__sales_channel_id` UNIQUE (`configuration_key`, `sales_channel_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $connection->executeUpdate($query);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
