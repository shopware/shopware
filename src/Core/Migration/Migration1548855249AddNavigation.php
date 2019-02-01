<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1548855249AddNavigation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1548855249;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `navigation` (
              `id` binary(16) NOT NULL,
              `version_id` binary(16) NOT NULL,
              `parent_id` binary(16) NULL,
              `parent_version_id` binary(16) NULL,
              `category_id` binary(16) NULL,
              `category_version_id` binary(16) NULL,
              `path` longtext COLLATE utf8mb4_unicode_ci,
              `position` int(11) unsigned NOT NULL DEFAULT \'1\',
              `level` int(11) unsigned NOT NULL DEFAULT \'1\',
              `child_count` int(11) unsigned NOT NULL DEFAULT \'0\',
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`, `version_id`),
              KEY `position` (`position`),
              KEY `level` (`level`),
              CONSTRAINT `fk.navigation.parent_id` FOREIGN KEY (`parent_id`, `parent_version_id`) REFERENCES `navigation` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.navigation.category_id` FOREIGN KEY (`category_id`, `category_version_id`) REFERENCES `category` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
