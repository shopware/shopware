<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536232630PropertyGroup extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232630;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `property_group` (
              `id`              BINARY(16)  NOT NULL,
              `sorting_type`    VARCHAR(50) NOT NULL DEFAULT \'alphanumeric\',
              `display_type`    VARCHAR(50) NOT NULL DEFAULT \'text\',
              `created_at`      DATETIME(3) NOT NULL,
              `updated_at`      DATETIME(3) NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE `property_group_translation` (
              `property_group_id`   BINARY(16)                              NOT NULL,
              `language_id`         BINARY(16)                              NOT NULL,
              `name`                VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `description`         LONGTEXT                                NULL,
              `custom_fields`       JSON                                    NULL,
              `created_at`          DATETIME(3)                             NOT NULL,
              `updated_at`          DATETIME(3)                             NULL,
              PRIMARY KEY (`property_group_id`, `language_id`),
              CONSTRAINT `json.property_group_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.property_group_translation.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.property_group_translation.property_group_id` FOREIGN KEY (`property_group_id`)
                REFERENCES `property_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
