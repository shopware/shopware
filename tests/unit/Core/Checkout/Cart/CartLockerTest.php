<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\CartLocker;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
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
        $context = Generator::generateSalesChannelContext(token: 'test-token');
        $result = $this->locker->locked($context, function () use (&$called) {
            $called = true;

            return 'test-result';
        });

        static::assertTrue($called);
        static::assertSame('test-result', $result);
    }

    public function testLockedAcquiresAndReleasesLock(): void
    {
        $token = 'test-token';
        $context = Generator::generateSalesChannelContext(token: $token);
        $lock = $this->lockFactory->createLock($this->locker->getLockKey($token));

        // Lock should be available before
        static::assertTrue($lock->acquire());
        $lock->release();

        $this->locker->locked($context, function () use ($lock): void {
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
        $context = Generator::generateSalesChannelContext(token: $token);
        $lock = $this->lockFactory->createLock($this->locker->getLockKey($token));

        try {
            $this->locker->locked($context, function (): void {
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
        $context = Generator::generateSalesChannelContext(token: $token);
        $lock = $this->lockFactory->createLock($this->locker->getLockKey($token));
        $lock->acquire();

        $this->expectExceptionObject(CartException::cartLocked($token));

        $this->locker->locked($context, function (): void {
            // This should not be executed
        });
    }

    public function testRecursiveUsageShouldNotThrowException(): void
    {
        $token = 'test-token';
        $context = Generator::generateSalesChannelContext(token: $token);

        $this->locker->locked($context, function () use ($context): void {
            $firstLock = $context->getCartLock();
            static::assertInstanceOf(LockInterface::class, $firstLock);

            $this->locker->locked($context, function () use ($context, $firstLock): void {
                static::assertSame($context->getCartLock(), $firstLock);
            });
        });

        static::assertNull($context->getCartLock());
    }

    public function testGetLockKey(): void
    {
        static::assertSame('cart-locktest-token', $this->locker->getLockKey('test-token'));
    }
}
