<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Event;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\Aware\OrderDeliveryAware;
use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('checkout')]
class OrderDeliveryStateMachineStateChangeEvent extends OrderStateMachineStateChangeEvent implements OrderDeliveryAware, ScalarValuesAware
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
        return parent::getAvailableData()
            ->add(OrderDeliveryAware::ORDER_DELIVERY, new EntityType(OrderDeliveryDefinition::class), [EventDataCollection::HIDDEN_FROM_WEBHOOK => true])
            ->add(OrderDeliveryAware::ORDER_DELIVERY_ID, new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getOrderDelivery(): OrderDeliveryEntity
    {
        return $this->orderDelivery;
    }

    public function getOrderDeliveryId(): string
    {
        return $this->orderDelivery->getId();
    }

    public function getValues(): array
    {
        return [OrderDeliveryAware::ORDER_DELIVERY_ID => $this->getOrderDeliveryId()];
    }
}
