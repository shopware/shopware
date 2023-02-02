<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536233410PromotionSalesChannel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233410;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `promotion_sales_channel` (
              `id` BINARY(16) NOT NULL,
              `promotion_id` BINARY(16) NOT NULL,
              `sales_channel_id` BINARY(16) NOT NULL,
              `priority` INT NOT NULL DEFAULT 0,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              INDEX `idx.promotion_sales_channel.sales_channel_id` (`sales_channel_id` ASC),
              INDEX `idx.promotion_sales_channel.promotion_id` (`promotion_id` ASC),
              CONSTRAINT `fk.promotion_sales_channel.promotion_id` FOREIGN KEY (`promotion_id`)
                REFERENCES `promotion` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
              CONSTRAINT `fk.promotion_sales_channel.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                REFERENCES `sales_channel` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
