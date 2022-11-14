<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Event\OrderPaymentMethodChangedEvent;
use Shopware\Core\Content\Flow\Dispatching\Aware\OrderTransactionAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\OrderTransactionStorer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

/**
 * @package business-ops
 *
 * @internal
 *
 * @covers \Shopware\Core\Content\Flow\Dispatching\Storer\OrderTransactionStorer
 */
class OrderTransactionStorerTest extends TestCase
{
    private OrderTransactionStorer $storer;

    /**
     * @var MockObject|EntityRepository
     */
    private $repository;

    public function setUp(): void
    {
        $this->repository = $this->createMock(EntityRepository::class);
        $this->storer = new OrderTransactionStorer($this->repository);
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
        /** @var MockObject|StorableFlow $storable */
        $storable = $this->createMock(StorableFlow::class);

        $storable->expects(static::exactly(1))
            ->method('hasStore')
            ->willReturn(true);

        $storable->expects(static::exactly(1))
            ->method('getStore')
            ->willReturn('test_id');

        $storable->expects(static::exactly(1))
            ->method('lazy');

        $this->storer->restore($storable);
    }

    public function testRestore(): void
    {
        /** @var MockObject|StorableFlow $storable */
        $storable = $this->createMock(StorableFlow::class);

        $storable->expects(static::exactly(1))
            ->method('hasStore')
            ->willReturn(false);

        $storable->expects(static::never())
            ->method('getStore');

        $storable->expects(static::never())
            ->method('lazy');

        $this->storer->restore($storable);
    }

    public function testLoadEntity(): void
    {
        $entity = new OrderTransactionEntity();
        $result = $this->createMock(EntitySearchResult::class);
        $result->expects(static::once())->method('get')->willReturn($entity);

        $this->repository->expects(static::once())->method('search')->willReturn($result);
        $res = $this->storer->load(['3443', Context::createDefaultContext()]);

        static::assertEquals($res, $entity);
    }

    public function testLoadNullEntity(): void
    {
        $entity = null;
        $result = $this->createMock(EntitySearchResult::class);
        $result->expects(static::once())->method('get')->willReturn($entity);

        $this->repository->expects(static::once())->method('search')->willReturn($result);
        $res = $this->storer->load(['3443', Context::createDefaultContext()]);

        static::assertEquals($res, $entity);
    }
}
