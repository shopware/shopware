<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536232980Cart extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232980;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `cart` (
              `token` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
              `name` VARCHAR(500) COLLATE utf8mb4_unicode_ci NOT NULL,
              `cart` LONGTEXT NOT NULL,
              `price` FLOAT NOT NULL,
              `line_item_count` VARCHAR(42) COLLATE utf8mb4_unicode_ci NOT NULL,
              `currency_id` BINARY(16) NOT NULL,
              `shipping_method_id` BINARY(16) NOT NULL,
              `payment_method_id` BINARY(16) NOT NULL,
              `country_id` BINARY(16) NOT NULL,
              `customer_id` BINARY(16) NULL,
              `sales_channel_id` BINARY(16) NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              PRIMARY KEY (`token`),
              CONSTRAINT `fk.cart.country_id` FOREIGN KEY (`country_id`)
                REFERENCES `country` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.cart.currency_id` FOREIGN KEY (`currency_id`)
                REFERENCES `currency` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.cart.customer_id` FOREIGN KEY (`customer_id`)
                REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.cart.payment_method_id` FOREIGN KEY (`payment_method_id`)
                REFERENCES `payment_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.cart.shipping_method_id` FOREIGN KEY (`shipping_method_id`)
                REFERENCES `shipping_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.cart.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
