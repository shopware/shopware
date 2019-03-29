<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

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
              `type_id` BINARY(16) NOT NULL,
              `name` VARCHAR(64) NOT NULL,
              `description` VARCHAR(255) NULL,
              `pattern` VARCHAR(255) NOT NULL,
              `start` INTEGER(8) NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              UNIQUE `uniq.name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->executeQuery($sql);

        $sql = <<<SQL
            CREATE TABLE `number_range_sales_channel` (
              `number_range_id` BINARY(16) NOT NULL,
              `sales_channel_id` BINARY(16) NULL,
              UNIQUE `uniq.numer_range_id__sales_channel_id` (`number_range_id`, `sales_channel_id`),
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
            CREATE TABLE `number_range_type` (
              `id` BINARY(16) NOT NULL,
              `type_name` VARCHAR(64) NOT NULL,
              `global` TINYINT(1) NOT NULL,
              PRIMARY KEY (`id`),
              INDEX `idx.type_name` (`type_name`)
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

        $typeProduct = Uuid::randomBytes();
        $typeOrder = Uuid::randomBytes();
        $typeCustomer = Uuid::randomBytes();

        $connection->insert('number_range_type', ['id' => $typeProduct, 'type_name' => 'product', 'global' => 1]);
        $connection->insert('number_range_type', ['id' => $typeOrder, 'type_name' => 'order', 'global' => 0]);
        $connection->insert('number_range_type', ['id' => $typeCustomer, 'type_name' => 'customer', 'global' => 0]);

        $connection->insert('number_range', ['id' => Uuid::randomBytes(), 'name' => 'Products', 'type_id' => $typeProduct, 'pattern' => '{n}', 'start' => 1, 'created_at' => (new \DateTime())->format(Defaults::DATE_FORMAT)]);
        $connection->insert('number_range', ['id' => Uuid::randomBytes(), 'name' => 'Orders', 'type_id' => $typeOrder, 'pattern' => '{n}', 'start' => 1, 'created_at' => (new \DateTime())->format(Defaults::DATE_FORMAT)]);
        $connection->insert('number_range', ['id' => Uuid::randomBytes(), 'name' => 'Customers', 'type_id' => $typeCustomer, 'pattern' => '{n}', 'start' => 1, 'created_at' => (new \DateTime())->format(Defaults::DATE_FORMAT)]);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
