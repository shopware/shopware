<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Shopware\Core\Checkout\Order;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class PrimaryOrderDeliveryService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Updates the `primaryOrderDelivery` reference on all given orders. The selection strategy is adapted from how
     * order deliveries are selected in the administration in vanilla shopware: The highest shipping costs.
     * When using multiple actual order deliveries another strategy might be more suitable and can be injected here.
     *
     * @param String[] $orderIds
     * @return void
     * @throws Exception
     */
    public function recalculatePrimaryOrderDeliveries(array $orderIds): void
    {
        if (count($orderIds) === 0) {
            return;
        }

        $this->connection->executeStatement(
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
                SET `order`.`primary_order_delivery_id` = `primary_order_delivery`.`id`
                WHERE `order`.`id` IN (:orderIds)
                AND `order`.`version_id` = :liveVersion;',
            [
                'orderIds' => array_map('hex2bin', $orderIds),
                'liveVersion' => hex2bin(Defaults::LIVE_VERSION),
            ],
            [
                'orderIds' => ArrayParameterType::STRING,
            ]
        );
    }
}
