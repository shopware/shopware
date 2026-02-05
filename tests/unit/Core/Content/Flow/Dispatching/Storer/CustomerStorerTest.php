<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\CustomerStorer;
use Shopware\Core\Content\Flow\Exception\CustomerDeletedException;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\CustomerProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\User\Recovery\UserRecoveryRequestEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(CustomerStorer::class)]
class CustomerStorerTest extends TestCase
{
    private CustomerStorer $storer;

    private MockObject&CustomerProvider $customerProvider;

    protected function setUp(): void
    {
        $this->customerProvider = $this->createMock(CustomerProvider::class);

        $this->storer = new CustomerStorer(
            $this->createMock(EntityRepository::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->customerProvider,
        );
    }

    public function testStoreWithAware(): void
    {
        $event = $this->createMock(CustomerRegisterEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(CustomerAware::CUSTOMER_ID, $stored);
    }

    public function testStoreWillCatchCustomerDeleteException(): void
    {
        $event = $this->createMock(OrderStateMachineStateChangeEvent::class);
        $event->method('getCustomerId')->willThrowException(new CustomerDeletedException('id'));

        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(CustomerAware::CUSTOMER_ID, $stored);
    }

    public function testStoreWithNotAware(): void
    {
        $event = $this->createMock(UserRecoveryRequestEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(CustomerAware::CUSTOMER_ID, $stored);
    }

    public function testRestoreHasStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['customerId' => 'test_id']);

        $this->storer->restore($storable);

        static::assertArrayHasKey('customer', $storable->data());
    }

    public function testRestoreEmptyStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext());

        $this->storer->restore($storable);

        static::assertEmpty($storable->data());
    }

    public function testLazyLoadEntity(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['customerId' => 'id'], []);
        $this->storer->restore($storable);
        $entity = new CustomerEntity();
        $entity->setId('id');

        $this->customerProvider->expects($this->once())->method('getData')->willReturn($entity);
        $res = $storable->getData('customer');

        static::assertSame($res, $entity);
    }

    public function testLazyLoadNullEntity(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['customerId' => 'id'], []);
        $this->storer->restore($storable);

        $this->customerProvider->expects($this->once())->method('getData')->willReturn(null);
        $res = $storable->getData('customer');

        static::assertNull($res);
    }

    public function testLazyLoadNullId(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['customerId' => null], []);
        $this->storer->restore($storable);
        $customerGroup = $storable->getData('customer');

        static::assertNull($customerGroup);
    }
}
