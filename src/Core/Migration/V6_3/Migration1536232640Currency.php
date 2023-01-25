<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536232640Currency extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232640;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `currency` (
              `id`                  BINARY(16)                              NOT NULL,
              `iso_code`            CHAR(3)                                 NOT NULL,
              `factor`              DOUBLE                                  NOT NULL,
              `symbol`              VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `position`            INT(11)                                 NOT NULL DEFAULT 1,
              `decimal_precision`   INT(11)                                 NOT NULL,
              `created_at`          DATETIME(3)                             NOT NULL,
              `updated_at`          DATETIME(3)                             NULL,
               PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE `currency_translation` (
              `currency_id` BINARY(16) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `short_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `custom_fields` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`currency_id`, `language_id`),
              CONSTRAINT `json.currency_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.currency_translation.language_id`
                FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.currency_translation.currency_id`
                FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
