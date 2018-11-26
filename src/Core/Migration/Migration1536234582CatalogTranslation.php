<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536234582CatalogTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536234582;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `catalog_translation` (
              `catalog_id` binary(16) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `name` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`catalog_id`, `language_id`),
              CONSTRAINT `fk.catalog_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.catalog_translation.catalog_id` FOREIGN KEY (`catalog_id`) REFERENCES `catalog` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
