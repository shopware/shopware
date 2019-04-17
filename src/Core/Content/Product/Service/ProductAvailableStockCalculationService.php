<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductAvailableStockCalculationService
{
    /**
     * @var Connection
     */
    protected $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function recalculate(array $productIds, Context $context): void
    {
        if (empty($productIds)) {
            return;
        }

        // TODO: Add version join conditions

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
        `order_line_item`.`type` = 'product'
        AND `product`.`id` IN (:ids)
        AND `order_delivery`.`state_id` = (
            # Select ID of the 'open' delivery state
            SELECT `state_machine_state`.`id`
            FROM `state_machine_state`
            LEFT JOIN `state_machine`
                ON `state_machine`.`id` = `state_machine_state`.`state_machine_id`
            WHERE
                `state_machine`.`technical_name` = 'order_delivery.state'
                AND `state_machine_state`.`technical_name` = 'open'
        )
    GROUP BY `product`.`id`
) AS `calculated_available_stock`
    ON `calculated_available_stock`.`product_id` = `product`.`id`
SET `product`.`available_stock` = IFNULL(`calculated_available_stock`.`available_stock`, `product`.`stock`)
WHERE `product`.`id` IN (:ids)
SQL;

        $ids = array_map([Uuid::class, 'fromHexToBytes'], $productIds);
        $this->connection->executeUpdate($sql, ['ids' => $ids], ['ids' => Connection::PARAM_STR_ARRAY]);
    }
}
