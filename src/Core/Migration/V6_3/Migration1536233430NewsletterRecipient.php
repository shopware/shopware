<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536233430NewsletterRecipient extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233430;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `newsletter_recipient` (
              `id` BINARY(16) NOT NULL,
              `email` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `title` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `first_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `last_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `zip_code` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `city` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `street` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `status` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `hash` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
              `salutation_id` BINARY(16) NULL,
              `language_id` BINARY(16) NOT NULL,
              `sales_channel_id` BINARY(16) NOT NULL,
              `custom_fields` JSON NULL,
              `confirmed_at` DATETIME(3) NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `json.newsletter_recipient.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.newsletter_recipient.salutation_id` FOREIGN KEY (`salutation_id`)
                REFERENCES `salutation` (`id`) ON DELETE RESTRICT,
              CONSTRAINT `fk.newsletter_recipient.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE RESTRICT,
              CONSTRAINT `fk.newsletter_recipient.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                REFERENCES `sales_channel` (`id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
