<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Framework\Adapter\Lock\LockManager;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
class CartLocker
{
    private const LOCK_TTL = 5;

    public function __construct(private readonly LockManager $lockManager)
    {
    }

    /**
     * @template T
     *
     * @param \Closure(): T $closure
     *
     * @return T
     */
    public function locked(SalesChannelContext $context, \Closure $closure)
    {
        if ($context->getCartLock()?->isAcquired()) {
            // If the lock is already acquired for this context & process, we can skip acquiring it again
            return $closure();
        }

        $lockKey = $this->getLockKey($context->getToken());
        $lock = $this->lockManager->acquireOrThrow(
            $lockKey,
            fn (): never => throw CartException::cartLocked($context->getToken()),
            ttl: self::LOCK_TTL,
        );

        try {
            $context->setCartLock($lock);

            return $closure();
        } finally {
            $lock->release();
            $context->setCartLock(null);
        }
    }

    public function getLockKey(string $token): string
    {
        return 'cart-lock' . $token;
    }
}
