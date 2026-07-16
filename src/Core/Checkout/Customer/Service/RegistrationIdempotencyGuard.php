<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Service;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\SalesChannel\CustomerResponse;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

/**
 * Answers duplicate registration submissions (double-tap, stale-session replay) with the original
 * result instead of registering twice: the registration runs under a short-lived lock that is
 * global per context token, and its result is remembered in a cache marker scoped to sales
 * channel, token and request digest. Best-effort only - it requires lock and cache backends
 * shared by the competing requests and degrades to unguarded registrations on infrastructure
 * failures. Inert in dev/test, where `cache.app` is a per-process array adapter.
 *
 * @internal
 */
#[Package('checkout')]
class RegistrationIdempotencyGuard
{
    private const LOCK_TTL = 30.0;

    private const LOCK_WAIT_TIMEOUT = 5.0;

    private const LOCK_RETRY_DELAY_US = 50000;

    private const MARKER_TTL = 30;

    private const MARKER_KEY_PREFIX = 'customer-registration-';

    private const LOCK_KEY_PREFIX = 'customer-registration-lock-';

    public function __construct(
        private readonly LockFactory $lockFactory,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly float $lockWaitTimeout = self::LOCK_WAIT_TIMEOUT,
    ) {
    }

    /**
     * Runs $register at most once per invocation; failures after it started are logged and never
     * replace its result or mask its exceptions.
     *
     * @param \Closure(): CustomerResponse $register the actual registration
     * @param \Closure(string, ?string): ?CustomerResponse $replay answers a duplicate from the original customer id and
     *                                                             context token (null for double-opt-in); returns null when
     *                                                             the original result is gone and $register should run again
     */
    public function guard(string $salesChannelId, string $contextToken, string $requestDigest, \Closure $register, \Closure $replay): CustomerResponse
    {
        $markerKey = self::MARKER_KEY_PREFIX . Hasher::hash($salesChannelId . '|' . $contextToken . '|' . $requestDigest, 'sha256');

        $response = $this->replayFromMarker($markerKey, $replay);
        if ($response !== null) {
            return $response;
        }

        try {
            $lock = $this->lockFactory->createLock(
                // sha256 (not the xxh128 default): the raw context token must not be recoverable from key listings
                self::LOCK_KEY_PREFIX . Hasher::hash($contextToken, 'sha256'),
                self::LOCK_TTL,
                autoRelease: false,
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Registration lock could not be created, executing the registration unguarded.', ['exception' => $e]);

            return $register();
        }

        try {
            $acquired = $this->acquireWithDeadline($lock);
        } catch (\Throwable $e) {
            // acquire() can throw after the store already saved the lock, do not strand it until the TTL expires
            $this->releaseSilently($lock);
            $this->logger->warning('Registration lock could not be acquired, executing the registration unguarded.', ['exception' => $e]);

            return $register();
        }

        if (!$acquired) {
            $this->logger->warning('Registration lock was not acquired within the wait deadline, executing the registration unguarded.');

            return $register();
        }

        try {
            $response = $this->replayFromMarker($markerKey, $replay);
            if ($response !== null) {
                return $response;
            }

            $response = $register();

            $this->storeMarker($markerKey, $response);

            return $response;
        } finally {
            $this->releaseSilently($lock);
        }
    }

    /**
     * @param \Closure(string, ?string): ?CustomerResponse $replay
     */
    private function replayFromMarker(string $markerKey, \Closure $replay): ?CustomerResponse
    {
        try {
            $item = $this->cache->getItem($markerKey);

            if (!$item->isHit()) {
                return null;
            }

            $marker = $item->get();
        } catch (\Throwable $e) {
            // a broken cache must not skip the lock, the caller continues into the guarded path
            $this->logger->warning('Registration marker could not be read.', ['exception' => $e]);

            return null;
        }

        if (!\is_array($marker)
            || !\array_key_exists('newContextToken', $marker)
            || !\is_string($marker['customerId'] ?? null)
            || !Uuid::isValid($marker['customerId'])
        ) {
            return null;
        }

        $newContextToken = $marker['newContextToken'];
        if ($newContextToken !== null && (!\is_string($newContextToken) || $newContextToken === '')) {
            return null;
        }

        $response = $replay($marker['customerId'], $newContextToken);

        if ($response === null) {
            $this->logger->warning('Duplicate registration detected, but the original result is no longer valid. Executing a fresh registration.', [
                'customerId' => $marker['customerId'],
            ]);
        }

        return $response;
    }

    private function storeMarker(string $markerKey, CustomerResponse $response): void
    {
        try {
            $item = $this->cache->getItem($markerKey);
            $item->set([
                'customerId' => $response->getCustomer()->getId(),
                'newContextToken' => $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN),
            ]);
            $item->expiresAfter(self::MARKER_TTL);

            if (!$this->cache->save($item)) {
                $this->logger->warning('Registration marker could not be saved.');
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Registration marker could not be saved.', ['exception' => $e]);
        }
    }

    /**
     * Non-blocking acquire under a bounded retry budget of roughly $lockWaitTimeout seconds:
     * blocking acquires wait indefinitely (the default FlockStore ignores TTLs entirely), which
     * would let requests sharing one token pile up on the worker pool.
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
