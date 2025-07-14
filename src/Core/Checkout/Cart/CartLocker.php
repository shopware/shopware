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
    private const LOCK_TTL = 30;

    public function __construct(private readonly LockFactory $lockFactory)
    {
    }

    /**
     * @param \Closure(): \T $closure
     *
     * @return \T
     *
     * @template \T
     */
    public function locked(string $token, \Closure $closure)
    {
        $lock = $this->lockFactory->createLock('cart-' . $token, self::LOCK_TTL);

        if (!$lock->acquire()) {
            throw CartException::cartLocked($token);
        }

        try {
            return $closure();
        } finally {
            $lock->release();
        }
    }
}
