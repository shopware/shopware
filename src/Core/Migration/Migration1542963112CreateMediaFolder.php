<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1542963112CreateMediaFolder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1542963112;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            CREATE TABLE `media_folder` (
              `id` BINARY(16) NOT NULL,
              `version_id` BINARY(16) NOT NULL,
              `parent_id` BINARY(16),
              `parent_version_id` BINARY(16),
              `child_count` int(11) unsigned NOT NULL DEFAULT \'0\',
              `media_folder_configuration_id` BINARY(16),
              `media_folder_configuration_version_id` BINARY(16),
              `configuration` BINARY(16),
              `use_parent_configuration` TINYINT(1),
              `created_at` DATETIME(3),
              `updated_at` DATETIME(3),
              PRIMARY KEY (`id`, `version_id`),
              CONSTRAINT `media_folder_ibfk_1` FOREIGN KEY (`parent_id`, `parent_version_id`) REFERENCES `media_folder` (`id`, `version_id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // no destructive changes
    }
}
