<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;

class ProductAvailableStockCalculationService
{
    public const ORDER_LINE_ITEM_TYPE_PRODUCT = 'product';

    /**
     * @var Connection
     */
    protected $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Recalcualtes the available stock of a given set of products by inspecting all order deliveries related to
     * these products. For each product the set of related order deliveries with state 'open' is determined. The
     * delivered quantities of the respective product within those order deliveries are than subtracted from the
     * current stock of the product to calculate its available stock.
     */
    public function recalculate(array $productIds, Context $context): void
    {
        if (empty($productIds)) {
            return;
        }

        $sql = <<<SQL
UPDATE `product`
LEFT JOIN (
    SELECT
        `product`.`id` AS `product_id`,
        GREATEST((MIN(`product`.`stock`) - SUM(`order_delivery_position`.`quantity`)), 0) AS `available_stock`
    FROM `order_delivery`
    LEFT JOIN `order_delivery_position`
        ON `order_delivery_position`.`order_delivery_id` = `order_delivery`.`id`
    LEFT JOIN `order_line_item`
        ON `order_line_item`.`id` = `order_delivery_position`.`order_line_item_id`
    LEFT JOIN `product`
        ON LOWER(HEX(`product`.`id`)) = TRIM(BOTH '"' FROM JSON_EXTRACT(`order_line_item`.`payload`, '$.id'))
    WHERE
        `order_line_item`.`type` = :order_line_item_type
        AND `product`.`id` IN (:productIds)
        AND `order_delivery`.`state_id` = (
            # Select ID of the 'open' delivery state
            SELECT `state_machine_state`.`id`
            FROM `state_machine_state`
            LEFT JOIN `state_machine`
                ON `state_machine`.`id` = `state_machine_state`.`state_machine_id`
            WHERE
                `state_machine`.`technical_name` = :state_machine
                AND `state_machine_state`.`technical_name` = :delivery_state_open
        )
    GROUP BY `product`.`id`
) AS `calculated_available_stock`
    ON `calculated_available_stock`.`product_id` = `product`.`id`
SET 
    `product`.`available_stock` = IFNULL(`calculated_available_stock`.`available_stock`, `product`.`stock`)
WHERE 
    `product`.`id` IN (:productIds); 
SQL;

        $this->connection->executeQuery(
            $sql,
            [
                'order_line_item_type' => self::ORDER_LINE_ITEM_TYPE_PRODUCT,
                'state_machine' => OrderDeliveryStates::STATE_MACHINE,
                'delivery_state_open' => OrderDeliveryStates::STATE_OPEN,
                'productIds' => array_map([Uuid::class, 'fromHexToBytes'], $productIds),
            ],
            [
                'productIds' => Connection::PARAM_STR_ARRAY,
            ]
        );
    }
}
