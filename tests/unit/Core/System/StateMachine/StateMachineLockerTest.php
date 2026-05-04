<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\StateMachine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\StateMachineEntity;
use Shopware\Core\System\StateMachine\StateMachineException;
use Shopware\Core\System\StateMachine\StateMachineLocker;
use Shopware\Core\System\StateMachine\StateMachineTransitionResult;
use Shopware\Core\System\StateMachine\Transition;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * @internal
 */
#[CoversClass(StateMachineLocker::class)]
#[CoversClass(StateMachineTransitionResult::class)]
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
        $transitionResult = $this->createTransitionResult();

        $result = $this->locker->locked($this->createTransition(), Context::createDefaultContext(), static function () use (&$called, $transitionResult): StateMachineTransitionResult {
            $called = true;

            return $transitionResult;
        });

        static::assertTrue($called);
        static::assertSame($transitionResult, $result);
    }

    public function testLockedAcquiresAndReleasesLock(): void
    {
        $transition = $this->createTransition();
        $context = Context::createDefaultContext();
        $lock = $this->lockFactory->createLock($this->locker->getLockKey($transition, $context));

        static::assertTrue($lock->acquire());
        $lock->release();

        $transitionResult = $this->locker->locked($transition, $context, function () use ($lock): StateMachineTransitionResult {
            static::assertFalse($lock->acquire(false));

            return $this->createTransitionResult();
        });

        static::assertTrue($transitionResult->hasTransitioned);
        static::assertTrue($lock->acquire());
        $lock->release();
    }

    public function testLockedReleasesLockOnException(): void
    {
        $transition = $this->createTransition();
        $context = Context::createDefaultContext();
        $lock = $this->lockFactory->createLock($this->locker->getLockKey($transition, $context));

        try {
            $this->locker->locked($transition, $context, static function (): StateMachineTransitionResult {
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

        $transitionResult = $this->createTransitionResult();

        $result = $locker->locked(
            $transition,
            $context,
            static fn (): StateMachineTransitionResult => $locker->locked(
                $transition,
                $context,
                static fn (): StateMachineTransitionResult => $transitionResult
            )
        );

        static::assertSame($transitionResult, $result);
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
            ->with($locker->getLockKey($transition, $context), 5.0, true)
            ->willReturn($lock);

        $lock->expects($this->once())
            ->method('acquire')
            ->with(true)
            ->willReturn(false);

        $lock->expects($this->never())
            ->method('release');

        $this->expectExceptionObject(StateMachineException::stateMachineTransitionLocked('order_transaction', '018f7bb26244728091f5077b7c20f8ca'));

        $locker->locked(
            $transition,
            $context,
            fn (): StateMachineTransitionResult => $this->createTransitionResult()
        );
    }

    public function testGetLockKey(): void
    {
        $transition = $this->createTransition();
        $context = Context::createDefaultContext();

        static::assertSame(
            'state-machine-transition-' . \hash('xxh128', 'order_transaction-018f7bb26244728091f5077b7c20f8ca-' . Defaults::LIVE_VERSION),
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

    public function testReset(): void
    {
        $transition = $this->createTransition();
        $context = Context::createDefaultContext();
        $lockFactory = $this->createMock(LockFactory::class);
        $firstLock = $this->createMock(SharedLockInterface::class);
        $secondLock = $this->createMock(SharedLockInterface::class);
        $locker = new StateMachineLocker($lockFactory);
        $transitionResult = $this->createTransitionResult();

        $lockFactory->expects($this->exactly(2))
            ->method('createLock')
            ->with($locker->getLockKey($transition, $context), 5.0, true)
            ->willReturnOnConsecutiveCalls($firstLock, $secondLock);

        $firstLock->expects($this->once())
            ->method('acquire')
            ->with(true)
            ->willReturn(true);

        $firstLock->expects($this->once())
            ->method('release');

        $secondLock->expects($this->once())
            ->method('acquire')
            ->with(true)
            ->willReturn(true);

        $secondLock->expects($this->once())
            ->method('release');

        $result = $locker->locked(
            $transition,
            $context,
            static function () use ($locker, $transition, $context, $transitionResult): StateMachineTransitionResult {
                $locker->reset();

                return $locker->locked(
                    $transition,
                    $context,
                    static fn (): StateMachineTransitionResult => $transitionResult
                );
            }
        );

        static::assertSame($transitionResult, $result);
    }

    private function createTransition(string $transitionName = 'paid'): Transition
    {
        return new Transition('order_transaction', '018f7bb26244728091f5077b7c20f8ca', $transitionName, 'stateId');
    }

    private function createTransitionResult(): StateMachineTransitionResult
    {
        $stateMachine = new StateMachineEntity();
        $stateMachine->setId('state-machine-id');
        $stateMachine->setTechnicalName('order_transaction.state');

        $fromPlace = new StateMachineStateEntity();
        $fromPlace->setId('from-place-id');
        $fromPlace->setStateMachineId($stateMachine->getId());
        $fromPlace->setTechnicalName('open');

        $toPlace = new StateMachineStateEntity();
        $toPlace->setId('to-place-id');
        $toPlace->setStateMachineId($stateMachine->getId());
        $toPlace->setTechnicalName('paid');

        $stateMachineStates = new StateMachineStateCollection();
        $stateMachineStates->set('fromPlace', $fromPlace);
        $stateMachineStates->set('toPlace', $toPlace);

        return new StateMachineTransitionResult(
            true,
            $stateMachineStates,
            $stateMachine,
            $fromPlace,
            $toPlace,
        );
    }
}
