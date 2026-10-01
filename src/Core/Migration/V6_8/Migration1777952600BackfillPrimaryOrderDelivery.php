<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1777952600BackfillPrimaryOrderDelivery extends MigrationStep
{
    private const UPDATE_LIMIT = 1000;

    public function getCreationTimestamp(): int
    {
        return 1777952600;
    }

    public function update(Connection $connection): void
    {
        // Re-runs the backfill from \Shopware\Core\Migration\V6_7\Migration1728040169AddPrimaryOrderDelivery
        // to cover orders created during the 6.7 lifecycle via direct DAL writes that did not set
        // primary_order_delivery_id.
        do {
            $ids = $connection->fetchFirstColumn(
                'SELECT `order`.`id`
                 FROM `order`
                 WHERE `order`.`primary_order_delivery_id` IS NULL
                   AND EXISTS (
                       SELECT 1
                       FROM `order_delivery`
                       WHERE `order_delivery`.`order_id` = `order`.`id`
                         AND `order_delivery`.`order_version_id` = `order`.`version_id`
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
                                 ORDER BY CAST(JSON_UNQUOTE(JSON_EXTRACT(`shipping_costs`, \'$.unitPrice\')) AS DECIMAL(20, 6)) DESC, `id` ASC
                             ) AS `rn`
                         FROM `order_delivery`
                         WHERE `order_id` IN (:ids)
                     ) AS `ranked`
                     WHERE `ranked`.`rn` = 1
                 ) AS `primary_delivery`
                     ON `primary_delivery`.`order_id` = `o`.`id`
                     AND `primary_delivery`.`order_version_id` = `o`.`version_id`
                 SET `o`.`primary_order_delivery_id` = `primary_delivery`.`id`,
                     `o`.`primary_order_delivery_version_id` = `primary_delivery`.`version_id`
                 WHERE `o`.`id` IN (:ids)
                   AND `o`.`primary_order_delivery_id` IS NULL',
                ['ids' => $ids],
                ['ids' => ArrayParameterType::BINARY]
            );
        } while (\count($ids) === self::UPDATE_LIMIT);
    }
}
