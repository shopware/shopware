<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Framework\Log\Package;
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
    public function locked(string $token, \Closure $closure)
    {
        $lockKey = $this->getLockKey($token);
        $lock = $this->lockFactory->createLock($lockKey, self::LOCK_TTL);

        if (!$lock->acquire()) {
            throw CartException::cartLocked($token);
        }

        try {
            return $closure();
        } finally {
            $lock->release();
        }
    }

    public function getLockKey(string $token): string
    {
        return 'cart-lock' . $token;
    }
}
