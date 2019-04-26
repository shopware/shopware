<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Content\Product\Stock\AvailableStockCalculatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\System\StateMachine\StateMachineEvents;

class ProductStockUpdater implements EventSubscriberInterface
{
    public const ORDER_LINE_ITEM_TYPE_PRODUCT = 'product';

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StateMachineEvents::STATE_MACHINE_HISTORY_WRITTEN_EVENT => [
                ['updateStockOfAffectedProducts', 500],
            ],
        ];
    }

    /**
     * Checks if newly written state order history entries represent a state transition
     * of order deliveries to state `shipped` and updates the stock values of all products,
     * which are related to those order deliveries by substracting the delivered quantity from
     * the current value.
     */
    public function updateStockOfAffectedProducts(EntityWrittenEvent $event): void
    {
        $sql = <<<SQL
UPDATE `product`
LEFT JOIN (
    SELECT
        `product`.`id` AS `product_id`,
        SUM(`order_delivery_position`.`quantity`) AS `shipped_stock`
    FROM `state_machine_history`
    LEFT JOIN `state_machine_state` as `state_machine_from_state`
        ON `state_machine_from_state`.`id` = `state_machine_history`.`from_state_id`
    LEFT JOIN `state_machine_state` as `state_machine_to_state`
        ON `state_machine_to_state`.`id` = `state_machine_history`.`to_state_id`
    LEFT JOIN `order_delivery` 
        ON LOWER(HEX(`order_delivery`.`id`)) = TRIM(BOTH '"' FROM JSON_EXTRACT(`state_machine_history`.`entity_id`, '$.id'))
    LEFT JOIN `order_delivery_position`
        ON `order_delivery_position`.`order_delivery_id` = `order_delivery`.`id`
    LEFT JOIN `order_line_item`
        ON `order_line_item`.`id` = `order_delivery_position`.`order_line_item_id`
    LEFT JOIN `product`
        ON LOWER(HEX(`product`.`id`)) = TRIM(BOTH '"' FROM JSON_EXTRACT(`order_line_item`.`payload`, '$.id'))
    WHERE
        `state_machine_history`.`id` IN (:stateMachineHistoryIds)
        AND `state_machine_history`.`entity_name` = :order_delivery_entity_name
        AND `state_machine_to_state`.`technical_name` = :delivery_state_shipped
        AND `state_machine_from_state`.`id` != `state_machine_to_state`.`id`
        AND `order_line_item`.`type` = :order_line_item_type
    GROUP BY `product`.`id`
) AS `calculated_shipped_stock` ON `calculated_shipped_stock`.`product_id` = `product`.`id`
SET 
    `product`.`stock` = `product`.`stock` - `calculated_shipped_stock`.`shipped_stock`
WHERE 
    `calculated_shipped_stock`.`product_id` IS NOT NULL;
SQL;

        $this->connection->executeQuery(
            $sql,
            [
                'order_line_item_type' => self::ORDER_LINE_ITEM_TYPE_PRODUCT,
                'order_delivery_entity_name' => OrderDeliveryDefinition::getEntityName(),
                'delivery_state_shipped' => OrderDeliveryStates::STATE_SHIPPED,
                'stateMachineHistoryIds' => array_map([Uuid::class, 'fromHexToBytes'], $event->getIds()),
            ],
            [
                'stateMachineHistoryIds' => Connection::PARAM_STR_ARRAY,
            ]
        );
    }
}
