<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232880PaymentMethod extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232880;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `payment_method` (
                `id`                   BINARY(16)                              NOT NULL,
                `technical_name`       VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `template`             VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
                `class`                VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
                `percentage_surcharge` DOUBLE                                  NULL,
                `absolute_surcharge`   DOUBLE                                  NULL,
                `surcharge_string`     VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
                `position`             INT(11)                                 NOT NULL DEFAULT 1,
                `active`               TINYINT(1)                              NOT NULL DEFAULT 0,
                `risk_rules`           JSON                                    NULL,
                `plugin_id`            BINARY(16)                              NULL,
                `created_at`           DATETIME(3)                             NOT NULL,
                `updated_at`           DATETIME(3)                             NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.name` (`technical_name`),
                CONSTRAINT `fk.payment_method.plugin_id` FOREIGN KEY (plugin_id)
                  REFERENCES plugin (id) ON UPDATE CASCADE ON DELETE SET NULL,
                CONSTRAINT `json.risk_rules` CHECK (JSON_VALID(`risk_rules`))
                ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        ');

        $connection->executeQuery('
            CREATE TABLE `payment_method_translation` (
              `payment_method_id` BINARY(16) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `additional_description` MEDIUMTEXT COLLATE utf8mb4_unicode_ci NULL,
              `attributes` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`payment_method_id`, `language_id`),
              CONSTRAINT `json.attributes` CHECK (JSON_VALID(`attributes`)),
              CONSTRAINT `fk.payment_method_translation.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.payment_method_translation.payment_method_id` FOREIGN KEY (`payment_method_id`)
                REFERENCES `payment_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
