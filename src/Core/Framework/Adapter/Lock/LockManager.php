<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Lock;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

/**
 * Provides the common lock handling patterns used across the platform.
 *
 * Use runWithLock() when the protected work starts and ends in the same call. The manager owns
 * the acquired lock and always releases it in a finally block. Use acquire() when the caller owns
 * the lock lifecycle.
 *
 * @internal
 */
#[Package('framework')]
class LockManager
{
    public const DEFAULT_TTL = 300.0;

    public function __construct(
        private readonly LockFactory $lockFactory,
        private readonly string $keyPrefix = '',
    ) {
    }

    /**
     * Acquires a lock, executes the callback and releases the lock afterwards.
     *
     * The failure callback keeps domain-specific fallback behavior and exceptions at the call site.
     *
     * @template T
     *
     * @param \Closure(): T $callback
     * @param \Closure(): T $onLockAcquisitionFailed
     *
     * @return T
     */
    public function runWithLock(
        string $key,
        \Closure $callback,
        \Closure $onLockAcquisitionFailed,
        float $ttl = self::DEFAULT_TTL,
        bool $blocking = false,
    ): mixed {
        $lock = $this->acquire($key, $ttl, $blocking);

        if ($lock === null) {
            return $onLockAcquisitionFailed();
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    /**
     * Acquires a lock and returns it to the caller.
     *
     * The caller owns the returned lock and must release it. This method is intended for workflows
     * where a missing lock is part of the normal control flow.
     */
    public function acquire(
        string $key,
        float $ttl = self::DEFAULT_TTL,
        bool $blocking = false,
    ): ?LockInterface {
        $lock = $this->lockFactory->createLock($this->getLockKey($key), $ttl);

        if ($lock->acquire($blocking)) {
            return $lock;
        }

        return null;
    }

    private function getLockKey(string $key): string
    {
        return $this->keyPrefix . $key;
    }
}
