<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Event;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\Aware\OrderDeliveryAware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('checkout')]
class OrderDeliveryStateMachineStateChangeEvent extends OrderStateMachineStateChangeEvent implements OrderDeliveryAware
{
    public function __construct(
        string $name,
        OrderEntity $order,
        private readonly OrderDeliveryEntity $orderDelivery,
        Context $context
    ) {
        parent::__construct($name, $order, $context);
    }

    public static function getAvailableData(): EventDataCollection
    {
        // @deprecated tag:v6.8.0 - Remove the options, the delivery is then part of the webhook payload and needs `order_delivery:read`
        $options = Feature::isActive('v6.8.0.0') ? [] : [EventDataCollection::HIDDEN_FROM_WEBHOOK => true];

        return parent::getAvailableData()
            ->add(OrderDeliveryAware::ORDER_DELIVERY, new EntityType(OrderDeliveryDefinition::class), $options);
    }

    public function getOrderDelivery(): OrderDeliveryEntity
    {
        return $this->orderDelivery;
    }

    public function getOrderDeliveryId(): string
    {
        return $this->orderDelivery->getId();
    }
}
