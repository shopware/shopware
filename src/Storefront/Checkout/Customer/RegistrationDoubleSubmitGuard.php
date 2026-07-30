<?php declare(strict_types=1);

namespace Shopware\Storefront\Checkout\Customer;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

/**
 * Suppresses a storefront registration that re-submits an already consumed context token.
 *
 * A successful registration rotates the context token, so a request still presenting the old one is
 * a resubmission. It is skipped, and its session is pointed at the token the first request produced.
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
        private readonly RequestStack $requestStack,
        private readonly SystemConfigService $systemConfigService,
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

        $winner = $this->consumedBy($markerKey);
        if ($winner !== null) {
            $this->adoptWinnerSession($winner);

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
            $winner = $this->consumedBy($markerKey);
            if ($winner !== null) {
                $this->adoptWinnerSession($winner);

                return;
            }

            $this->logger->warning('Registration lock was not acquired within the wait deadline, registering unguarded.');

            $register();

            return;
        }

        try {
            $winner = $this->consumedBy($markerKey);
            if ($winner !== null) {
                $this->adoptWinnerSession($winner);

                return;
            }

            $register();

            // only a rotated token is spent
            if ($context->getToken() !== $token) {
                $this->markConsumed($markerKey, $context->getToken());
            }
        } finally {
            $this->releaseSilently($lock);
        }
    }

    private function markerKey(string $token): string
    {
        return self::MARKER_KEY_PREFIX . Hasher::hash($token, 'sha256');
    }

    /**
     * The token a completed registration rotated this one into, or null when it is unconsumed.
     */
    private function consumedBy(string $markerKey): ?string
    {
        try {
            $item = $this->cache->getItem($markerKey);

            if (!$item->isHit()) {
                return null;
            }

            $winner = $item->get();
        } catch (\Throwable $e) {
            // a broken cache must not skip the lock
            $this->logger->warning('Registration marker could not be read.', ['exception' => $e]);

            return null;
        }

        return \is_string($winner) && $winner !== '' ? $winner : null;
    }

    private function markConsumed(string $markerKey, string $winnerToken): void
    {
        try {
            $item = $this->cache->getItem($markerKey);
            $item->set($winnerToken);
            $item->expiresAfter(self::MARKER_TTL);

            if (!$this->cache->save($item)) {
                $this->logger->warning('Registration marker could not be saved.');
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Registration marker could not be saved.', ['exception' => $e]);
        }
    }

    /**
     * Points the session at the given context token, mirroring StorefrontSubscriber::updateSession():
     * migrate first, so the anonymous session id cannot reach the logged-in context.
     */
    private function adoptWinnerSession(string $winnerToken): void
    {
        $mainRequest = $this->requestStack->getMainRequest();
        if ($mainRequest === null || !$mainRequest->hasSession(true)) {
            return;
        }

        $session = $mainRequest->getSession();
        $session->migrate();
        $session->set('sessionId', $session->getId());

        if ($this->systemConfigService->getBool('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel')) {
            $salesChannelId = $mainRequest->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);

            if ($salesChannelId) {
                $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $salesChannelId, $winnerToken);
            }
        }

        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $winnerToken);
        $mainRequest->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $winnerToken);
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
