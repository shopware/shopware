<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\AppRegistrationLock;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

/**
 * @internal
 */
#[CoversClass(AppRegistrationLock::class)]
class AppRegistrationLockTest extends TestCase
{
    public function testAcquireReturnsTheLockKeyedByAppId(): void
    {
        $appId = Uuid::randomHex();

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(true);

        $factory = $this->createMock(LockFactory::class);
        // The key has to be derived from the app id (and shared with rotation/recovery) so all three
        // operations contend for the same lock; a drifting key would silently stop excluding them.
        $factory->expects($this->once())
            ->method('createLock')
            ->with('app-secret-rotation-' . $appId, 30)
            ->willReturn($lock);

        static::assertSame($lock, (new AppRegistrationLock($factory))->acquire($appId));
    }

    public function testAcquireThrowsWhenTheLockIsAlreadyHeld(): void
    {
        $appId = Uuid::randomHex();

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(false);

        $factory = $this->createMock(LockFactory::class);
        $factory->method('createLock')->willReturn($lock);

        $this->expectExceptionObject(AppException::appSecretRotationInProgress($appId));

        (new AppRegistrationLock($factory))->acquire($appId);
    }

    public function testAcquireThrowsWhenTheStoreReportsAConflict(): void
    {
        $appId = Uuid::randomHex();

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')->willThrowException(new LockConflictedException());

        $factory = $this->createMock(LockFactory::class);
        $factory->method('createLock')->willReturn($lock);

        $this->expectExceptionObject(AppException::appSecretRotationInProgress($appId));

        (new AppRegistrationLock($factory))->acquire($appId);
    }

    public function testAcquireFailsGracefullyWhenTheLockStoreIsUnavailable(): void
    {
        // A LockAcquiringException means the lock store itself is unreachable (Redis/DB down, bad LOCK_DSN),
        // not that the lock is held. It must surface as the typed, retryable domain exception instead of
        // escaping raw and breaking a background flow.
        $appId = Uuid::randomHex();

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')->willThrowException(new LockAcquiringException('lock store is down'));

        $factory = $this->createMock(LockFactory::class);
        $factory->method('createLock')->willReturn($lock);

        $this->expectExceptionObject(AppException::appSecretLockUnavailable($appId));

        (new AppRegistrationLock($factory))->acquire($appId);
    }
}
