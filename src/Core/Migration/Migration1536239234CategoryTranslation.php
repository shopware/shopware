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
              `language_id` binary(16) NOT NULL,
              `catalog_id` binary(16) NOT NULL,
              `name` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `path_names` longtext COLLATE utf8mb4_unicode_ci NULL,
              `meta_keywords` mediumtext COLLATE utf8mb4_unicode_ci NULL,
              `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `meta_description` mediumtext COLLATE utf8mb4_unicode_ci NULL,
              `cms_headline` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `cms_description` mediumtext COLLATE utf8mb4_unicode_ci NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`category_id`, `category_version_id`, `language_id`),
              CONSTRAINT `fk.category_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.category_translation.category_id` FOREIGN KEY (`category_id`, `category_version_id`) REFERENCES `category` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.category_translation.catalog_id` FOREIGN KEY (`catalog_id`) REFERENCES `catalog` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
