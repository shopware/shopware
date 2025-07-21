<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\CartLocker;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartLocker::class)]
class CartLockerTest extends TestCase
{
    private LockFactory $lockFactory;

    private CartLocker $locker;

    protected function setUp(): void
    {
        $this->lockFactory = new LockFactory(new InMemoryStore());
        $this->locker = new CartLocker($this->lockFactory);
    }

    public function testLockedExecutesClosure(): void
    {
        $called = false;
        $result = $this->locker->locked('test-token', function () use (&$called) {
            $called = true;

            return 'test-result';
        });

        static::assertTrue($called);
        static::assertSame('test-result', $result);
    }

    public function testLockedAcquiresAndReleasesLock(): void
    {
        $token = 'test-token';
        $lock = $this->lockFactory->createLock($this->locker->getLockKey($token));

        // Lock should be available before
        static::assertTrue($lock->acquire());
        $lock->release();

        $this->locker->locked($token, function () use ($lock): void {
            // Lock should not be available during the execution of the closure
            static::assertFalse($lock->acquire(false));
        });

        // Lock should be available again after
        static::assertTrue($lock->acquire());
        $lock->release();
    }

    public function testLockedReleasesLockOnException(): void
    {
        $token = 'test-token';
        $lock = $this->lockFactory->createLock($this->locker->getLockKey($token));

        try {
            $this->locker->locked($token, function (): void {
                throw new \Exception('test');
            });
        } catch (\Exception) {
            // silent
        }

        // Lock should be available again after an exception
        static::assertTrue($lock->acquire());
        $lock->release();
    }

    public function testLockedThrowsExceptionOnFailure(): void
    {
        $token = 'test-token';
        $lock = $this->lockFactory->createLock($this->locker->getLockKey($token));
        $lock->acquire();

        $this->expectExceptionObject(CartException::cartLocked($token));

        $this->locker->locked($token, function (): void {
            // This should not be executed
        });
    }

    public function testGetLockKey(): void
    {
        static::assertSame('cart-locktest-token', $this->locker->getLockKey('test-token'));
    }
}
