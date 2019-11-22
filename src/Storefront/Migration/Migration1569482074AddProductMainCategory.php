<?php declare(strict_types=1);

namespace Shopware\Storefront\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1569482074AddProductMainCategory extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1569482074;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
            CREATE TABLE `main_category` (
              `id` BINARY(16) NOT NULL PRIMARY KEY,
              `product_id` BINARY(16) NOT NULL,
              `product_version_id` BINARY(16) NOT NULL,
              `category_id` BINARY(16) NOT NULL,
              `category_version_id` BINARY(16) NOT NULL,
              `sales_channel_id` BINARY(16) NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              CONSTRAINT `uniq.main_category.sales_channel_product` UNIQUE (`product_id`, `product_version_id`, `sales_channel_id`),
              CONSTRAINT `fk.main_category.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                REFERENCES `sales_channel` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
              CONSTRAINT `fk.main_category.category_id` FOREIGN KEY (`category_id`, `category_version_id`)
                REFERENCES `category` (`id`, `version_id`) ON UPDATE CASCADE ON DELETE CASCADE,
              CONSTRAINT `fk.main_category.product_id` FOREIGN KEY (`product_id`, `product_version_id`)
                REFERENCES `product` (`id`, `version_id`) ON UPDATE CASCADE ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        $connection->executeQuery($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
