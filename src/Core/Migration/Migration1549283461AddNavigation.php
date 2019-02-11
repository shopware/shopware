<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1549283461AddNavigation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1549283461;
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
              `level` int(11) unsigned NOT NULL DEFAULT \'1\',
              `child_count` int(11) unsigned NOT NULL DEFAULT \'0\',
              `cms_page_id` binary(16) NULL, 
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`, `version_id`),
              KEY `level` (`level`),
              CONSTRAINT `fk.navigation.parent_id` FOREIGN KEY (`parent_id`, `parent_version_id`) REFERENCES `navigation` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.navigation.cms_page_id` FOREIGN KEY (`cms_page_id`) REFERENCES `cms_page` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE,
              CONSTRAINT `fk.navigation.category_id` FOREIGN KEY (`category_id`, `category_version_id`) REFERENCES `category` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeUpdate('
ALTER TABLE `sales_channel`
ADD `navigation_id` binary(16) NULL AFTER `tax_calculation_type`,
ADD `navigation_version_id` binary(16) NULL AFTER `navigation_id`;        
        ');

        $connection->executeUpdate('
ALTER TABLE `sales_channel`
ADD FOREIGN KEY `fk.sales_channel.navigation_id` (`navigation_id`, `navigation_version_id`) REFERENCES `navigation` (`id`, `version_id`) ON DELETE NO ACTION ON UPDATE CASCADE     
        ');

        $connection->executeQuery('
            CREATE TABLE `navigation_translation` (
              `navigation_id` binary(16) NOT NULL,
              `navigation_version_id` binary(16) NOT NULL,
              `language_id` binary(16) NOT NULL,
              `name` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `slot_config` json,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`navigation_id`, `navigation_version_id`, `language_id`),
              CONSTRAINT `fk.navigation_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.navigation_translation.navigation_id` FOREIGN KEY (`navigation_id`, `navigation_version_id`) REFERENCES `navigation` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
