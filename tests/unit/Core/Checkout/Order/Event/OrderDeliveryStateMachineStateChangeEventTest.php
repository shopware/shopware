<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\Event;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Event\OrderDeliveryStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\Aware\OrderDeliveryAware;
use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventRegistry;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderDeliveryStateMachineStateChangeEvent::class)]
class OrderDeliveryStateMachineStateChangeEventTest extends TestCase
{
    public function testFlowsGetTheDeliveryAndWebhooksOnlyItsId(): void
    {
        $definition = (new BusinessEventCollector(
            new BusinessEventRegistry(),
            new EventDispatcher(),
            static::createStub(Connection::class)
        ))->define(OrderDeliveryStateMachineStateChangeEvent::class, 'state_enter.order_delivery.state.shipped');

        static::assertNotNull($definition);
        static::assertSame(
            [
                OrderAware::ORDER => (new EntityType(OrderDefinition::class))->toArray(),
                OrderDeliveryAware::ORDER_DELIVERY => [
                    ...(new EntityType(OrderDeliveryDefinition::class))->toArray(),
                    EventDataCollection::HIDDEN_FROM_WEBHOOK => true,
                ],
                OrderDeliveryAware::ORDER_DELIVERY_ID => (new ScalarValueType(ScalarValueType::TYPE_STRING))->toArray(),
            ],
            $definition->getData()
        );
        static::assertTrue($definition->getAware(OrderDeliveryAware::class));
        static::assertTrue($definition->getAware(ScalarValuesAware::class));
    }

    public function testExposesTheChangedDelivery(): void
    {
        $delivery = new OrderDeliveryEntity();
        $delivery->setId('order-delivery-id');

        $event = new OrderDeliveryStateMachineStateChangeEvent(
            'state_enter.order_delivery.state.shipped',
            new OrderEntity(),
            $delivery,
            Context::createDefaultContext()
        );

        static::assertSame($delivery, $event->getOrderDelivery());
        static::assertSame('order-delivery-id', $event->getOrderDeliveryId());
        static::assertSame([OrderDeliveryAware::ORDER_DELIVERY_ID => 'order-delivery-id'], $event->getValues());
        static::assertSame('state_enter.order_delivery.state.shipped', $event->getName());
    }
}
