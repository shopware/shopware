<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536232720Country extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232720;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `country` (
              `id`                              BINARY(16)                              NOT NULL,
              `iso`                             VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `position`                        INT(11)                                 NOT NULL DEFAULT 1,
              `tax_free`                        TINYINT(1)                              NOT NULL DEFAULT 0,
              `active`                          TINYINT(1)                              NOT NULL DEFAULT 1,
              `iso3`                            VARCHAR(45) COLLATE utf8mb4_unicode_ci  NULL,
              `display_state_in_registration`   TINYINT(1)                              NOT NULL DEFAULT 0,
              `force_state_in_registration`     TINYINT(1)                              NOT NULL DEFAULT 0,
              `created_at`                      DATETIME(3)                             NOT NULL,
              `updated_at`                      DATETIME(3)                             NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE `country_translation` (
              `country_id`      BINARY(16)                              NOT NULL,
              `language_id`     BINARY(16)                              NOT NULL,
              `name`            VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `custom_fields`   JSON                                    NULL,
              `created_at`      DATETIME(3)                             NOT NULL,
              `updated_at`      DATETIME(3)                             NULL,
              PRIMARY KEY (`country_id`, `language_id`),
              CONSTRAINT `json.country_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.country_translation.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.country_translation.country_id` FOREIGN KEY (`country_id`)
                REFERENCES `country` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
