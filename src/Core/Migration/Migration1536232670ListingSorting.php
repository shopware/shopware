<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232670ListingSorting extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232670;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `listing_sorting` (
              `id` BINARY(16) NOT NULL,
              `active` TINYINT(1) unsigned NOT NULL DEFAULT 1,
              `unique_key` VARCHAR(30) NOT NULL,
              `display_in_categories` TINYINT(1) unsigned NOT NULL DEFAULT 1,
              `position` INT(11) NOT NULL DEFAULT 1,
              `payload` JSON NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uniq.unique_key` (`unique_key`),
              KEY `idx.display_in_categories__position` (`display_in_categories`,`position`),
              CONSTRAINT `JSON.payload` CHECK (JSON_VALID(`payload`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeQuery('
            CREATE TABLE `listing_sorting_translation` (
              `listing_sorting_id` BINARY(16) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `label` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `attributes` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`listing_sorting_id`, `language_id`),
              CONSTRAINT `JSON.attributes` CHECK (JSON_VALID(`attributes`)),
              CONSTRAINT `fk.listing_sorting_translation.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.listing_sorting_translation.listing_sorting_id` FOREIGN KEY (`listing_sorting_id`)
                REFERENCES `listing_sorting` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
