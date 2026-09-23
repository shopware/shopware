<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Guard;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

/**
 * Suppresses a storefront submission that re-submits an already consumed context token.
 *
 * The marker records only that a token is spent, never what it became: a stale token is what a
 * fixated session presents, so anything derived from it would reach an attacker just as easily.
 *
 * @internal
 */
#[Package('checkout')]
class DoubleSubmitGuard
{
    private const LOCK_TTL = 30.0;

    // Past this we submit unguarded, so it must outlast a normal submission — including the mail sent inline until 6.8 defers flows.
    private const LOCK_WAIT_TIMEOUT = 3.0;

    private const LOCK_RETRY_DELAY_US = 50000;

    private const MARKER_TTL = 10;

    public function __construct(
        private readonly LockFactory $lockFactory,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly float $lockWaitTimeout = self::LOCK_WAIT_TIMEOUT,
    ) {
    }

    /**
     * @param string $scope prefixes the marker and the lock key, e.g. RegisterController::DOUBLE_SUBMIT_SCOPE
     * @param \Closure(): void $submit
     *
     * @return bool false only when the submission was suppressed as a double submit
     */
    public function guard(string $scope, SalesChannelContext $context, \Closure $submit): bool
    {
        $token = $context->getToken();
        $markerKey = $this->markerKey($scope, $token);

        if ($this->isConsumed($scope, $markerKey)) {
            return false;
        }

        try {
            $lock = $this->lockFactory->createLock(
                $scope . '-lock-' . $token,
                self::LOCK_TTL,
                autoRelease: false,
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Double submit lock could not be created, submitting unguarded.', ['scope' => $scope, 'exception' => $e]);

            $this->submitAndMark($submit, $context, $scope, $token, $markerKey);

            return true;
        }

        try {
            $acquired = $this->acquireWithDeadline($lock);
        } catch (\Throwable $e) {
            $this->releaseSilently($lock, $scope);
            $this->logger->warning('Double submit lock could not be acquired, submitting unguarded.', ['scope' => $scope, 'exception' => $e]);

            $this->submitAndMark($submit, $context, $scope, $token, $markerKey);

            return true;
        }

        try {
            if ($this->isConsumed($scope, $markerKey)) {
                return false;
            }

            if (!$acquired) {
                $this->logger->warning('Double submit lock was not acquired within the wait deadline, submitting unguarded.', ['scope' => $scope]);
            }

            $this->submitAndMark($submit, $context, $scope, $token, $markerKey);

            return true;
        } finally {
            if ($acquired) {
                $this->releaseSilently($lock, $scope);
            }
        }
    }

    private function markerKey(string $scope, string $token): string
    {
        return $scope . '-' . $token;
    }

    /**
     * @param \Closure(): void $submit
     */
    private function submitAndMark(\Closure $submit, SalesChannelContext $context, string $scope, string $token, string $markerKey): void
    {
        $submitted = false;

        try {
            $submit();

            $submitted = true;
        } finally {
            if ($submitted || $context->getToken() !== $token) {
                $this->markConsumed($scope, $markerKey);
            }
        }
    }

    /**
     * @phpstan-impure a competing submission can write the marker between two calls
     */
    private function isConsumed(string $scope, string $markerKey): bool
    {
        try {
            return $this->cache->getItem($markerKey)->isHit();
        } catch (\Throwable $e) {
            $this->logger->warning('Double submit marker could not be read.', ['scope' => $scope, 'exception' => $e]);

            return false;
        }
    }

    private function markConsumed(string $scope, string $markerKey): void
    {
        try {
            $item = $this->cache->getItem($markerKey);
            $item->set(true);
            $item->expiresAfter(self::MARKER_TTL);

            if (!$this->cache->save($item)) {
                $this->logger->warning('Double submit marker could not be saved.', ['scope' => $scope]);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Double submit marker could not be saved.', ['scope' => $scope, 'exception' => $e]);
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

    private function releaseSilently(SharedLockInterface $lock, string $scope): void
    {
        try {
            $lock->release();
        } catch (\Throwable $e) {
            $this->logger->warning('Double submit lock could not be released.', ['scope' => $scope, 'exception' => $e]);
        }
    }
}
