<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Lock;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Lock\LockManager;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Lock\Exception\LockAcquiringException;
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
    public function testRunWithLockUsesDefaultsAndReleasesLockAfterSuccessfulCallback(): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())
            ->method('acquire')
            ->with(false)
            ->willReturn(true);
        $lock->expects($this->once())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())
            ->method('createLock')
            ->with('test-lock', LockManager::DEFAULT_TTL)
            ->willReturn($lock);

        $manager = new LockManager($lockFactory);

        $result = $manager->runWithLock(
            'test-lock',
            static fn (): string => 'result',
            static fn (): string => 'fallback',
        );

        static::assertSame('result', $result);
    }

    public function testRunWithLockReleasesLockAfterCallbackException(): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $lockFactory = static::createStub(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);

        $manager = new LockManager($lockFactory);

        $this->expectExceptionObject(new \RuntimeException('callback failed'));

        $manager->runWithLock(
            'test-lock',
            static function (): void {
                throw new \RuntimeException('callback failed');
            },
            static function (): void {
            },
        );
    }

    public function testRunWithLockReturnsFailureCallbackResultWhenLockCannotBeAcquired(): void
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

        $result = $manager->runWithLock(
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
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())
            ->method('acquire')
            ->with(false)
            ->willReturn(false);

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())
            ->method('createLock')
            ->with('test-lock', LockManager::DEFAULT_TTL)
            ->willReturn($lock);

        $manager = new LockManager($lockFactory);

        static::assertNull($manager->acquire('test-lock'));
    }

    public function testAcquirePropagatesLockConflictedException(): void
    {
        $lock = static::createStub(SharedLockInterface::class);
        $exception = new LockConflictedException();
        $lock->method('acquire')->willThrowException($exception);

        $lockFactory = static::createStub(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);

        $manager = new LockManager($lockFactory);

        $this->expectExceptionObject($exception);

        $manager->acquire('test-lock');
    }

    public function testAcquirePropagatesLockAcquiringException(): void
    {
        $lock = static::createStub(SharedLockInterface::class);
        $exception = new LockAcquiringException();
        $lock->method('acquire')->willThrowException($exception);

        $lockFactory = static::createStub(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);

        $manager = new LockManager($lockFactory);

        $this->expectExceptionObject($exception);

        $manager->acquire('test-lock');
    }
}
