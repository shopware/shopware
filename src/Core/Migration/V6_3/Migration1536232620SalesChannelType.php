<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536232620SalesChannelType extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232620;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `sales_channel_type` (
              `id`              BINARY(16)                              NOT NULL,
              `cover_url`       VARCHAR(500) COLLATE utf8mb4_unicode_ci NULL,
              `icon_name`       VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `screenshot_urls` JSON                                    NULL,
              `created_at`      DATETIME(3)                             NOT NULL,
              `updated_at`      DATETIME(3)                             NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `json.sales_channel_type.screenshot_urls` CHECK (JSON_VALID(`screenshot_urls`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE `sales_channel_type_translation` (
              `sales_channel_type_id`   BINARY(16)                              NOT NULL,
              `language_id`             BINARY(16)                              NOT NULL,
              `name`                    VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `manufacturer`            VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `description`             VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `description_long`        LONGTEXT COLLATE utf8mb4_unicode_ci     NULL,
              `custom_fields`           JSON                                    NULL,
              `created_at`              DATETIME(3)                             NOT NULL,
              `updated_at`              DATETIME(3)                             NULL,
              PRIMARY KEY (`sales_channel_type_id`, `language_id`),
              CONSTRAINT `json.sales_channel_type_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.sales_channel_type_translation.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.sales_channel_type_translation.sales_channel_type_id` FOREIGN KEY (`sales_channel_type_id`)
                REFERENCES `sales_channel_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
