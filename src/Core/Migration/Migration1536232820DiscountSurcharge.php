<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232820DiscountSurcharge extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232820;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `discount_surcharge` (
              `id` BINARY(16) NOT NULL,
              `rule_id` BINARY(16) NOT NULL,
              `type` VARCHAR(255),
              `amount` FLOAT NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
               PRIMARY KEY (`id`),
               CONSTRAINT `fk.discount_surcharge.rule_id` FOREIGN KEY (`rule_id`)
                 REFERENCES `rule` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeQuery('
            CREATE TABLE `discount_surcharge_translation` (
              `discount_surcharge_id` BINARY(16) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `name` VARCHAR(500) COLLATE utf8mb4_unicode_ci NULL,
              `attributes` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`discount_surcharge_id`, `language_id`),
              CONSTRAINT `json.attributes` CHECK (JSON_VALID(`attributes`)),
              CONSTRAINT `fk.discount_surcharge_translation.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.discount_surcharge_translation.discount_surcharge_id` FOREIGN KEY (`discount_surcharge_id`)
                REFERENCES `discount_surcharge` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
