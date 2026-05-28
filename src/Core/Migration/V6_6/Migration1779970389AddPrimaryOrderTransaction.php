<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1779970389AddPrimaryOrderTransaction extends MigrationStep
{
    private const UPDATE_LIMIT = 1000;

    public function getCreationTimestamp(): int
    {
        return 1779970389;
    }

    public function update(Connection $connection): void
    {
        // No FK from order to order_transaction here: the relationship is circular and the DAL owns consistency.
        $this->addColumn($connection, 'order', 'primary_order_transaction_id', 'BINARY(16)');
        $this->addColumn($connection, 'order', 'primary_order_transaction_version_id', 'BINARY(16)');

        if (!$this->indexExists($connection, 'order', 'uidx.order.primary_order_transaction')) {
            $connection->executeStatement(
                'ALTER TABLE `order`
                 ADD UNIQUE INDEX `uidx.order.primary_order_transaction`
                    (`id`, `version_id`, `primary_order_transaction_id`)'
            );
        }

        do {
            $ids = $connection->fetchFirstColumn(
                'SELECT `order`.`id`
                 FROM `order`
                 WHERE `order`.`primary_order_transaction_id` IS NULL
                   AND EXISTS (
                       SELECT 1
                       FROM `order_transaction`
                       WHERE `order_transaction`.`order_id` = `order`.`id`
                         AND `order_transaction`.`order_version_id` = `order`.`version_id`
                   )
                 LIMIT :limit',
                ['limit' => self::UPDATE_LIMIT],
                ['limit' => ParameterType::INTEGER]
            );

            if ($ids === []) {
                break;
            }

            $connection->executeStatement(
                'UPDATE `order` AS `o`
                 INNER JOIN (
                     SELECT `order_id`, `order_version_id`, `id`, `version_id`
                     FROM (
                         SELECT
                             `order_id`,
                             `order_version_id`,
                             `id`,
                             `version_id`,
                             ROW_NUMBER() OVER (
                                 PARTITION BY `order_id`, `order_version_id`
                                 ORDER BY `created_at` DESC, `id` ASC
                             ) AS `rn`
                         FROM `order_transaction`
                         WHERE `order_id` IN (:ids)
                     ) AS `ranked`
                     WHERE `ranked`.`rn` = 1
                 ) AS `primary_transaction`
                     ON `primary_transaction`.`order_id` = `o`.`id`
                     AND `primary_transaction`.`order_version_id` = `o`.`version_id`
                 SET `o`.`primary_order_transaction_id` = `primary_transaction`.`id`,
                     `o`.`primary_order_transaction_version_id` = `primary_transaction`.`version_id`
                 WHERE `o`.`id` IN (:ids)
                   AND `o`.`primary_order_transaction_id` IS NULL',
                ['ids' => $ids],
                ['ids' => ArrayParameterType::BINARY]
            );
        } while (\count($ids) === self::UPDATE_LIMIT);
    }
}
