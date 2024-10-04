<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('core')]
class PrimaryOrderDeliverySubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'triggerOrderDeliveryChangeSet',
            OrderEvents::ORDER_DELIVERY_WRITTEN_EVENT => 'setPrimaryOrderDelivery',
            OrderEvents::ORDER_WRITTEN_EVENT => 'setPrimaryOrderDelivery',
        ];
    }

    public function triggerOrderDeliveryChangeSet(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if (!$command instanceof DeleteCommand) {
                continue;
            }

            if ($command->getEntityName() !== OrderDeliveryDefinition::ENTITY_NAME) {
                continue;
            }

            $command->requestChangeSet();
        }
    }

    public function setPrimaryOrderDelivery(EntityWrittenEvent $event): void
    {
        $orderIds = [];
        foreach ($event->getPayloads() as $payload) {
            if (!isset($payload['orderId'])) {
                continue;
            }

            $orderIds[] = $payload['orderId'];
        }

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
