<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1551283937Promotion extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1551283937;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
        CREATE TABLE IF NOT EXISTS `promotion` (
              `id` BINARY(16) NOT NULL,
              `name` VARCHAR(255) NOT NULL,
              `active` TINYINT(1) NOT NULL DEFAULT 0,
              `valid_from` DATETIME NULL,
              `valid_until` DATETIME NULL,
              `redeemable` INT NULL DEFAULT 1,
              `exclusive` TINYINT(1) NOT NULL DEFAULT 0,
              `priority` INT NOT NULL DEFAULT 0,
              `exclude_lower_priority` TINYINT(1) NOT NULL DEFAULT 0,
              `discount_rule_id` BINARY(16) NULL,
              `code` VARCHAR(255) NULL UNIQUE,
              `use_codes` TINYINT(1) NOT NULL DEFAULT 0,
              PRIMARY KEY (`id`),
              INDEX `idx.promotion.discount_rule_id` (`discount_rule_id` ASC),
              CONSTRAINT `fk.promotion.discount_rule_id`
                FOREIGN KEY (`discount_rule_id`)
                REFERENCES `rule` (`id`)
                ON DELETE NO ACTION
                ON UPDATE NO ACTION)
            ENGINE = InnoDB');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
