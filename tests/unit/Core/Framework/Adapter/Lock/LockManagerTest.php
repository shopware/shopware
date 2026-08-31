<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Lock;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Lock\LockManager;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LockManager::class)]
class LockManagerTest extends TestCase
{
    public function testExecuteLockedReleasesLockAfterSuccessfulCallback(): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())
            ->method('acquire')
            ->with()
            ->willReturn(true);
        $lock->expects($this->once())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())
            ->method('createLock')
            ->with('test-lock')
            ->willReturn($lock);

        $manager = new LockManager($lockFactory);

        $result = $manager->executeLocked(
            'test-lock',
            static fn (): string => 'result',
            static fn (): string => 'fallback',
        );

        static::assertSame('result', $result);
    }

    public function testExecuteLockedReleasesLockAfterCallbackException(): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $lockFactory = static::createStub(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);

        $manager = new LockManager($lockFactory);

        $this->expectExceptionObject(new \RuntimeException('callback failed'));

        $manager->executeLocked(
            'test-lock',
            static function (): void {
                throw new \RuntimeException('callback failed');
            },
            static function (): void {
            },
        );
    }

    public function testExecuteLockedReturnsFailureCallbackResultWhenLockCannotBeAcquired(): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())
            ->method('acquire')
            ->with(false)
            ->willReturn(false);
        $lock->expects($this->never())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())
            ->method('createLock')
            ->with('test-lock', 5.0)
            ->willReturn($lock);

        $manager = new LockManager($lockFactory);

        $result = $manager->executeLocked(
            'test-lock',
            static fn (): string => 'locked',
            static fn (): string => 'fallback',
            ttl: 5.0,
            blocking: false,
        );

        static::assertSame('fallback', $result);
    }

    public function testAcquireReturnsLockWithoutReleasingIt(): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())
            ->method('acquire')
            ->with(true)
            ->willReturn(true);
        $lock->expects($this->never())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())
            ->method('createLock')
            ->with('prefix-test-lock', 10.0)
            ->willReturn($lock);

        $manager = new LockManager($lockFactory, 'prefix-');

        static::assertSame($lock, $manager->acquire('test-lock', ttl: 10.0, blocking: true));
    }

    public function testAcquireReturnsNullWhenLockCannotBeAcquired(): void
    {
        $lock = static::createStub(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(false);

        $lockFactory = static::createStub(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);

        $manager = new LockManager($lockFactory);

        static::assertNull($manager->acquire('test-lock'));
    }

    public function testAcquireOrThrowReturnsLockWithoutReleasingIt(): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())
            ->method('acquire')
            ->with(false)
            ->willReturn(true);
        $lock->expects($this->never())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())
            ->method('createLock')
            ->with('test-lock', 15.0)
            ->willReturn($lock);

        $manager = new LockManager($lockFactory);

        static::assertSame($lock, $manager->acquireOrThrow(
            'test-lock',
            static fn (): never => throw new \RuntimeException('lock failed'),
            ttl: 15.0,
            blocking: false,
        ));
    }

    public function testAcquireOrThrowRunsFailureCallbackWhenLockCannotBeAcquired(): void
    {
        $lock = static::createStub(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(false);

        $lockFactory = static::createStub(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);

        $manager = new LockManager($lockFactory);

        $this->expectExceptionObject(new \RuntimeException('lock failed'));

        $manager->acquireOrThrow(
            'test-lock',
            static fn (): never => throw new \RuntimeException('lock failed'),
        );
    }

    public function testAcquireCanTreatSymfonyAcquisitionExceptionsLikeFailedAcquisition(): void
    {
        $lock = static::createStub(SharedLockInterface::class);
        $lock->method('acquire')->willThrowException(new LockConflictedException());

        $lockFactory = static::createStub(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);

        $manager = new LockManager($lockFactory);

        static::assertNull($manager->acquire('test-lock', catchAcquiringExceptions: true));
    }
}
