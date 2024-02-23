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
use Shopware\Core\Content\Flow\Events\BeforeLoadStorableFlowDataEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(OrderTransactionStorer::class)]
class OrderTransactionStorerTest extends TestCase
{
    private OrderTransactionStorer $storer;

    private MockObject&EntityRepository $repository;

    private MockObject&EventDispatcherInterface $dispatcher;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EntityRepository::class);
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->storer = new OrderTransactionStorer($this->repository, $this->dispatcher);
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
        $result = $this->createMock(EntitySearchResult::class);
        $result->expects(static::once())->method('get')->willReturn($entity);

        $this->repository->expects(static::once())->method('search')->willReturn($result);
        $res = $storable->getData('orderTransaction');

        static::assertEquals($res, $entity);
    }

    public function testLazyLoadNullEntity(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['orderTransactionId' => 'id'], []);
        $this->storer->restore($storable);
        $entity = null;
        $result = $this->createMock(EntitySearchResult::class);
        $result->expects(static::once())->method('get')->willReturn($entity);

        $this->repository->expects(static::once())->method('search')->willReturn($result);
        $res = $storable->getData('orderTransaction');

        static::assertEquals($res, $entity);
    }

    public function testLazyLoadNullId(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['orderTransactionId' => null], []);
        $this->storer->restore($storable);
        $customerGroup = $storable->getData('orderTransaction');

        static::assertNull($customerGroup);
    }

    public function testDispatchBeforeLoadStorableFlowDataEvent(): void
    {
        $this->dispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with(
                static::isInstanceOf(BeforeLoadStorableFlowDataEvent::class),
                'flow.storer.order_transaction.criteria.event'
            );

        $storable = new StorableFlow('name', Context::createDefaultContext(), ['orderTransactionId' => 'id'], []);
        $this->storer->restore($storable);
        $storable->getData('orderTransaction');
    }
}
