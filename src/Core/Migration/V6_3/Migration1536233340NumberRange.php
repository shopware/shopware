<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536233340NumberRange extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233340;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<'SQL'
            CREATE TABLE `number_range` (
              `id` BINARY(16) NOT NULL,
              `type_id` BINARY(16) NOT NULL,
              `global` TINYINT(1) NOT NULL,
              `pattern` VARCHAR(255) NOT NULL,
              `start` INTEGER(8) NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->executeStatement($sql);

        $sql = <<<'SQL'
            CREATE TABLE `number_range_translation` (
              `number_range_id` BINARY(16) NOT NULL,
              `name` VARCHAR(64) NULL,
              `description` VARCHAR(255) NULL,
              `custom_fields` JSON NULL,
              `language_id` BINARY(16) NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`number_range_id`, `language_id`),
              CONSTRAINT `json.number_range_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.number_range_translation.number_range_id`   FOREIGN KEY (`number_range_id`)
                REFERENCES `number_range` (`id`)  ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.number_range_translation.language_id`     FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`)    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->executeStatement($sql);

        $sql = <<<'SQL'
            CREATE TABLE `number_range_state` (
              `id` BINARY(16) NOT NULL,
              `number_range_id` BINARY(16) NOT NULL,
              `last_value` INTEGER(8) NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`number_range_id`),
              UNIQUE `uniq.id` (`id`),
              INDEX `idx.number_range_id` (`number_range_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        // No Foreign Key here is intended. It should be possible to handle the state with another Persistence so
        // we can force MySQL to expect a Dependency here
        $connection->executeStatement($sql);

        $sql = <<<'SQL'
            CREATE TABLE `number_range_type` (
              `id` BINARY(16) NOT NULL,
              `technical_name` VARCHAR(64),
              `global` TINYINT(1) NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              UNIQUE `uniq.technical_name` (`technical_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $connection->executeStatement($sql);

        $sql = <<<'SQL'
            CREATE TABLE `number_range_type_translation` (
              `number_range_type_id` BINARY(16) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `type_name` VARCHAR(64) NOT NULL,
              `custom_fields` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
               PRIMARY KEY (`number_range_type_id`, `language_id`),
              CONSTRAINT `fk.number_range_type_translation.number_range_type_id`   FOREIGN KEY (`number_range_type_id`)
                REFERENCES `number_range_type` (`id`)  ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.number_range_type_translation.language_id`     FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`)    ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `json.number_range_type_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $connection->executeStatement($sql);

        $sql = <<<'SQL'
            CREATE TABLE `number_range_sales_channel` (
              `id` BINARY(16) NOT NULL,
              `number_range_id` BINARY(16) NOT NULL,
              `sales_channel_id` BINARY(16) NULL,
              `number_range_type_id` BINARY(16) NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              UNIQUE `uniq.numer_range_id__sales_channel_id` (`number_range_id`, `sales_channel_id`),
              CONSTRAINT `fk.number_range_sales_channel.number_range_id`
                FOREIGN KEY (number_range_id) REFERENCES `number_range` (id) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.number_range_sales_channel.sales_channel_id`
                FOREIGN KEY (sales_channel_id) REFERENCES `sales_channel` (id) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.number_range_sales_channel.number_range_type_id`
                FOREIGN KEY (number_range_type_id) REFERENCES `number_range_type` (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
