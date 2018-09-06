<?php declare(strict_types=1);

namespace Shopware\Core\Version;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232687Currency extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232687;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `currency` (
              `id` binary(16) NOT NULL,
              `tenant_id` binary(16) NOT NULL,
              `version_id` binary(16) NOT NULL,
              `is_default` tinyint(1) NOT NULL DEFAULT \'0\',
              `factor` double NOT NULL,
              `symbol` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `placed_in_front` tinyint(1) NOT NULL DEFAULT \'0\',
              `position` int(11) NOT NULL DEFAULT \'1\',
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
               PRIMARY KEY (`id`, `version_id`, `tenant_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
