<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1548764880AddSystemConfigTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1548764880;
    }

    public function update(Connection $connection): void
    {
        $query = <<<SQL
CREATE TABLE IF NOT EXISTS `system_config` (
    `id` BINARY(16) NOT NULL, 
    `namespace` VARCHAR(255) NOT NULL,
    `configuration_key` VARCHAR(255) NOT NULL,
    `configuration_value` LONGTEXT NOT NULL,
    `sales_channel_id` BINARY(16) DEFAULT NULL,
    `created_at` DATETIME(3),
    `updated_at` DATETIME(3),
    PRIMARY KEY (`id`),
    UNIQUE (`configuration_key`, `namespace`, `sales_channel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $connection->executeQuery($query);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
