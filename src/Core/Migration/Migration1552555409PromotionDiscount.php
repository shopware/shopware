<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1552555409PromotionDiscount extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1552555409;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
        CREATE TABLE IF NOT EXISTS `promotion_discount` (
          `id` BINARY(16) NOT NULL,
          `promotion_id` BINARY(16) NOT NULL,
          `type` VARCHAR(32) NOT NULL,
          `value` DOUBLE NOT NULL,
          `graduated` TINYINT(1) NOT NULL DEFAULT 0,
          `graduation_step` INT NULL,
          `graduation_order` VARCHAR(32) NULL,
          `apply_towards` VARCHAR(32) NOT NULL,
          PRIMARY KEY (`promotion_id`),
          INDEX `idx.promotion_discount.promotion_id` (`promotion_id` ASC),
          CONSTRAINT `fk.promotion_discount.promotion_id`
            FOREIGN KEY (`promotion_id`)
            REFERENCES `promotion` (`id`)
            ON DELETE CASCADE
            ON UPDATE NO ACTION
          )
        ENGINE = InnoDB
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
