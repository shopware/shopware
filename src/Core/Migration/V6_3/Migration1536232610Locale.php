<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536232610Locale extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232610;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE `locale` (
               `id`          BINARY(16)                              NOT NULL,
               `code`        VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
               `created_at`  DATETIME(3)                             NOT NULL,
               `updated_at`  DATETIME(3)                             NULL,
               PRIMARY KEY (`id`),
               UNIQUE KEY `uniq.code` (`code`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        $connection->executeStatement(
            'ALTER TABLE `language` ADD CONSTRAINT `fk.language.locale_id` FOREIGN KEY (`locale_id`)
               REFERENCES `locale` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;'
        );

        $connection->executeStatement(
            'ALTER TABLE `language` ADD CONSTRAINT `fk.language.translation_code_id` FOREIGN KEY (`translation_code_id`)
               REFERENCES `locale` (`id`) ON DELETE CASCADE ON UPDATE CASCADE'
        );

        $connection->executeStatement(
            'CREATE TABLE `locale_translation` (
               `locale_id`       BINARY(16)                              NOT NULL,
               `language_id`     BINARY(16)                              NOT NULL,
               `name`            VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
               `territory`       VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
               `custom_fields`   JSON                                    NULL,
               `created_at`      DATETIME(3)                             NOT NULL,
               `updated_at`      DATETIME(3)                             NULL,
               PRIMARY KEY (`locale_id`, `language_id`),
               CONSTRAINT `json.locale_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
               CONSTRAINT `fk.locale_translation.language_id` FOREIGN KEY (`language_id`)
                 REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
               CONSTRAINT `fk.locale_translation.locale_id` FOREIGN KEY (`locale_id`)
                 REFERENCES `locale` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
