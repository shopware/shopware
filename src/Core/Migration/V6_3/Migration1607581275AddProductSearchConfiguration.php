<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;

/**
 * @internal
 */
#[Package('core')]
class Migration1607581275AddProductSearchConfiguration extends MigrationStep
{
    use ImportTranslationsTrait;

    public function getCreationTimestamp(): int
    {
        return 1607581275;
    }

    public function update(Connection $connection): void
    {
        $this->createProductSearchConfigTable($connection);
        $this->createProductSearchConfigFieldTable($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function createProductSearchConfigTable(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `product_search_config` (
                `id`                    BINARY(16)        NOT NULL,
                `language_id`           BINARY(16)        NOT NULL,
                `and_logic`             TINYINT(1)        NOT NULL DEFAULT 1,
                `min_search_length`     SMALLINT          NOT NULL DEFAULT 2,
                `excluded_terms`        JSON              NULL,
                `created_at`            DATETIME(3)       NOT NULL,
                `updated_at`            DATETIME(3)       NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `json.product_search_config.excluded_terms` CHECK (JSON_VALID(`excluded_terms`)),
                CONSTRAINT `uniq.product_search_config.language_id` UNIQUE (`language_id`),
                CONSTRAINT `fk.product_search_config.language_id` FOREIGN KEY (`language_id`)
                    REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    private function createProductSearchConfigFieldTable(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `product_search_config_field` (
                `id`                            BINARY(16)                                  NOT NULL,
                `product_search_config_id`      BINARY(16)                                  NOT NULL,
                `custom_field_id`               BINARY(16)                                  NULL,
                `field`                         VARCHAR(255)                                NOT NULL,
                `tokenize`                      TINYINT(1)                                  NOT NULL    DEFAULT 0,
                `searchable`                    TINYINT(1)                                  NOT NULL    DEFAULT 0,
                `ranking`                       INT(11)                                     NOT NULL    DEFAULT 0,
                `created_at`                    DATETIME(3)                                 NOT NULL,
                `updated_at`                    DATETIME(3)                                 NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `uniq.search_config_field.field__config_id` UNIQUE (`field`, `product_search_config_id`),
                CONSTRAINT `fk.search_config_field.product_search_config_id` FOREIGN KEY (`product_search_config_id`)
                    REFERENCES `product_search_config` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.search_config_field.custom_field_id` FOREIGN KEY (`custom_field_id`)
                    REFERENCES `custom_field` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
