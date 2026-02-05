<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
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

    private MockObject&OrderProvider $orderProvider;

    protected function setUp(): void
    {
        $this->orderProvider = $this->createMock(OrderProvider::class);

        $this->storer = new OrderStorer(
            $this->createMock(EntityRepository::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->orderProvider
        );
    }

    public function testStoreWithAware(): void
    {
        $event = $this->createMock(CheckoutOrderPlacedEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(OrderAware::ORDER_ID, $stored);
    }

    public function testStoreWithNotAware(): void
    {
        $event = $this->createMock(CustomerRegisterEvent::class);
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
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['orderId' => 'id'], []);
        $this->storer->restore($storable);
        $entity = new OrderEntity();
        $entity->setId('id');

        $this->orderProvider->expects($this->once())->method('getData')->willReturn($entity);
        $res = $storable->getData('order');

        static::assertSame($res, $entity);
    }

    public function testLazyLoadNullEntity(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['orderId' => 'id'], []);
        $this->storer->restore($storable);

        $this->orderProvider->expects($this->once())->method('getData')->willReturn(null);
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
}
