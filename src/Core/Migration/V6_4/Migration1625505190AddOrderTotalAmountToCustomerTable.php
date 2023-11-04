<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1625505190AddOrderTotalAmountToCustomerTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1625505190;
    }

    public function update(Connection $connection): void
    {
        $orderTotalAmountColumn = $connection->fetchOne(
            'SHOW COLUMNS FROM `customer` WHERE `Field` LIKE :column;',
            ['column' => 'order_total_amount']
        );

        if ($orderTotalAmountColumn !== false) {
            return;
        }

        $connection->executeStatement('
            ALTER TABLE `customer` ADD COLUMN order_total_amount DOUBLE DEFAULT 0 AFTER order_count;
        ');

        $connection->executeStatement('
            UPDATE `customer`

            INNER JOIN (
                SELECT `order_customer`.customer_id,
                    COUNT(`order`.id) as order_count,
                    SUM(`order`.amount_total) as order_total_amount,
                    MAX(`order`.order_date_time) as last_order_date

                FROM `order_customer`

                INNER JOIN `order`
                    ON `order`.id = `order_customer`.order_id
                    AND `order`.version_id = `order_customer`.order_version_id
                    AND `order`.version_id = :version

                INNER JOIN `state_machine_state`
                    ON `state_machine_state`.id = `order`.state_id
                    AND `state_machine_state`.technical_name = :state

                GROUP BY `order_customer`.customer_id
            ) as `meta_data`
            ON `meta_data`.customer_id = `customer`.id

            SET `customer`.order_count = `meta_data`.order_count,
                `customer`.last_order_date = `meta_data`.last_order_date,
                `customer`.order_total_amount = `meta_data`.order_total_amount
        ', [
            'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'state' => OrderStates::STATE_COMPLETED,
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
