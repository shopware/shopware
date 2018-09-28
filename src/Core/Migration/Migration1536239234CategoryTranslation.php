<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536239234CategoryTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536239234;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `category_translation` (
              `category_id` binary(16) NOT NULL,
              `category_version_id` binary(16) NOT NULL,
              `category_tenant_id` binary(16) NOT NULL,
              `language_id` binary(16) NOT NULL,
              `language_tenant_id` binary(16) NOT NULL,
              `catalog_id` binary(16) NOT NULL,
              `catalog_tenant_id` binary(16) NOT NULL,
              `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `path_names` longtext COLLATE utf8mb4_unicode_ci,
              `meta_keywords` mediumtext COLLATE utf8mb4_unicode_ci,
              `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `meta_description` mediumtext COLLATE utf8mb4_unicode_ci,
              `cms_headline` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `cms_description` mediumtext COLLATE utf8mb4_unicode_ci,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`category_id`, `category_version_id`, `category_tenant_id`, `language_id`, `language_tenant_id`),
              CONSTRAINT `category_translation_ibfk_1` FOREIGN KEY (`language_id`, `category_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `category_translation_ibfk_2` FOREIGN KEY (`category_id`, `category_version_id`, `language_tenant_id`) REFERENCES `category` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `category_translation_ibfk_3` FOREIGN KEY (`catalog_id`, `catalog_tenant_id`) REFERENCES `catalog` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
