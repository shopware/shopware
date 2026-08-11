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
 * The marker records only that a token is spent, never what it became: a stale token is what a
 * fixated session presents, so anything derived from it would reach an attacker just as easily.
 *
 * @internal
 */
#[Package('checkout')]
class RegistrationDoubleSubmitGuard
{
    private const LOCK_TTL = 30.0;

    private const LOCK_WAIT_TIMEOUT = 5.0;

    private const LOCK_RETRY_DELAY_US = 50000;

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
     * @param \Closure(): void $register
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
            $this->releaseSilently($lock);
            $this->logger->warning('Registration lock could not be acquired, registering unguarded.', ['exception' => $e]);

            $this->registerAndMark($register, $context, $token, $markerKey);

            return;
        }

        if (!$acquired) {
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
     * @param \Closure(): void $register
     */
    private function registerAndMark(\Closure $register, SalesChannelContext $context, string $token, string $markerKey): void
    {
        $registered = false;

        try {
            $register();

            $registered = true;
        } finally {
            if ($registered || $context->getToken() !== $token) {
                $this->markConsumed($markerKey);
            }
        }
    }

    /**
     * @phpstan-impure a competing registration can write the marker between two calls
     */
    private function isConsumed(string $markerKey): bool
    {
        try {
            return $this->cache->getItem($markerKey)->isHit();
        } catch (\Throwable $e) {
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
