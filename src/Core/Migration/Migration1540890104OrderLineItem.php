<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1540890104OrderLineItem extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1540890104;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `order_line_item` ADD COLUMN `payload` LONGTEXT NULL AFTER `type`;
        ');

        $connection->executeQuery('
            ALTER TABLE `order_line_item` ADD COLUMN `price_definition` LONGTEXT NULL NULL AFTER `type`;
        ');

        $connection->executeQuery('
            ALTER TABLE `order_line_item` ADD COLUMN `price` LONGTEXT NULL NULL AFTER `type`;
        ');

        $connection->executeQuery('
            ALTER TABLE `order_line_item` ADD COLUMN `stackable` TINYINT(1) NOT NULL DEFAULT 1 NULL AFTER `type`;
        ');

        $connection->executeQuery('
            ALTER TABLE `order_line_item` ADD COLUMN `removable` TINYINT(1) NOT NULL DEFAULT 1 NULL AFTER `type`;
        ');

        $connection->executeQuery('
            ALTER TABLE `order_line_item` ADD COLUMN `priority` INT(11) NOT NULL DEFAULT 100 NULL AFTER `type`;
        ');

        $connection->executeQuery('
            ALTER TABLE `order_line_item` ADD COLUMN `good` TINYINT(1) NOT NULL DEFAULT 1 NULL AFTER `type`;
        ');

        $connection->executeQuery('
            ALTER TABLE `order_line_item`
            ADD CONSTRAINT `payload_json_valid` CHECK(JSON_VALID(`payload`) OR `payload` IS NULL);
        ');

        $connection->executeQuery('
            ALTER TABLE `order_line_item`
            ADD CONSTRAINT `price_json_valid` CHECK(JSON_VALID(`price`) OR `price` IS NULL);
        ');

        $connection->executeQuery('
            ALTER TABLE `order_line_item`
            ADD CONSTRAINT `price_definition_json_valid` CHECK(JSON_VALID(`price_definition`) OR `price_definition` IS NULL);
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
