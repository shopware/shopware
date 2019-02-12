<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536240667ProductManufacturerTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536240667;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `product_manufacturer_translation` (
              `product_manufacturer_id` binary(16) NOT NULL,
              `product_manufacturer_version_id` binary(16) NOT NULL,
              `language_id` binary(16) NOT NULL,
              `name` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `description` longtext COLLATE utf8mb4_unicode_ci NULL,
              `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `meta_description` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `meta_keywords` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`product_manufacturer_id`, `product_manufacturer_version_id`, `language_id`),
              CONSTRAINT `fk.product_manufacturer_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.product_manufacturer_translation.product_manufacturer_id` FOREIGN KEY (`product_manufacturer_id`, `product_manufacturer_version_id`) REFERENCES `product_manufacturer` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
