<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536239240ProductManufacturer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536239240;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `product_manufacturer` (
              `id` binary(16) NOT NULL,
              `tenant_id` binary(16) NOT NULL,
              `catalog_id` binary(16) NOT NULL,
              `catalog_tenant_id` binary(16) NOT NULL,
              `version_id` binary(16) NOT NULL,
              `link` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
              `media_id` binary(16) DEFAULT NULL,
              `media_tenant_id` binary(16) DEFAULT NULL,
              `media_version_id` binary(16) DEFAULT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
               PRIMARY KEY (`id`, `version_id`, `tenant_id`),
               CONSTRAINT `fk_product_manufacturer.media_id` FOREIGN KEY (`media_id`, `media_version_id`, `media_tenant_id`) REFERENCES `media` (`id`, `version_id`, `tenant_id`) ON DELETE SET NULL ON UPDATE CASCADE,
               CONSTRAINT `fk_product_manufacturer.catalog_id` FOREIGN KEY (`catalog_id`, `catalog_tenant_id`) REFERENCES `catalog` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
