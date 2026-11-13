<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Event\OrderDeliveryStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\Aware\OrderDeliveryAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\OrderDeliveryStorer;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\OrderDeliveryProvider;
use Shopware\Core\Content\Shared\MailFlow\Event\MailFlowDataCriteriaEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(OrderDeliveryStorer::class)]
class OrderDeliveryStorerTest extends TestCase
{
    private EventDispatcher $dispatcher;

    private Container $container;

    private OrderDeliveryStorer $storer;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
        $this->container = new Container();

        $this->storer = new OrderDeliveryStorer(new OrderDeliveryProvider($this->dispatcher, $this->container));
    }

    public function testStoresTheDeliveryIdOfADeliveryAwareEvent(): void
    {
        $stored = $this->storer->store($this->createDeliveryEvent('delivery-id'), []);

        static::assertSame([OrderDeliveryAware::ORDER_DELIVERY_ID => 'delivery-id'], $stored);
    }

    public function testKeepsAnAlreadyStoredDeliveryId(): void
    {
        $stored = $this->storer->store(
            $this->createDeliveryEvent('delivery-id'),
            [OrderDeliveryAware::ORDER_DELIVERY_ID => 'stored-id']
        );

        static::assertSame([OrderDeliveryAware::ORDER_DELIVERY_ID => 'stored-id'], $stored);
    }

    public function testIgnoresEventsWithoutDelivery(): void
    {
        $stored = $this->storer->store(static::createStub(CustomerRegisterEvent::class), []);

        static::assertSame([], $stored);
    }

    public function testRestoresTheDeliveryWithTheMailFlowCriteria(): void
    {
        $delivery = new OrderDeliveryEntity();
        $delivery->setId('delivery-id');

        $repository = new StaticEntityRepository([new OrderDeliveryCollection([$delivery])]);
        $this->container->set(OrderDeliveryDefinition::ENTITY_NAME . '.repository', $repository);

        $this->dispatcher->addListener(
            'mail-flow.data.order_delivery.criteria.event',
            static fn (MailFlowDataCriteriaEvent $event) => $event->criteria->addAssociation('order')
        );

        $storable = new StorableFlow('state_enter.order_delivery.state.shipped', Context::createDefaultContext(), [
            OrderDeliveryAware::ORDER_DELIVERY_ID => 'delivery-id',
        ]);
        $this->storer->restore($storable);

        static::assertSame($delivery, $storable->getData(OrderDeliveryAware::ORDER_DELIVERY));

        $criteria = $repository->searchedCriteria[0];
        static::assertSame(['delivery-id'], $criteria->getIds());
        static::assertSame(
            ['positions', 'shippingMethod', 'stateMachineState', 'shippingOrderAddress', 'order'],
            array_keys($criteria->getAssociations())
        );
    }

    public function testRestoresNullWhenTheDeliveryIsGone(): void
    {
        $this->container->set(
            OrderDeliveryDefinition::ENTITY_NAME . '.repository',
            new StaticEntityRepository([new OrderDeliveryCollection()])
        );

        $storable = new StorableFlow('state_enter.order_delivery.state.shipped', Context::createDefaultContext(), [
            OrderDeliveryAware::ORDER_DELIVERY_ID => 'delivery-id',
        ]);
        $this->storer->restore($storable);

        static::assertNull($storable->getData(OrderDeliveryAware::ORDER_DELIVERY));
    }

    public function testRestoresNullForAStoredNullId(): void
    {
        $storable = new StorableFlow('state_enter.order_delivery.state.shipped', Context::createDefaultContext(), [
            OrderDeliveryAware::ORDER_DELIVERY_ID => null,
        ]);
        $this->storer->restore($storable);

        static::assertNull($storable->getData(OrderDeliveryAware::ORDER_DELIVERY));
    }

    public function testRestoresNothingWithoutStoredId(): void
    {
        $storable = new StorableFlow('state_enter.order.state.done', Context::createDefaultContext());
        $this->storer->restore($storable);

        static::assertSame([], $storable->data());
    }

    private function createDeliveryEvent(string $deliveryId): OrderDeliveryStateMachineStateChangeEvent
    {
        $delivery = new OrderDeliveryEntity();
        $delivery->setId($deliveryId);

        return new OrderDeliveryStateMachineStateChangeEvent(
            'state_enter.order_delivery.state.shipped',
            new OrderEntity(),
            $delivery,
            Context::createDefaultContext()
        );
    }
}
