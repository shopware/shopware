<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232640Currency extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232640;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `currency` (
              `id` BINARY(16) NOT NULL,
              `is_default` TINYINT(1) NOT NULL DEFAULT 0,
              `factor` DOUBLE NOT NULL,
              `symbol` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `placed_in_front` TINYINT(1) NOT NULL DEFAULT 0,
              `position` INT(11) NOT NULL DEFAULT 1,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
               PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeQuery('
            CREATE TABLE `currency_translation` (
              `currency_id` BINARY(16) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `short_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `attributes` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`currency_id`, `language_id`),
              CONSTRAINT `JSON.attributes` CHECK (JSON_VALID(`attributes`)),
              CONSTRAINT `fk.currency_translation.language_id`
                FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.currency_translation.currency_id`
                FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
