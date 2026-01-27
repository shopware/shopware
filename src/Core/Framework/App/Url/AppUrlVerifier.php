<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Url;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\ShopId\Fingerprint\AppUrl;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('framework')]
class AppUrlVerifier
{
    public const VERIFICATION_RESULT_CACHE_KEY = 'app_url_verification_result';
    private const VERIFICATION_CACHE_KEY_PREFIX = 'app_url_verify-';
    private const BACK_OFF = [
        1 => 60 * 5,  // 5 minutes
        2 => 60 * 15, // 15 minutes
    ];
    private const MAX_TRIES = 3;
    private const VERIFY_PATH = '/api/app-system/shop/verify';

    public function __construct(
        private readonly string $appEnv,
        private readonly string $shopwareVersion,
        private readonly CacheItemPoolInterface&CacheInterface $cache,
        private readonly HttpClientInterface $httpClient,
        private readonly LockFactory $lockFactory, /** Should be a lock with ttl support - otherwise locks might not expire and verification will not be performed */
        private readonly ClockInterface $clock = new NativeClock(),
    ) {
    }

    public function getCurrentState(): ?VerificationState
    {
        $item = $this->cache->getItem(self::VERIFICATION_RESULT_CACHE_KEY);

        if ($item->isHit()) {
            return $item->get();
        }

        return null;
    }

    public function forceVerify(ShopId $shopId): bool
    {
        $this->cache->deleteItem(self::VERIFICATION_RESULT_CACHE_KEY);

        return $this->doVerify($shopId, 'app-url-verification-force');
    }

    public function verify(ShopId $shopId): bool
    {
        return $this->doVerify($shopId, 'app-url-verification');
    }

    /**
     * Finalize verification, check if the given token and key matches what is stored in the cache.
     */
    public function completeVerification(string $runId, string $token): bool
    {
        $cacheKey = AppUrlVerifier::VERIFICATION_CACHE_KEY_PREFIX . $runId;

        $item = $this->cache->getItem($cacheKey);

        if (!$item->isHit()) {
            return false;
        }

        $storedToken = $item->get();

        if (\strlen($storedToken) !== 32 || \strlen($token) !== 32) {
            return false;
        }

        return hash_equals($storedToken, $token);
    }

    private function doVerify(ShopId $shopId, string $lockKey): bool
    {
        if ($this->appEnv !== 'prod') {
            return true;
        }

        $appUrl = $shopId->getFingerprint(AppUrl::IDENTIFIER);

        if ($appUrl === null) {
            return false;
        }

        $lock = $this->acquireLock($lockKey);
        if ($lock === null) {
            // if we can't get a lock, just return true - so app communications can continue
            return true;
        }

        try {
            $state = $this->getCurrentState();

            if ($state === null) {
                // first attempt
                $state = $this->performVerification($appUrl);

                return $state->isNotHardFail();
            }

            return match ($state->status) {
                VerificationStatus::PASS => true,
                VerificationStatus::HARD_FAIL => false,
                VerificationStatus::SOFT_FAIL => $this->handleSoftFail($appUrl, $state),
            };
        } catch (\Throwable) {
            // we should not blow up here on any account
            return true;
        } finally {
            $lock->release();
        }
    }

    private function handleSoftFail(string $appUrl, VerificationState $previousState): bool
    {
        $wait = self::BACK_OFF[$previousState->numTries];
        if ($previousState->isInBackoff($this->clock->now(), $wait)) {
            // still backing off, let communication continue
            return true;
        }

        $state = $this->performVerification($appUrl, $previousState->numTries + 1);

        if ($state->is(VerificationStatus::SOFT_FAIL) && $state->numTries >= self::MAX_TRIES) {
            $this->persist($state->asHardFail($this->clock->now()));

            return false;
        }

        return $state->isNotHardFail();
    }

    private function acquireLock(string $lockKey): ?LockInterface
    {
        $lock = $this->lockFactory->createLock($lockKey, 10);

        try {
            if ($lock->acquire()) {
                return $lock;
            }
        } catch (LockConflictedException|LockAcquiringException) {
        }

        return null;
    }

    private function performVerification(string $appUrl, int $tries = 1): VerificationState
    {
        [$status, $info] = $this->executeVerify($appUrl);

        $state = new VerificationState($status, $tries, $this->clock->now(), $info);

        $this->persist($state);

        return $state;
    }

    private function persist(VerificationState $state): void
    {
        $item = $this->cache->getItem(self::VERIFICATION_RESULT_CACHE_KEY);
        $item->set($state);

        if ($state->is(VerificationStatus::HARD_FAIL)) {
            $item->expiresAt(null); // never expire
        } else {
            $item->expiresAfter(60 * 60 * 24); // 24h
        }

        $this->cache->save($item);
    }

    /**
     * @return array{0: VerificationStatus, 1: string|null}
     */
    private function executeVerify(string $appUrl): array
    {
        try {
            $url = $this->buildVerificationUrl($appUrl);
        } catch (AppException $e) {
            return [VerificationStatus::HARD_FAIL, $e->getMessage()];
        }

        $runId = bin2hex(random_bytes(8));
        $cacheKey = self::VERIFICATION_CACHE_KEY_PREFIX . $runId;
        $token = $this->createAndPersistToken($cacheKey);

        try {
            $resp = $this->httpClient->request(
                'GET',
                $url,
                $this->buildRequestOptions($runId, $token)
            );

            if ($resp->getStatusCode() === Response::HTTP_NO_CONTENT) {
                return [VerificationStatus::PASS, null];
            }

            if ($resp->getStatusCode() >= Response::HTTP_INTERNAL_SERVER_ERROR) {
                return [VerificationStatus::SOFT_FAIL, $resp->getContent(false)];
            }
        } catch (TransportExceptionInterface $e) {
            return [VerificationStatus::SOFT_FAIL, 'Failed to connect to APP_URL'];
        } catch (\Throwable $e) {
            return [VerificationStatus::HARD_FAIL, $e->getMessage()];
        } finally {
            $this->cache->deleteItem($cacheKey);
        }

        return [VerificationStatus::HARD_FAIL, 'Verification failed'];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRequestOptions(string $runId, string $token): array
    {
        return [
            'max_redirects' => 1,
            'timeout' => 2.0,
            'connect_timeout' => 0.5,
            'query' => ['rid' => $runId, 'token' => $token],
            'headers' => [
                'Cache-Control' => 'no-store, no-cache, max-age=0',
                'Pragma' => 'no-cache',
                'User-Agent' => 'Shopware-AppUrlVerifier/' . $this->shopwareVersion,
            ],
        ];
    }

    private function createAndPersistToken(string $cacheKey): string
    {
        $this->cache->deleteItem($cacheKey);

        return $this->cache->get($cacheKey, function (ItemInterface $item) {
            $item->expiresAfter(60 * 2); // 2 minutes

            return bin2hex(random_bytes(16));
        });
    }

    private function buildVerificationUrl(string $appUrl): string
    {
        $trimmed = rtrim($appUrl, '/');

        if (!filter_var($trimmed, \FILTER_VALIDATE_URL)) {
            throw AppException::invalidAppUrl('Invalid URL format.');
        }

        $parts = parse_url($trimmed);
        if (!isset($parts['scheme'], $parts['host'])) {
            throw AppException::invalidAppUrl('Invalid URL format.');
        }

        if (strtolower($parts['scheme']) !== 'https') {
            throw AppException::invalidAppUrl('HTTPS is required.');
        }

        return $trimmed . self::VERIFY_PATH;
    }
}
