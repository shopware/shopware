<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\UserStorer;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\UserRecoveryProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Event\UserAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Shopware\Core\System\User\Recovery\UserRecoveryRequestEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(UserStorer::class)]
class UserStorerTest extends TestCase
{
    private UserStorer $storer;

    private Stub&UserRecoveryProvider $userRecoveryProvider;

    protected function setUp(): void
    {
        $this->userRecoveryProvider = static::createStub(UserRecoveryProvider::class);

        $this->storer = $this->createStorer($this->userRecoveryProvider);
    }

    public function testStoreWithAware(): void
    {
        $event = static::createStub(UserRecoveryRequestEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(UserAware::USER_RECOVERY_ID, $stored);
    }

    public function testStore(): void
    {
        $event = static::createStub(CustomerRegisterEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(UserAware::USER_RECOVERY_ID, $stored);
    }

    public function testRestoreHasStored(): void
    {
        $this->userRecoveryProvider->method('getData')->willReturn(new UserRecoveryEntity());

        $storable = new StorableFlow('name', Context::createDefaultContext(), ['userRecoveryId' => 'test_id']);

        $this->storer->restore($storable);

        static::assertArrayHasKey('userRecovery', $storable->data());
    }

    public function testRestoreEmptyStored(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext());

        $this->storer->restore($storable);

        static::assertEmpty($storable->data());
    }

    public function testLazyLoadEntity(): void
    {
        $userRecoveryProvider = $this->createMock(UserRecoveryProvider::class);
        $storer = $this->createStorer($userRecoveryProvider);

        $storable = new StorableFlow('name', Context::createDefaultContext(), ['userRecoveryId' => 'id'], []);
        $storer->restore($storable);
        $entity = new UserRecoveryEntity();
        $entity->setId('id');

        $userRecoveryProvider->expects($this->once())->method('getData')->willReturn($entity);
        $res = $storable->getData('userRecovery');

        static::assertSame($res, $entity);
    }

    public function testLazyLoadNullEntity(): void
    {
        $userRecoveryProvider = $this->createMock(UserRecoveryProvider::class);
        $storer = $this->createStorer($userRecoveryProvider);

        $storable = new StorableFlow('name', Context::createDefaultContext(), ['userRecoveryId' => 'id'], []);
        $storer->restore($storable);

        $userRecoveryProvider->expects($this->once())->method('getData')->willReturn(null);
        $res = $storable->getData('userRecovery');

        static::assertNull($res);
    }

    public function testLazyLoadNullId(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), ['userRecoveryId' => null], []);
        $this->storer->restore($storable);
        $customerGroup = $storable->getData('userRecovery');

        static::assertNull($customerGroup);
    }

    private function createStorer(UserRecoveryProvider $userRecoveryProvider): UserStorer
    {
        return new UserStorer(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            $userRecoveryProvider,
        );
    }
}
