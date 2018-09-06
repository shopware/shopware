<?php declare(strict_types=1);

namespace Shopware\Core\Version;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536237812Category extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536237812;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `category` (
              `id` binary(16) NOT NULL,
              `tenant_id` binary(16) NOT NULL,
              `version_id` binary(16) NOT NULL,
              `catalog_id` binary(16) NOT NULL,
              `catalog_tenant_id` binary(16) NOT NULL,
              `auto_increment` bigint unsigned NOT NULL AUTO_INCREMENT,
              `parent_id` binary(16) DEFAULT NULL,
              `parent_tenant_id` binary(16) DEFAULT NULL,
              `parent_version_id` binary(16) DEFAULT NULL,
              `media_id` binary(16) DEFAULT NULL,
              `media_tenant_id` binary(16) DEFAULT NULL,
              `media_version_id` binary(16) DEFAULT NULL,
              `path` longtext COLLATE utf8mb4_unicode_ci,
              `position` int(11) unsigned NOT NULL DEFAULT \'1\',
              `level` int(11) unsigned NOT NULL DEFAULT \'1\',
              `template` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `active` tinyint(1) NOT NULL DEFAULT \'1\',
              `is_blog` tinyint(1) NOT NULL DEFAULT \'0\',
              `external` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `hide_filter` tinyint(1) NOT NULL DEFAULT \'0\',
              `hide_top` tinyint(1) NOT NULL DEFAULT \'0\',
              `product_box_layout` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `hide_sortings` tinyint(1) NOT NULL DEFAULT \'0\',
              `sorting_ids` longtext COLLATE utf8mb4_unicode_ci,
              `facet_ids` longtext COLLATE utf8mb4_unicode_ci,
              `child_count` int(11) unsigned NOT NULL DEFAULT \'0\',
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`id`, `version_id`, `tenant_id`),
              KEY `position` (`position`),
              KEY `level` (`level`),
              KEY `auto_increment` (`auto_increment`),
              CONSTRAINT `fk_category.catalog_id` FOREIGN KEY (`catalog_id`, `catalog_tenant_id`) REFERENCES `catalog` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk_category.media_id` FOREIGN KEY (`media_id`, `media_version_id`, `media_tenant_id`) REFERENCES `media` (`id`, `version_id`, `tenant_id`) ON DELETE SET NULL ON UPDATE CASCADE,
              CONSTRAINT `fk_category.parent_id` FOREIGN KEY (`parent_id`, `parent_version_id`, `parent_tenant_id`) REFERENCES `category` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
