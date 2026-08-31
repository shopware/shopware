<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Lock;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

/**
 * Provides the common lock handling patterns used across the platform.
 *
 * Use executeLocked() when the protected work starts and ends in the same call. The manager owns
 * the acquired lock and always releases it in a finally block. Use acquireOrThrow() when a failed
 * acquisition maps to a domain exception, and acquire() when a nullable lock is part of the
 * caller's normal control flow.
 *
 * @internal
 */
#[Package('framework')]
class LockManager
{
    public function __construct(
        private readonly LockFactory $lockFactory,
        private readonly string $keyPrefix = '',
    ) {
    }

    /**
     * Acquires a lock, executes the callback and releases the lock afterwards.
     *
     * The failure callback keeps domain-specific fallback behavior and exceptions at the
     * call site. When no TTL is provided, the lock is created without passing a TTL to Symfony so
     * the component's default behavior is preserved exactly.
     *
     * Passing null for $blocking preserves Symfony's default acquire() call; pass true or false
     * when the call site previously used an explicit mode.
     *
     * @template T
     *
     * @param \Closure(): T $callback
     * @param \Closure(): T $onLockAcquisitionFailed
     *
     * @return T
     */
    public function executeLocked(
        string $key,
        \Closure $callback,
        \Closure $onLockAcquisitionFailed,
        ?float $ttl = null,
        ?bool $blocking = null,
        bool $catchAcquiringExceptions = false,
    ): mixed {
        $lock = $this->acquire($key, $ttl, $blocking, $catchAcquiringExceptions);

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
     * Acquires a lock and returns it to the caller or executes the failure callback.
     *
     * The caller owns the returned lock and must release it. Use this method for workflows where
     * failing to acquire the lock is exceptional, but the lock lifecycle cannot be represented by a
     * single callback.
     *
     * Passing null for $blocking preserves Symfony's default acquire() call; pass true or false
     * when the call site previously used an explicit mode.
     *
     * @param \Closure(): never $onLockAcquisitionFailed
     */
    public function acquireOrThrow(
        string $key,
        \Closure $onLockAcquisitionFailed,
        ?float $ttl = null,
        ?bool $blocking = null,
        bool $catchAcquiringExceptions = false,
    ): LockInterface {
        $lock = $this->acquire($key, $ttl, $blocking, $catchAcquiringExceptions);

        if ($lock !== null) {
            return $lock;
        }

        return $onLockAcquisitionFailed();
    }

    /**
     * Acquires a lock and returns it to the caller.
     *
     * The caller owns the returned lock and must release it. This method is intended for workflows
     * where a missing lock is part of the normal control flow.
     *
     * Passing null for $blocking preserves Symfony's default acquire() call; pass true or false
     * when the call site previously used an explicit mode. When $catchAcquiringExceptions is true,
     * Symfony lock acquisition exceptions are treated like a failed acquisition.
     */
    public function acquire(
        string $key,
        ?float $ttl = null,
        ?bool $blocking = null,
        bool $catchAcquiringExceptions = false,
    ): ?LockInterface {
        $lock = $this->createLock($key, $ttl);

        try {
            $acquired = $blocking === null ? $lock->acquire() : $lock->acquire($blocking);

            if ($acquired) {
                return $lock;
            }
        } catch (LockConflictedException|LockAcquiringException $exception) {
            if (!$catchAcquiringExceptions) {
                throw $exception;
            }
        }

        return null;
    }

    private function createLock(string $key, ?float $ttl): LockInterface
    {
        $key = $this->getLockKey($key);

        if ($ttl === null) {
            return $this->lockFactory->createLock($key);
        }

        return $this->lockFactory->createLock($key, $ttl);
    }

    private function getLockKey(string $key): string
    {
        return $this->keyPrefix . $key;
    }
}
