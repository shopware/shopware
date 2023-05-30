<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1604056421CustomerWishlistProducts extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1604056421;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `customer_wishlist_product` (
              `id`                    binary(16)  NOT NULL,
              `customer_wishlist_id`  binary(16)  NOT NULL,
              `product_id`            binary(16)  NOT NULL,
              `product_version_id`    binary(16)  NOT NULL,
              `custom_fields`         json        NULL,
              `created_at`            datetime(3) NOT NULL,
              `updated_at`            datetime(3) DEFAULT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uniq.customer_wishlist.sales_channel_id__customer_id` (`customer_wishlist_id`,`product_id`),
              KEY `fk.customer_wishlist_product.product_id` (`product_id`,`product_version_id`),
              KEY `fk.customer_wishlist_product.customer_wishlist_id` (`customer_wishlist_id`),
              CONSTRAINT `fk.customer_wishlist_product.product_id` FOREIGN KEY (`product_id`, `product_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.customer_wishlist_product.customer_wishlist_id` FOREIGN KEY (`customer_wishlist_id`) REFERENCES `customer_wishlist` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
