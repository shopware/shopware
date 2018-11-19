<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1542612587RemoveOrderStates extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1542612587;
    }

    public function update(Connection $connection): void
    {
        $this->createTables($connection);
        $this->migrateData($connection);
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
    DROP FOREIGN KEY `fk_order.order_state_id`,
    MODIFY COLUMN `order_state_id` binary(16) NULL,
    MODIFY COLUMN `order_state_version_id` binary(16) NULL,
    ADD COLUMN `state_id` binary(16) NULL,
    ADD INDEX `state_index` (`state_id`),
    ADD FOREIGN KEY `fk_order.state_id` (`state_id`) REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
SQL;
        $connection->executeQuery($sql);

        $sql = <<<SQL
ALTER TABLE `order_delivery`
    DROP FOREIGN KEY `fk_order_delivery.order_state_id`,
    MODIFY COLUMN `order_state_id` binary(16) NULL,
    MODIFY COLUMN `order_state_version_id` binary(16) NULL,
    ADD COLUMN `state_id` binary(16) NULL,
    ADD INDEX `state_index` (`state_id`),
    ADD FOREIGN KEY `fk_order_delivery.state_id` (`state_id`) REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
SQL;
        $connection->executeQuery($sql);

        $sql = <<<SQL
ALTER TABLE `order_transaction`
    DROP FOREIGN KEY `fk_order_transaction.order_transaction_state_id`,
    MODIFY COLUMN `order_transaction_state_id` binary(16) NULL,
    MODIFY COLUMN `order_transaction_state_version_id` binary(16) NULL,
    ADD COLUMN `state_id` binary(16) NULL,
    ADD INDEX `state_index` (`state_id`),
    ADD FOREIGN KEY `fk_order_transaction.state_id` (`state_id`) REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
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
  DROP COLUMN `order_state_version_id`,
  MODIFY COLUMN `state_id` binary(16) NOT NULL
SQL;
        $connection->executeQuery($sql);

        $sql = <<<SQL
ALTER TABLE `order_delivery`
  DROP COLUMN `order_state_id`,
  DROP COLUMN `order_state_version_id`,
  MODIFY COLUMN `state_id` binary(16) NOT NULL
SQL;
        $connection->executeQuery($sql);

        $sql = <<<SQL
ALTER TABLE `order_transaction`
  DROP COLUMN `order_transaction_state_id`,
  DROP COLUMN `order_transaction_state_version_id`,
  MODIFY COLUMN `state_id` binary(16) NOT NULL
SQL;
        $connection->executeQuery($sql);
    }

    private function migrateData(Connection $connection)
    {
        $mapping = [
        ];

        $sql = <<<SQL
SELECT `order`.`id`, `order`.`order_state_id`, `order_state_translation`.`description` 
FROM `order`
INNER JOIN `order_state_translation` ON `order_state_translation`.`order_state_id` = `order`.`order_state_id`
SQL;

        $connection->executeQuery($sql);
    }
}
