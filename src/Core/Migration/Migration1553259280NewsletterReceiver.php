<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1553259280NewsletterReceiver extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1553259280;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `newsletter_receiver` (
              `id` BINARY(16) NOT NULL,
              `email` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `first_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `last_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `zip_code` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `city` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `street` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `status` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `hash` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
              `salutation_id` BINARY(16) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `sales_channel_id` BINARY(16) NOT NULL,
              `attributes` JSON NULL,
              `confirmed_at` DATETIME(3) NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `fk.newsletter_receiver.salutation_id` FOREIGN KEY (`salutation_id`)
                REFERENCES `salutation` (`id`) ON DELETE RESTRICT,
              CONSTRAINT `fk.newsletter_receiver.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE RESTRICT,
              CONSTRAINT `fk.newsletter_receiver.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                REFERENCES `sales_channel` (`id`) ON DELETE RESTRICT,
              CONSTRAINT `json.attributes` CHECK (JSON_VALID(`attributes`)) 
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
