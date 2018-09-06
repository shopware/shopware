<?php declare(strict_types=1);

namespace Shopware\Core\Version;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536234581VersionCommitData extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536234581;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `version_commit_data` (
              `id` binary(16) NOT NULL,
              `tenant_id` binary(16) NOT NULL,
              `auto_increment` bigint NOT NULL AUTO_INCREMENT UNIQUE,
              `version_commit_id` binary(16) NOT NULL,
              `version_commit_tenant_id` binary(16) NOT NULL,
              `entity_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
              `entity_id` LONGTEXT NOT NULL,
              `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
              `payload` LONGTEXT NOT NULL,
              `user_id` binary(16) DEFAULT NULL,
              `user_tenant_id` binary(16) DEFAULT NULL,
              `integration_id` binary(16) DEFAULT NULL,
              `integration_tenant_id` binary(16) DEFAULT NULL,
              `created_at` datetime(3) NOT NULL,
              CHECK (JSON_VALID (`entity_id`)),
              CHECK (JSON_VALID (`payload`)),
              PRIMARY KEY (`id`, `tenant_id`),
              FOREIGN KEY (`version_commit_id`, `version_commit_tenant_id`) REFERENCES `version_commit` (`id`, `tenant_id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
