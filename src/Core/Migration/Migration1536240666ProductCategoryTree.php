<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536240666ProductCategoryTree extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536240666;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `product_category_tree` (
              `product_id` binary(16) NOT NULL,
              `product_tenant_id` binary(16) NOT NULL,
              `product_version_id` binary(16) NOT NULL,
              `category_id` binary(16) NOT NULL,
              `category_tenant_id` binary(16) NOT NULL,
              `category_version_id` binary(16) NOT NULL,
              PRIMARY KEY (`product_id`, `product_version_id`, `product_tenant_id`, `category_id`, `category_version_id`, `category_tenant_id`),
              CONSTRAINT `product_category_tree_ibfk_1` FOREIGN KEY (`product_id`, `product_version_id`, `product_tenant_id`) REFERENCES `product` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `product_category_tree_ibfk_2` FOREIGN KEY (`category_id`, `category_version_id`, `category_tenant_id`) REFERENCES `category` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
