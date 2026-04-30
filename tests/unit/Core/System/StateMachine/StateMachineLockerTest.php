<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\StateMachine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\StateMachineException;
use Shopware\Core\System\StateMachine\StateMachineLocker;
use Shopware\Core\System\StateMachine\Transition;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * @internal
 */
#[CoversClass(StateMachineLocker::class)]
class StateMachineLockerTest extends TestCase
{
    private LockFactory $lockFactory;

    private StateMachineLocker $locker;

    protected function setUp(): void
    {
        $this->lockFactory = new LockFactory(new InMemoryStore());
        $this->locker = new StateMachineLocker($this->lockFactory);
    }

    public function testLockedExecutesClosure(): void
    {
        $called = false;

        $result = $this->locker->locked($this->createTransition(), Context::createDefaultContext(), static function () use (&$called): string {
            $called = true;

            return 'test-result';
        });

        static::assertTrue($called);
        static::assertSame('test-result', $result);
    }

    public function testLockedAcquiresAndReleasesLock(): void
    {
        $transition = $this->createTransition();
        $context = Context::createDefaultContext();
        $lock = $this->lockFactory->createLock($this->locker->getLockKey($transition, $context));

        static::assertTrue($lock->acquire());
        $lock->release();

        $this->locker->locked($transition, $context, static function () use ($lock): void {
            static::assertFalse($lock->acquire(false));
        });

        static::assertTrue($lock->acquire());
        $lock->release();
    }

    public function testLockedReleasesLockOnException(): void
    {
        $transition = $this->createTransition();
        $context = Context::createDefaultContext();
        $lock = $this->lockFactory->createLock($this->locker->getLockKey($transition, $context));

        try {
            $this->locker->locked($transition, $context, static function (): void {
                throw new \RuntimeException('test');
            });
        } catch (\RuntimeException) {
        }

        static::assertTrue($lock->acquire());
        $lock->release();
    }

    public function testRecursiveUsageShouldNotAcquireLockAgain(): void
    {
        $transition = $this->createTransition();
        $context = Context::createDefaultContext();
        $lockFactory = $this->createMock(LockFactory::class);
        $lock = $this->createMock(SharedLockInterface::class);
        $locker = new StateMachineLocker($lockFactory);

        $lockFactory->expects($this->once())
            ->method('createLock')
            ->willReturn($lock);

        $lock->expects($this->once())
            ->method('acquire')
            ->with(true)
            ->willReturn(true);

        $lock->expects($this->once())
            ->method('release');

        $locker->locked($transition, $context, static fn () => $locker->locked($transition, $context, static fn (): string => 'nested'));
    }

    public function testLockedThrowsExceptionOnFailure(): void
    {
        $transition = $this->createTransition();
        $context = Context::createDefaultContext();
        $lockFactory = $this->createMock(LockFactory::class);
        $lock = $this->createMock(SharedLockInterface::class);
        $locker = new StateMachineLocker($lockFactory);

        $lockFactory->expects($this->once())
            ->method('createLock')
            ->with($locker->getLockKey($transition, $context), 30.0, true)
            ->willReturn($lock);

        $lock->expects($this->once())
            ->method('acquire')
            ->with(true)
            ->willReturn(false);

        $lock->expects($this->never())
            ->method('release');

        $this->expectExceptionObject(StateMachineException::stateMachineTransitionLocked('order_transaction', '018f7bb26244728091f5077b7c20f8ca'));

        $locker->locked($transition, $context, static function (): void {
        });
    }

    public function testGetLockKey(): void
    {
        $transition = $this->createTransition();
        $context = Context::createDefaultContext();

        static::assertSame(
            'state-machine-transition-' . hash('xxh128', 'order_transaction-018f7bb26244728091f5077b7c20f8ca-' . Defaults::LIVE_VERSION),
            $this->locker->getLockKey($transition, $context)
        );
    }

    public function testGetLockKeyIgnoresTransitionName(): void
    {
        $context = Context::createDefaultContext();

        static::assertSame(
            $this->locker->getLockKey($this->createTransition('paid'), $context),
            $this->locker->getLockKey($this->createTransition('cancel'), $context)
        );
    }

    private function createTransition(string $transitionName = 'paid'): Transition
    {
        return new Transition('order_transaction', '018f7bb26244728091f5077b7c20f8ca', $transitionName, 'stateId');
    }
}
