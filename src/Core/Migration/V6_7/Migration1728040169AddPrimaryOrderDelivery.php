<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1728040169AddPrimaryOrderDelivery extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1728040169;
    }

    public function update(Connection $connection): void
    {
        if (!$this->columnExists($connection, 'order', 'primary_order_delivery_id')) {
            $connection->executeStatement(
                'ALTER TABLE `order`
                ADD COLUMN `primary_order_delivery_id` BINARY(16) NULL DEFAULT NULL AFTER `language_id`,
                ADD UNIQUE INDEX `uidx.order.primary_order_delivery` (`id`, `version_id`, `primary_order_delivery_id`),
                ADD CONSTRAINT `fk.order.primary_order_delivery`
                    FOREIGN KEY (`primary_order_delivery_id`)
                    REFERENCES `order_delivery` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE;'
            );
            $connection->executeStatement(
                'UPDATE `order`
                -- Select a single order delivery with the highest shippingCosts.unitPrice as the primary order
                -- delivery for the order. This selection strategy is adapted from how order deliveries are selected
                -- in the administration. See /administration/src/module/sw-order/view/sw-order-detail-base/index.js
                LEFT JOIN (
                    SELECT
                        `order_id`,
                        `order_version_id`,
                        MAX(
                            CAST(JSON_UNQUOTE(
                                JSON_EXTRACT(`order_delivery`.`shipping_costs`, "$.unitPrice")
                            ) AS DECIMAL)
                        ) AS `unitPrice`
                    FROM `order_delivery`
                    INNER JOIN `order`
                        ON `order_delivery`.`order_id` = `order`.`id`
                        AND `order_delivery`.`order_version_id` = `order`.`version_id`
                    GROUP BY `order_id`, `order_version_id`
                ) `primary_order_delivery_shipping_cost`
                    ON `primary_order_delivery_shipping_cost`.`order_id` = `order`.`id`
                    AND `primary_order_delivery_shipping_cost`.`order_version_id` = `order`.`version_id`
                INNER JOIN `order_delivery` as `primary_order_delivery`
                    ON `primary_order_delivery`.`order_version_id` = `order`.`version_id`
                    AND `primary_order_delivery`.`id` = (
                        SELECT `id`
                        FROM `order_delivery`
                        WHERE `order_delivery`.`order_id` = `order`.`id`
                        AND `order_delivery`.`order_version_id` = `order`.`version_id`
                        AND CAST(JSON_UNQUOTE(JSON_EXTRACT(`order_delivery`.`shipping_costs`, "$.unitPrice")) AS DECIMAL) = `primary_order_delivery_shipping_cost`.`unitPrice`
                        -- Add LIMIT 1 here because this join would join multiple deliveries if they are tied for the
                        -- primary order delivery (i.e. multiple order delivery have the same highest shipping cost).
                        LIMIT 1
                    )
                SET `order`.`primary_order_delivery_id` = `primary_order_delivery`.`id`;'
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        if ($this->columnExists($connection, 'order', 'primary_order_delivery_id')) {
            $this->dropIndexIfExists($connection, 'order', 'uidx.order.primary_order_delivery');
            $this->dropForeignKeyIfExists($connection, 'order', 'fk.order.primary_order_delivery');
            $this->dropColumnIfExists($connection, 'order', 'primary_order_delivery_id');
        }
    }
}
