<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\OrderStorer;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\OrderProvider;
use Shopware\Core\Content\Shared\MailFlow\Event\MailFlowDataCriteriaEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(OrderStorer::class)]
class OrderStorerTest extends TestCase
{
    private OrderStorer $storer;

    private Stub&OrderProvider $orderProvider;

    protected function setUp(): void
    {
        $this->orderProvider = static::createStub(OrderProvider::class);

        $this->storer = $this->createStorer($this->orderProvider);
    }

    public function testStoreWithAware(): void
    {
        $event = static::createStub(CheckoutOrderPlacedEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(OrderAware::ORDER_ID, $stored);
    }

    public function testStoreWithNotAware(): void
    {
        $event = static::createStub(CustomerRegisterEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(OrderAware::ORDER_ID, $stored);
    }

    public function testRestoreHasStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['orderId' => 'test_id']);

        $this->storer->restore($storable);

        static::assertArrayHasKey('order', $storable->data());
    }

    public function testRestoreEmptyStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext());

        $this->storer->restore($storable);

        static::assertCount(0, $storable->data());
    }

    public function testLazyLoadEntity(): void
    {
        $orderProvider = $this->createMock(OrderProvider::class);
        $storer = $this->createStorer($orderProvider);

        $storable = new StorableFlow('name', Context::createDefaultContext(), ['orderId' => 'id'], []);
        $storer->restore($storable);
        $entity = new OrderEntity();
        $entity->setId('id');

        $orderProvider->expects($this->once())->method('getData')->willReturn($entity);
        $res = $storable->getData('order');

        static::assertSame($res, $entity);
    }

    public function testLazyLoadNullEntity(): void
    {
        $orderProvider = $this->createMock(OrderProvider::class);
        $storer = $this->createStorer($orderProvider);

        $storable = new StorableFlow('name', Context::createDefaultContext(), ['orderId' => 'id'], []);
        $storer->restore($storable);

        $orderProvider->expects($this->once())->method('getData')->willReturn(null);
        $res = $storable->getData('order');

        static::assertNull($res);
    }

    public function testLazyLoadNullId(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['orderId' => null], []);
        $this->storer->restore($storable);
        $customerGroup = $storable->getData('order');

        static::assertNull($customerGroup);
    }

    public function testLazyLoadedOrderHasAssociationsAddedThroughTheMailFlowCriteriaEvent(): void
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $orderRepository = new StaticEntityRepository([new OrderCollection([$order])]);
        $storer = $this->createStorerWithDeliveryPositionsSubscriber($orderRepository);

        $storable = new StorableFlow('name', Context::createDefaultContext(), [OrderAware::ORDER_ID => $order->getId()]);
        $storer->restore($storable);

        static::assertSame($order, $storable->getData(OrderAware::ORDER));
        static::assertTrue($orderRepository->searchedCriteria[0]->getAssociation('deliveries.positions')->hasAssociation('orderLineItem'));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testLegacyLazyLoadedOrderHasAssociationsAddedThroughTheMailFlowCriteriaEvent(): void
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $orderRepository = new StaticEntityRepository([new OrderCollection([$order])]);
        $storer = $this->createStorerWithDeliveryPositionsSubscriber($orderRepository);

        $storable = new StorableFlow('name', Context::createDefaultContext(), [OrderAware::ORDER_ID => $order->getId()]);
        $storer->restore($storable);

        static::assertSame($order, $storable->getData(OrderAware::ORDER));
        static::assertTrue($orderRepository->searchedCriteria[0]->getAssociation('deliveries.positions')->hasAssociation('orderLineItem'));
    }

    /**
     * @param StaticEntityRepository<OrderCollection> $orderRepository
     */
    private function createStorerWithDeliveryPositionsSubscriber(StaticEntityRepository $orderRepository): OrderStorer
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('mail-flow.data.order.criteria.event', static function (MailFlowDataCriteriaEvent $event): void {
            $event->criteria->addAssociation('deliveries.positions.orderLineItem');
        });

        $container = new Container();
        $container->set('order.repository', $orderRepository);

        return new OrderStorer($orderRepository, $dispatcher, new OrderProvider($dispatcher, $container));
    }

    private function createStorer(OrderProvider $orderProvider): OrderStorer
    {
        return new OrderStorer(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            $orderProvider,
        );
    }
}
