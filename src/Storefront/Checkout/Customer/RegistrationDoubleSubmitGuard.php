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
 * A successful registration rotates the context token, so a request still presenting the old one is
 * a resubmission and is skipped.
 *
 * The marker only records that the token is spent, never what it became. A stale context token is
 * exactly what a fixated session presents, so anything derived from it would be available to an
 * attacker on the same terms as to the genuine sender, and handing back the rotated token would undo
 * the session migration that login performs.
 *
 * Best effort: needs a lock and a cache shared by the competing requests and registers unguarded
 * when either is unavailable. Registrations that do not rotate the token are not covered.
 *
 * @internal
 */
#[Package('checkout')]
class RegistrationDoubleSubmitGuard
{
    private const LOCK_TTL = 30.0;

    private const LOCK_WAIT_TIMEOUT = 5.0;

    private const LOCK_RETRY_DELAY_US = 50000;

    private const MARKER_TTL = 30;

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

        if ($this->claimMarker($markerKey)) {
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

            $register();

            return;
        }

        try {
            $acquired = $this->acquireWithDeadline($lock);
        } catch (\Throwable $e) {
            // acquire() can throw after the store saved the lock, do not strand it until the TTL expires
            $this->releaseSilently($lock);
            $this->logger->warning('Registration lock could not be acquired, registering unguarded.', ['exception' => $e]);

            $register();

            return;
        }

        if (!$acquired) {
            // the holder may have finished while we waited
            if ($this->claimMarker($markerKey)) {
                return;
            }

            $this->logger->warning('Registration lock was not acquired within the wait deadline, registering unguarded.');

            $register();

            return;
        }

        try {
            if ($this->claimMarker($markerKey)) {
                return;
            }

            $register();
        } finally {
            // only a rotated token is spent, and it stays spent even if something threw after the
            // rotation - the customer exists either way from that point on
            if ($context->getToken() !== $token) {
                $this->markConsumed($markerKey);
            }

            $this->releaseSilently($lock);
        }
    }

    private function markerKey(string $token): string
    {
        return self::MARKER_KEY_PREFIX . Hasher::hash($token, 'sha256');
    }

    /**
     * Answers whether the token is spent, and clears the marker in the same step.
     *
     * The marker absorbs one resubmission, which is what a double submit produces. Leaving it in
     * place would also swallow the registration the visitor then makes deliberately, because a
     * suppressed request stays anonymous and keeps presenting the same token.
     *
     * @phpstan-impure the marker is cleared as it is read, so repeated calls do not agree
     */
    private function claimMarker(string $markerKey): bool
    {
        try {
            if (!$this->cache->getItem($markerKey)->isHit()) {
                return false;
            }

            $this->cache->deleteItem($markerKey);

            return true;
        } catch (\Throwable $e) {
            // a broken cache must not skip the lock
            $this->logger->warning('Registration marker could not be read.', ['exception' => $e]);

            return false;
        }
    }

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
