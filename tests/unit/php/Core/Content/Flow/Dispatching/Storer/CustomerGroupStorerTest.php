<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerGroupRegistrationDeclined;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\CustomerGroupStorer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\CustomerGroupAware;

/**
 * @package business-ops
 *
 * @internal
 *
 * @covers \Shopware\Core\Content\Flow\Dispatching\Storer\CustomerGroupStorer
 */
class CustomerGroupStorerTest extends TestCase
{
    private CustomerGroupStorer $storer;

    private MockObject&EntityRepository $repository;

    public function setUp(): void
    {
        $this->repository = $this->createMock(EntityRepository::class);
        $this->storer = new CustomerGroupStorer($this->repository);
    }

    public function testStoreWithAware(): void
    {
        $event = $this->createMock(CustomerGroupRegistrationDeclined::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(CustomerGroupAware::CUSTOMER_GROUP_ID, $stored);
    }

    public function testStoreWithNotAware(): void
    {
        $event = $this->createMock(CustomerRegisterEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(CustomerGroupAware::CUSTOMER_GROUP_ID, $stored);
    }

    public function testRestoreHasStored(): void
    {
        /** @var MockObject&StorableFlow $storable */
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

    public function testRestoreEmptyStored(): void
    {
        /** @var MockObject&StorableFlow $storable */
        $storable = $this->createMock(StorableFlow::class);

        $storable->expects(static::exactly(1))
            ->method('hasStore')
            ->willReturn(false);

        $storable->expects(static::never())
            ->method('getStore');

        $storable->expects(static::never())
            ->method('setData');

        $this->storer->restore($storable);
    }

    public function testLoadEntity(): void
    {
        $entity = new CustomerGroupEntity();
        $result = $this->createMock(EntitySearchResult::class);
        $result->expects(static::once())->method('get')->willReturn($entity);

        $this->repository->expects(static::once())->method('search')->willReturn($result);
        $customerGroup = $this->storer->load(['3443', Context::createDefaultContext()]);

        static::assertEquals($customerGroup, $entity);
    }

    public function testLoadNullEntity(): void
    {
        $entity = null;
        $result = $this->createMock(EntitySearchResult::class);
        $result->expects(static::once())->method('get')->willReturn($entity);

        $this->repository->expects(static::once())->method('search')->willReturn($result);
        $customerGroup = $this->storer->load(['3443', Context::createDefaultContext()]);

        static::assertEquals($customerGroup, $entity);
    }
}
