<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536233390Promotion extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233390;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
        CREATE TABLE IF NOT EXISTS `promotion` (
              `id` BINARY(16) NOT NULL,
              `active` TINYINT(1) NOT NULL DEFAULT 0,
              `valid_from` DATETIME NULL,
              `valid_until` DATETIME NULL,
              `max_redemptions_global` INT NOT NULL DEFAULT 1,
              `max_redemptions_per_customer` INT NOT NULL DEFAULT 1,
              `order_count` INT NOT NULL DEFAULT 0,
              `orders_per_customer_count` JSON NULL,
              `exclusive` TINYINT(1) NOT NULL DEFAULT 0,
              `code` VARCHAR(255) NULL UNIQUE,
              `use_codes` TINYINT(1) NOT NULL DEFAULT 0,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`)
          ) ENGINE = InnoDB');

        $connection->executeStatement('
        CREATE TABLE `promotion_translation` (
            `name` VARCHAR(255) NULL,
            `promotion_id` BINARY(16) NOT NULL,
            `language_id` BINARY(16) NOT NULL,
            `created_at` DATETIME(3) NOT NULL,
            `updated_at` DATETIME(3) NULL,
            PRIMARY KEY (`promotion_id`,`language_id`),
            KEY `fk.promotion_translation.promotion_id` (`promotion_id`),
            KEY `fk.promotion_translation.language_id` (`language_id`),
            CONSTRAINT `fk.promotion_translation.promotion_id` FOREIGN KEY (`promotion_id`)
              REFERENCES `promotion` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk.promotion_translation.language_id` FOREIGN KEY (`language_id`)
              REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
