<?php declare(strict_types=1);

namespace Shopware\Core\Version;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232707Integration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232707;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `integration` (
              `id` binary(16) NOT NULL,
              `tenant_id` binary(16) NOT NULL,
              `write_access` tinyint(1) NOT NULL DEFAULT \'0\',
              `access_key` varchar(255) NOT NULL,
              `secret_access_key` varchar(255) NOT NULL,
              `label` varchar(255) NOT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              `last_usage_at` datetime(3),
              PRIMARY KEY (`id`, `tenant_id`),
              INDEX `access_key` (`access_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
