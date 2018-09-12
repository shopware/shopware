<?php declare(strict_types=1);

namespace Shopware\Core\Version;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536240664ProductTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536240664;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `product_translation` (
              `product_id` binary(16) NOT NULL,
              `product_version_id` binary(16) NOT NULL,
              `product_tenant_id` binary(16) NOT NULL,
              `language_id` binary(16) NOT NULL,
              `language_tenant_id` binary(16) NOT NULL,
              `catalog_id` binary(16) NOT NULL,
              `catalog_tenant_id` binary(16) NOT NULL,
              `additional_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `name` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `keywords` mediumtext COLLATE utf8mb4_unicode_ci,
              `description` mediumtext COLLATE utf8mb4_unicode_ci,
              `description_long` mediumtext COLLATE utf8mb4_unicode_ci,
              `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `pack_unit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`product_id`, `language_id`, `product_version_id`, `language_tenant_id`, `product_tenant_id`),
              CONSTRAINT `fk_product_trans.language_id` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk_product_trans.product_id` FOREIGN KEY (`product_id`, `product_version_id`, `product_tenant_id`) REFERENCES `product` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk_product_trans.catalog_id` FOREIGN KEY (`catalog_id`, `catalog_tenant_id`) REFERENCES `catalog` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
