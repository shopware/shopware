<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerAccountRecoverRequestEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Content\Flow\Dispatching\Aware\CustomerRecoveryAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\CustomerRecoveryStorer;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\CustomerRecoveryProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(CustomerRecoveryStorer::class)]
class CustomerRecoveryStorerTest extends TestCase
{
    private CustomerRecoveryStorer $storer;

    private CustomerRecoveryProvider&Stub $customerRecoveryProvider;

    protected function setUp(): void
    {
        $this->customerRecoveryProvider = static::createStub(CustomerRecoveryProvider::class);

        $this->storer = $this->createStorer($this->customerRecoveryProvider);
    }

    public function testStoreWithAware(): void
    {
        $event = static::createStub(CustomerAccountRecoverRequestEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(CustomerRecoveryAware::CUSTOMER_RECOVERY_ID, $stored);
    }

    public function testStoreWithNotAware(): void
    {
        $event = static::createStub(CustomerRegisterEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(CustomerRecoveryAware::CUSTOMER_RECOVERY_ID, $stored);
    }

    public function testRestoreHasStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['customerRecoveryId' => 'test_id']);

        $this->storer->restore($storable);

        static::assertArrayHasKey('customerRecovery', $storable->data());
    }

    public function testRestoreEmptyStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext());

        $this->storer->restore($storable);

        static::assertEmpty($storable->data());
    }

    public function testLazyLoadEntity(): void
    {
        $customerRecoveryProvider = $this->createMock(CustomerRecoveryProvider::class);
        $storer = $this->createStorer($customerRecoveryProvider);

        $storable = new StorableFlow('name', Context::createDefaultContext(), ['customerRecoveryId' => 'id']);
        $storer->restore($storable);
        $entity = new CustomerRecoveryEntity();
        $entity->setId('id');

        $customerRecoveryProvider->expects($this->once())->method('getData')->willReturn($entity);
        $res = $storable->getData('customerRecovery');

        static::assertSame($res, $entity);
    }

    public function testLazyLoadNullEntity(): void
    {
        $customerRecoveryProvider = $this->createMock(CustomerRecoveryProvider::class);
        $storer = $this->createStorer($customerRecoveryProvider);

        $storable = new StorableFlow('name', Context::createDefaultContext(), ['customerRecoveryId' => 'id']);
        $storer->restore($storable);

        $customerRecoveryProvider->expects($this->once())->method('getData')->willReturn(null);
        $res = $storable->getData('customerRecovery');

        static::assertNull($res);
    }

    public function testLazyLoadNullId(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['customerRecoveryId' => null], []);
        $this->storer->restore($storable);
        $customerGroup = $storable->getData('customerRecovery');

        static::assertNull($customerGroup);
    }

    private function createStorer(CustomerRecoveryProvider $customerRecoveryProvider): CustomerRecoveryStorer
    {
        return new CustomerRecoveryStorer(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            $customerRecoveryProvider,
        );
    }
}
