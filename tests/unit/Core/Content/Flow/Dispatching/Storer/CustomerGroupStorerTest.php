<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerGroupRegistrationDeclined;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\CustomerGroupStorer;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\CustomerGroupProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Event\CustomerGroupAware;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(CustomerGroupStorer::class)]
class CustomerGroupStorerTest extends TestCase
{
    private CustomerGroupStorer $storer;

    private MockObject&CustomerGroupProvider $customerGroupProvider;

    protected function setUp(): void
    {
        $this->customerGroupProvider = $this->createMock(CustomerGroupProvider::class);

        $this->storer = new CustomerGroupStorer(
            $this->createMock(EntityRepository::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->customerGroupProvider
        );
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
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['customerGroupId' => 'test_id']);

        $this->storer->restore($storable);
        static::assertArrayHasKey('customerGroup', $storable->data());
    }

    public function testRestoreEmptyStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext());

        $this->storer->restore($storable);
        static::assertEmpty($storable->data());
    }

    public function testLazyLoadEntity(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['customerGroupId' => 'id'], []);

        $this->storer->restore($storable);
        $entity = new CustomerGroupEntity();
        $entity->setId('id');

        $this->customerGroupProvider->expects($this->once())->method('getData')->willReturn($entity);
        $customerGroup = $storable->getData('customerGroup');

        static::assertSame($customerGroup, $entity);
    }

    public function testLazyLoadNullEntity(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['customerGroupId' => 'id'], []);
        $this->storer->restore($storable);

        $this->customerGroupProvider->expects($this->once())->method('getData')->willReturn(null);
        $customerGroup = $storable->getData('customerGroup');

        static::assertNull($customerGroup);
    }

    public function testLazyLoadNullId(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['customerGroupId' => null], []);
        $this->storer->restore($storable);
        $customerGroup = $storable->getData('customerGroup');

        static::assertNull($customerGroup);
    }
}
