<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1558594334PromotionDiscountPrice extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1558594334;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
          CREATE TABLE `promotion_discount_prices` (
              `id` BINARY(16) NOT NULL,
              `discount_id` BINARY(16) NOT NULL,
              `currency_id` BINARY(16) NOT NULL,
              `price` float NOT NULL DEFAULT 0,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              INDEX `fk.promotion_discount_prices.discount_id` (`discount_id`),
              INDEX `fk.promotion_discount_prices.currency_id` (`currency_id`),
              CONSTRAINT `fk.promotion_discount_prices.currency_id` FOREIGN KEY (`currency_id`)
                REFERENCES `currency` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.promotion_discount_prices.discount_id` FOREIGN KEY (`discount_id`)
                REFERENCES `promotion_discount` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
