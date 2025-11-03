<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Lock\LockFactory;

/**
 * @internal
 */
#[Package('checkout')]
class CartLocker
{
    private const LOCK_TTL = 5;

    public function __construct(private readonly LockFactory $lockFactory)
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
        $lock = $this->lockFactory->createLock($lockKey, self::LOCK_TTL);

        if (!$lock->acquire()) {
            throw CartException::cartLocked($context->getToken());
        }

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
