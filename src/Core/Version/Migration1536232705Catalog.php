<?php declare(strict_types=1);

namespace Shopware\Core\Version;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232705Catalog extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232705;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `catalog` (
              `id` binary(16) NOT NULL,
              `tenant_id` binary(16) NOT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`, `tenant_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
