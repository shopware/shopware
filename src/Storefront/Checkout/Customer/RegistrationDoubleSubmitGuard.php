<?php declare(strict_types=1);

namespace Shopware\Storefront\Checkout\Customer;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

/**
 * Suppresses a storefront registration that re-submits an already consumed context token.
 *
 * A registration that completes spends its context token, so a request still presenting it is a
 * resubmission and is skipped until the marker expires. Completion is the signal rather than the
 * rotated token, because a registration that waits for a double opt-in confirmation returns without
 * rotating it.
 *
 * The marker only records that the token is spent, never what it became. A stale context token is
 * exactly what a fixated session presents, so anything derived from it would be available to an
 * attacker on the same terms as to the genuine sender, and handing back the rotated token would undo
 * the session migration that login performs.
 *
 * Best effort: needs a lock and a cache shared by the competing requests and registers unguarded
 * when either is unavailable, in which case the token is still spent.
 *
 * @internal
 */
#[Package('checkout')]
class RegistrationDoubleSubmitGuard
{
    private const LOCK_TTL = 30.0;

    private const LOCK_WAIT_TIMEOUT = 5.0;

    private const LOCK_RETRY_DELAY_US = 50000;

    // outlives the lock wait budget, so a request that waited it out still finds the marker
    private const MARKER_TTL = 10;

    private const MARKER_KEY_PREFIX = 'storefront-registration-';

    private const LOCK_KEY_PREFIX = 'storefront-registration-lock-';

    public function __construct(
        private readonly LockFactory $lockFactory,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly float $lockWaitTimeout = self::LOCK_WAIT_TIMEOUT,
    ) {
    }

    /**
     * Runs $register unless the context token was already consumed by a registration.
     *
     * @param \Closure(): void $register the actual registration
     */
    public function guard(SalesChannelContext $context, \Closure $register): void
    {
        $token = $context->getToken();
        $markerKey = $this->markerKey($token);

        if ($this->isConsumed($markerKey)) {
            return;
        }

        try {
            $lock = $this->lockFactory->createLock(
                // sha256: the raw token must not be recoverable from key listings
                self::LOCK_KEY_PREFIX . Hasher::hash($token, 'sha256'),
                self::LOCK_TTL,
                autoRelease: false,
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Registration lock could not be created, registering unguarded.', ['exception' => $e]);

            $this->registerAndMark($register, $context, $token, $markerKey);

            return;
        }

        try {
            $acquired = $this->acquireWithDeadline($lock);
        } catch (\Throwable $e) {
            // acquire() can throw after the store saved the lock, do not strand it until the TTL expires
            $this->releaseSilently($lock);
            $this->logger->warning('Registration lock could not be acquired, registering unguarded.', ['exception' => $e]);

            $this->registerAndMark($register, $context, $token, $markerKey);

            return;
        }

        if (!$acquired) {
            // the holder may have finished while we waited
            if ($this->isConsumed($markerKey)) {
                return;
            }

            $this->logger->warning('Registration lock was not acquired within the wait deadline, registering unguarded.');

            $this->registerAndMark($register, $context, $token, $markerKey);

            return;
        }

        try {
            if ($this->isConsumed($markerKey)) {
                return;
            }

            $this->registerAndMark($register, $context, $token, $markerKey);
        } finally {
            $this->releaseSilently($lock);
        }
    }

    private function markerKey(string $token): string
    {
        return self::MARKER_KEY_PREFIX . Hasher::hash($token, 'sha256');
    }

    /**
     * Runs the registration and spends the token, on every path that reaches the registration.
     *
     * An unguarded run creates a customer just as much as a guarded one, so it spends the token too.
     *
     * @param \Closure(): void $register the actual registration
     */
    private function registerAndMark(\Closure $register, SalesChannelContext $context, string $token, string $markerKey): void
    {
        $registered = false;

        try {
            $register();

            $registered = true;
        } finally {
            // a rotated token stays spent even if something threw after the rotation - the customer
            // exists either way from that point on
            if ($registered || $context->getToken() !== $token) {
                $this->markConsumed($markerKey);
            }
        }
    }

    /**
     * Answers whether the token was already spent by a registration.
     *
     * The marker stays until it expires. A suppressed request stays anonymous and keeps presenting
     * the same token, so clearing the marker as it is read would arm the guard for the next
     * resubmission instead of suppressing it.
     *
     * @phpstan-impure a competing registration can write the marker between two calls
     */
    private function isConsumed(string $markerKey): bool
    {
        try {
            return $this->cache->getItem($markerKey)->isHit();
        } catch (\Throwable $e) {
            // a broken cache must not skip the lock
            $this->logger->warning('Registration marker could not be read.', ['exception' => $e]);

            return false;
        }
    }

    /**
     * Written only by the request that ran the registration, so resubmitting can neither clear the
     * marker nor push its expiry forward.
     */
    private function markConsumed(string $markerKey): void
    {
        try {
            $item = $this->cache->getItem($markerKey);
            $item->set(true);
            $item->expiresAfter(self::MARKER_TTL);

            if (!$this->cache->save($item)) {
                $this->logger->warning('Registration marker could not be saved.');
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Registration marker could not be saved.', ['exception' => $e]);
        }
    }

    /**
     * Non-blocking acquire within a bounded retry budget; a blocking acquire could wait indefinitely.
     */
    private function acquireWithDeadline(SharedLockInterface $lock): bool
    {
        $retries = (int) ceil($this->lockWaitTimeout * 1000000 / self::LOCK_RETRY_DELAY_US);

        while (true) {
            if ($lock->acquire()) {
                return true;
            }

            if (--$retries < 0) {
                return false;
            }

            usleep(self::LOCK_RETRY_DELAY_US);
        }
    }

    private function releaseSilently(SharedLockInterface $lock): void
    {
        try {
            $lock->release();
        } catch (\Throwable $e) {
            $this->logger->warning('Registration lock could not be released.', ['exception' => $e]);
        }
    }
}
