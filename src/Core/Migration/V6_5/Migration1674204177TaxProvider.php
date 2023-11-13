<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1674204177TaxProvider extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1674204177;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS`tax_provider` (
              `id`                      BINARY(16)          NOT NULL,
              `active`                  TINYINT(1) unsigned NOT NULL DEFAULT 1,
              `priority`                INT                 NOT NULL DEFAULT 1,
              `identifier`              VARCHAR(255)        NOT NULL,
              `availability_rule_id`    BINARY(16)          NULL,
              `app_id`                  BINARY(16)          NULL,
              `process_url`             VARCHAR(255)        NULL,
              `created_at`              DATETIME(3)         NOT NULL,
              `updated_at`              DATETIME(3)         NULL,
               PRIMARY KEY (`id`),
               CONSTRAINT `uniq.tax_provider.identifier`
                 UNIQUE (`identifier`),
               CONSTRAINT `fk.tax_provider.availability_rule_id` FOREIGN KEY (`availability_rule_id`)
                 REFERENCES `rule` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
               CONSTRAINT `fk.tax_provider.app_id` FOREIGN KEY (`app_id`)
                 REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
               INDEX (`availability_rule_id`, `active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `tax_provider_translation` (
              `tax_provider_id`     BINARY(16)                                  NOT NULL,
              `language_id`         BINARY(16)                                  NOT NULL,
              `name`                VARCHAR(255)    COLLATE utf8mb4_unicode_ci  NULL,
              `custom_fields`       JSON                                        NULL,
              `created_at`          DATETIME(3)                                 NOT NULL,
              `updated_at`          DATETIME(3)                                 NULL,
              PRIMARY KEY (`tax_provider_id`, `language_id`),
              CONSTRAINT `json.tax_provider_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.tax_provider_translation.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.tax_provider_translation.tax_provider_id` FOREIGN KEY (`tax_provider_id`)
                REFERENCES `tax_provider` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
