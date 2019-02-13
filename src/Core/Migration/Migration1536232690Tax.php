<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232690Tax extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232690;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `tax` (
              `id` BINARY(16) NOT NULL,
              `tax_rate` DECIMAL(10, 2) NOT NULL,
              `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `attributes` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              KEY `idx.tax` (`tax_rate`),
              CONSTRAINT `JSON.attributes` CHECK (JSON_VALID(`attributes`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
