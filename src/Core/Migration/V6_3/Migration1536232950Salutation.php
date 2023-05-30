<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536232950Salutation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232950;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `salutation` (
              `id`             BINARY(16)    NOT NULL,
              `salutation_key` VARCHAR(255)  NOT NULL,
              `created_at`     DATETIME(3)   NOT NULL,
              `updated_at`     DATETIME(3)   NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uniq.salutation_key` (`salutation_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE `salutation_translation` (
              `salutation_id` BINARY(16)    NOT NULL,
              `language_id`   BINARY(16)    NOT NULL,
              `display_name`  VARCHAR(255)  NULL,
              `letter_name`   VARCHAR(255)  NULL,
              `created_at`    DATETIME(3)   NOT NULL,
              `updated_at`    DATETIME(3)   NULL,
              PRIMARY KEY (`salutation_id`, `language_id`),
              CONSTRAINT `fk.salutation_translation.salutation_id`   FOREIGN KEY (`salutation_id`)
                REFERENCES `salutation` (`id`)  ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.salutation_translation.language_id`     FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`)    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
