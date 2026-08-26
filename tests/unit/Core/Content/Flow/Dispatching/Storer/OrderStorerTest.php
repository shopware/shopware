<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\OrderStorer;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\OrderProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Log\Package;
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

        static::assertEmpty($storable->data());
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

    private function createStorer(OrderProvider $orderProvider): OrderStorer
    {
        return new OrderStorer(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            $orderProvider,
        );
    }
}
