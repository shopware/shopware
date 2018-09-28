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
              `product_manufacturer_tenant_id` binary(16) NOT NULL,
              `product_manufacturer_version_id` binary(16) NOT NULL,
              `catalog_id` binary(16) NOT NULL,
              `catalog_tenant_id` binary(16) NOT NULL,
              `language_id` binary(16) NOT NULL,
              `language_tenant_id` binary(16) NOT NULL,
              `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `description` longtext COLLATE utf8mb4_unicode_ci,
              `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `meta_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `meta_keywords` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`product_manufacturer_id`, `product_manufacturer_version_id`, `product_manufacturer_tenant_id`,`language_id`, `language_tenant_id`),
              CONSTRAINT `product_manufacturer_translation_ibfk_1` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `product_manufacturer_translation_ibfk_2` FOREIGN KEY (`product_manufacturer_id`, `product_manufacturer_version_id`, `product_manufacturer_tenant_id`) REFERENCES `product_manufacturer` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `product_manufacturer_translation_ibfk_3` FOREIGN KEY (`catalog_id`, `catalog_tenant_id`) REFERENCES `catalog` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
