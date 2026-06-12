<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Event\OrderPaymentMethodChangedEvent;
use Shopware\Core\Content\Flow\Dispatching\Aware\OrderTransactionAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\OrderTransactionStorer;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\OrderTransactionProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(OrderTransactionStorer::class)]
class OrderTransactionStorerTest extends TestCase
{
    private OrderTransactionStorer $storer;

    private MockObject&OrderTransactionProvider $orderTransactionProvider;

    protected function setUp(): void
    {
        $this->orderTransactionProvider = $this->createMock(OrderTransactionProvider::class);

        $this->storer = new OrderTransactionStorer(
            $this->createMock(EntityRepository::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->orderTransactionProvider,
        );
    }

    public function testStoreWithAware(): void
    {
        $event = $this->createMock(OrderPaymentMethodChangedEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(OrderTransactionAware::ORDER_TRANSACTION_ID, $stored);
    }

    public function testStoreWithNotAware(): void
    {
        $event = $this->createMock(CustomerRegisterEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(OrderTransactionAware::ORDER_TRANSACTION_ID, $stored);
    }

    public function testRestoreHasStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['orderTransactionId' => 'test_id']);

        $this->storer->restore($storable);

        static::assertArrayHasKey('orderTransaction', $storable->data());
    }

    public function testRestoreEmptyStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext());

        $this->storer->restore($storable);

        static::assertEmpty($storable->data());
    }

    public function testLazyLoadEntity(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['orderTransactionId' => 'id'], []);
        $this->storer->restore($storable);
        $entity = new OrderTransactionEntity();
        $entity->setId('id');

        $this->orderTransactionProvider->expects($this->once())->method('getData')->willReturn($entity);
        $res = $storable->getData('orderTransaction');

        static::assertSame($res, $entity);
    }

    public function testLazyLoadNullEntity(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['orderTransactionId' => 'id'], []);
        $this->storer->restore($storable);

        $this->orderTransactionProvider->expects($this->once())->method('getData')->willReturn(null);
        $res = $storable->getData('orderTransaction');

        static::assertNull($res);
    }

    public function testLazyLoadNullId(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['orderTransactionId' => null], []);
        $this->storer->restore($storable);
        $customerGroup = $storable->getData('orderTransaction');

        static::assertNull($customerGroup);
    }
}
