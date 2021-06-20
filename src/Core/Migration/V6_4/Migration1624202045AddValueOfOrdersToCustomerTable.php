<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1624202045AddValueOfOrdersToCustomerTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1624202045;
    }

    public function update(Connection $connection): void
    {
        $valueOfOrdersColumn = $connection->fetchColumn(
            'SHOW COLUMNS FROM `customer` WHERE `Field` LIKE :column;',
            ['column' => 'order_total_amount']
        );

        if ($valueOfOrdersColumn !== false) {
            return;
        }

        $connection->executeUpdate('
            ALTER TABLE `customer` ADD COLUMN order_total_amount DOUBLE DEFAULT 0 AFTER order_count;
        ');

        $connection->executeStatement('
            UPDATE `customer`
            SET order_total_amount = (
                SELECT SUM(`order`.amount_total) as total_amount
                FROM `order`
                    INNER JOIN `order_customer` ON `order`.id = `order_customer`.order_id
                    INNER JOIN `state_machine_state` ON `state_machine_state`.id = `order`.state_id AND `state_machine_state`.technical_name <> :cancelled_state
                WHERE `order_customer`.customer_id = `customer`.id AND `order`.version_id = :version_id
            )
        ', [
            'cancelled_state' => OrderStates::STATE_CANCELLED,
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
