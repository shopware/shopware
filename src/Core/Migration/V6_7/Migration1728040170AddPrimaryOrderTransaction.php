<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1728040170AddPrimaryOrderTransaction extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1728040170;
    }

    public function update(Connection $connection): void
    {
        // No foreign key set both from order -> (primary) order transaction on
        // purpose so the DAL can handle the circular reference. We have a similar situation with the order and order
        // address.
        if (!$this->columnExists($connection, 'order', 'primary_order_transaction_id')) {
            $connection->executeStatement(
                'ALTER TABLE `order`
                ADD COLUMN `primary_order_transaction_id` BINARY(16) NULL DEFAULT NULL,
                ADD COLUMN `primary_order_transaction_version_id` BINARY(16) NULL DEFAULT NULL,
                ADD UNIQUE INDEX `uidx.order.primary_order_transaction` (`id`, `version_id`, `primary_order_transaction_id`)'
            );
        }

        $updateLimit = 1000;

        do {
            $ids = $connection->fetchFirstColumn(
                'SELECT `id` FROM `order` WHERE `primary_order_transaction_id` IS NULL AND EXISTS (
                 SELECT 1
                 FROM `order_transaction`
                 WHERE `order_transaction`.`order_id` = `order`.`id`
                   AND `order_transaction`.`order_version_id` = `order`.`version_id`
             ) LIMIT :limit',
                ['limit' => $updateLimit],
                ['limit' => ParameterType::INTEGER]
            );

            if (empty($ids)) {
                break;
            }

            $connection->executeStatement(
                'UPDATE `order`
                INNER JOIN `order_transaction` as `primary_order_transaction`
                    ON `primary_order_transaction`.`order_id` = `order`.`id`
                    AND `primary_order_transaction`.`order_version_id` = `order`.`version_id`
                    AND `primary_order_transaction`.`id` = (
                        SELECT `id`
                        FROM `order_transaction`
                        WHERE `order_transaction`.`order_id` = `order`.`id`
                        AND `order_transaction`.`order_version_id` = `order`.`version_id`
                        ORDER BY `order_transaction`.`created_at` DESC
                        LIMIT 1
                    )
                SET `order`.`primary_order_transaction_id` = `primary_order_transaction`.`id`,
                    `order`.`primary_order_transaction_version_id` = `primary_order_transaction`.`order_version_id`
                WHERE `order`.`id` IN (:ids);',
                ['ids' => $ids],
                ['ids' => ArrayParameterType::BINARY]
            );
        } while (\count($ids) === $updateLimit);
    }
}
