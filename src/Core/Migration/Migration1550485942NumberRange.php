<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Struct\Uuid;

class Migration1550485942NumberRange extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1550485942;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
            CREATE TABLE `number_range` (
              `id` BINARY(16) NOT NULL,
              `entity_id` BINARY(16) NOT NULL,
              `name` VARCHAR(64) NOT NULL,
              `description` VARCHAR(255) NULL,
              `pattern` VARCHAR(255) NOT NULL,
              `start` INTEGER(8) NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              UNIQUE (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->executeQuery($sql);

        $sql = <<<SQL
            CREATE TABLE `number_range_sales_channel` (
              `number_range_id` BINARY(16) NOT NULL,
              `sales_channel_id` BINARY(16) NULL,
              UNIQUE (`number_range_id`, `sales_channel_id`),
              CONSTRAINT `fk.number_range_sales_channel.number_range_id`
                FOREIGN KEY (number_range_id) REFERENCES `number_range` (id) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.number_range_sales_channel.sales_channel_id`
                FOREIGN KEY (sales_channel_id) REFERENCES `sales_channel` (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->executeQuery($sql);

        $sql = <<<SQL
            CREATE TABLE `number_range_state` (
              `number_range_id` BINARY(16) NOT NULL,
              `last_value` INTEGER(8) NOT NULL,
              PRIMARY KEY (`number_range_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        // No Foreign Key here is intended. It should be possible to handle the state with another Persistence so
        // we can force MySQL to expect a Dependency here
        $connection->executeQuery($sql);

        $sql = <<<SQL
            CREATE TABLE `number_range_entity` (
              `id` BINARY(16) NOT NULL,
              `entity_name` VARCHAR(64) NOT NULL,
              `global` TINYINT(1) NOT NULL,
              PRIMARY KEY (`id`),
              INDEX `idx.entity_name` (`entity_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $connection->executeQuery($sql);

        $sql = <<<SQL
            ALTER TABLE `sales_channel` 
            ADD COLUMN `short_name` VARCHAR(45) NULL AFTER `navigation_version_id`;
 
SQL;
        $connection->executeQuery($sql);

        $sql = <<<SQL
            ALTER TABLE `product` 
            ADD COLUMN `product_number` VARCHAR(64) NULL AFTER `ean`;
 
SQL;
        $connection->executeQuery($sql);

        $sql = <<<SQL
            ALTER TABLE `order` 
            ADD COLUMN `order_number` VARCHAR(64) NULL AFTER `auto_increment`;
 
SQL;
        $connection->executeQuery($sql);

        $sql = <<<SQL
            ALTER TABLE `order_transaction` 
            ADD COLUMN `order_transaction_number` VARCHAR(64) NULL AFTER `order_id`;
 
SQL;
        $connection->executeQuery($sql);

        foreach (DEFAULTS::NUMBER_RANGE_ENTITIES as $entityName => $numberRangeEntity) {
            $entityId = Uuid::uuid4()->getBytes();
            $connection->insert('number_range_entity', ['id' => $entityId, 'entity_name' => $entityName, 'global' => $numberRangeEntity['global']]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
