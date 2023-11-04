<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1536233510DocumentConfiguration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233510;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<'SQL'
    CREATE TABLE `document_base_config` (
      `id` BINARY(16) NOT NULL,
      `name` VARCHAR(64) NOT NULL,
      `filename_prefix` VARCHAR(64) DEFAULT '',
      `filename_suffix` VARCHAR(64) DEFAULT '',
      `document_number` VARCHAR(64) DEFAULT '',
      `global` TINYINT(1) DEFAULT 0,
      `document_type_id` BINARY(16) NOT NULL,
      `logo_id` BINARY(16) NULL,
      `config` JSON NULL,
      `created_at` DATETIME(3) NOT NULL,
      `updated_at` DATETIME(3) NULL,
      PRIMARY KEY (`id`),
      KEY `idx.document_base_config.type_id` (`document_type_id`),
      CONSTRAINT `json.config` CHECK (JSON_VALID(`config`)),
      CONSTRAINT `fk.document_base_config.type_id` FOREIGN KEY (`document_type_id`) REFERENCES `document_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
      CONSTRAINT `fk.document_base_config.logo_id` FOREIGN KEY (`logo_id`) REFERENCES `media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        $connection->executeStatement($sql);

        $sql = <<<'SQL'
    CREATE TABLE `document_base_config_sales_channel` (
      `id` BINARY(16) NOT NULL,
      `document_base_config_id` BINARY(16) NOT NULL,
      `document_type_id` BINARY(16) NOT NULL,
      `sales_channel_id` BINARY(16) NULL,
      `created_at` DATETIME(3) NOT NULL,
      `updated_at` DATETIME(3) NULL,
      PRIMARY KEY (`id`),
      UNIQUE `uniq.document_base_configuration_id__sales_channel_id` (`document_type_id`, `sales_channel_id`),
      CONSTRAINT `fk.document_base_config_sales_channel.document_base_config_id`
      FOREIGN KEY (document_base_config_id) REFERENCES `document_base_config` (id) ON DELETE CASCADE ON UPDATE CASCADE,
      CONSTRAINT `fk.document_base_config_sales_channel.document_type_id`
      FOREIGN KEY (document_type_id) REFERENCES `document_type` (id) ON DELETE CASCADE ON UPDATE CASCADE,
      CONSTRAINT `fk.document_base_config_sales_channel.sales_channel_id`
      FOREIGN KEY (sales_channel_id) REFERENCES `sales_channel` (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // nth
    }
}
