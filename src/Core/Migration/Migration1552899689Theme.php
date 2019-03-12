<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1552899689Theme extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1552899689;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `theme` (
              `id` BINARY(16) NOT NULL,
              `name` VARCHAR(255) NOT NULL,
              `author` VARCHAR(255) NOT NULL,
              `config` JSON NOT NULL,
              `themeValues` JSON NULL,
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
