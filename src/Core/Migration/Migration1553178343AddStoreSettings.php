<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1553178343AddStoreSettings extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1553178343;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `store_settings` (
              `id` BINARY(16) NOT NULL,
              `setting_key` VARCHAR(250) NOT NULL,
              `setting_value` VARCHAR(250) NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
