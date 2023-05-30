<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536232670Unit extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232670;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `unit` (
              `id`          BINARY(16)  NOT NULL,
              `created_at`  DATETIME(3) NOT NULL,
              `updated_at`  DATETIME(3) NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE `unit_translation` (
              `unit_id`         BINARY(16) NOT NULL,
              `language_id`     BINARY(16) NOT NULL,
              `short_code`      VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `name`            VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `custom_fields`   JSON NULL,
              `created_at`      DATETIME(3) NOT NULL,
              `updated_at`      DATETIME(3) NULL,
              PRIMARY KEY (`unit_id`,`language_id`),
              CONSTRAINT `json.unit_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.unit_translation.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.unit_translation.unit_id` FOREIGN KEY (`unit_id`)
                REFERENCES `unit` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
