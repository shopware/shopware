<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232704VersionCommit extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232704;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `version_commit` (
              `id` binary(16) NOT NULL,
              `auto_increment` bigint NOT NULL AUTO_INCREMENT UNIQUE,
              `is_merge` TINYINT(1) NOT NULL DEFAULT 0,
              `message` varchar(5000) NULL,
              `user_id` binary(16) NULL,
              `integration_id` binary(16) NULL,
              `version_id` binary(16) NOT NULL,
              `created_at` datetime(3) NOT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
