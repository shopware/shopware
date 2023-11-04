<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536233540ProductSearchKeyword extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233540;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `product_search_keyword` (
                `id` BINARY(16) NOT NULL,
                `version_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `product_id` BINARY(16) NOT NULL,
                `product_version_id` BINARY(16) NOT NULL,
                `keyword` VARCHAR(255) NOT NULL,
                `ranking` DOUBLE NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`,`version_id`,`language_id`),
                KEY `idx.product_search_keyword.product_id` (`product_id`,`product_version_id`),
                KEY `idx.product_search_keyword.language_id` (`language_id`),
                CONSTRAINT `fk.product_search_keyword.product_id` FOREIGN KEY (`product_id`,`product_version_id`)
                  REFERENCES `product` (`id`,`version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.product_search_keyword.language_id` FOREIGN KEY (`language_id`)
                  REFERENCES `language` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
');

        $connection->executeStatement('
            CREATE TABLE `product_keyword_dictionary` (
                `id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `keyword` VARCHAR(500) NOT NULL,
                `reversed` VARCHAR(500) GENERATED ALWAYS AS (REVERSE(keyword)) STORED,
                PRIMARY KEY (`id`,`language_id`),
                KEY `idx.product_keyword_dictionary.language_id` (`language_id`),
                CONSTRAINT `fk.product_keyword_dictionary.language_id` FOREIGN KEY (`language_id`)
                  REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
