<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1548316526RemoveOrderStates extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1548316526;
    }

    public function update(Connection $connection): void
    {
        $this->createTables($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropTables($connection);
        $this->dropColumns($connection);
    }

    private function createTables(Connection $connection): void
    {
        $sql = <<<SQL
ALTER TABLE `order`
    DROP FOREIGN KEY `fk.order.order_state_id`,
    MODIFY COLUMN `order_state_id` binary(16) NULL,
    ADD COLUMN `state_id` binary(16) NULL,
    ADD INDEX `idx.state_index` (`state_id`)
SQL;
        $connection->executeQuery($sql);

        $sql = <<<SQL
ALTER TABLE `order_delivery`
    DROP FOREIGN KEY `fk.order_delivery.order_state_id`,
    MODIFY COLUMN `order_state_id` binary(16) NULL,
    ADD COLUMN `state_id` binary(16) NULL,
    ADD INDEX `idx.state_index` (`state_id`)
SQL;
        $connection->executeQuery($sql);

        $sql = <<<SQL
ALTER TABLE `order_transaction`
    DROP FOREIGN KEY `fk.order_transaction.order_transaction_state_id`,
    MODIFY COLUMN `order_transaction_state_id` binary(16) NULL,
    ADD COLUMN `state_id` binary(16) NULL,
    ADD INDEX `idx.state_index` (`state_id`)
SQL;
        $connection->executeQuery($sql);
    }

    private function dropTables(Connection $connection): void
    {
        $connection->executeQuery('DROP TABLE `order_state_translation`');
        $connection->executeQuery('DROP TABLE `order_state`');
        $connection->executeQuery('DROP TABLE `order_transaction_state_translation`');
        $connection->executeQuery('DROP TABLE `order_transaction_state`');
    }

    private function dropColumns(Connection $connection): void
    {
        $sql = <<<SQL
ALTER TABLE `order`
  DROP COLUMN `order_state_id`,
  MODIFY COLUMN `state_id` binary(16) NOT NULL,
  ADD CONSTRAINT `fk.order.state_id` FOREIGN KEY (`state_id`) REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
SQL;
        $connection->executeQuery($sql);

        $sql = <<<SQL
ALTER TABLE `order_delivery`
  DROP COLUMN `order_state_id`,
  MODIFY COLUMN `state_id` binary(16) NOT NULL,
  ADD CONSTRAINT `fk.order_delivery.state_id` FOREIGN KEY (`state_id`) REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
SQL;
        $connection->executeQuery($sql);

        $sql = <<<SQL
ALTER TABLE `order_transaction`
  DROP COLUMN `order_transaction_state_id`,
  DROP COLUMN `order_transaction_state_version_id`,
  MODIFY COLUMN `state_id` binary(16) NOT NULL,
  ADD CONSTRAINT `fk.order_transaction.state_id` FOREIGN KEY  (`state_id`) REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
SQL;
        $connection->executeQuery($sql);
    }
}
