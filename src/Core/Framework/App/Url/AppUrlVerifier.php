<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Url;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\ShopId\Fingerprint\AppUrl;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
#[Package('framework')]
class AppUrlVerifier
{
    public const VERIFICATION_RESULT_CACHE_KEY = 'app_url_verification_result';
    private const VERIFICATION_CACHE_KEY_PREFIX = 'app_url_verify-';

    private const NON_HARD_FAIL_TTL = 60 * 60 * 24; // 24h
    private const INITIAL_SOFT_FAIL_BACKOFF = 60; // 1 minute
    private const MAX_SOFT_FAIL_BACKOFF = 60 * 60; // 1 hour
    private const VERIFY_PATH = '/api/app-system/shop/verify';

    public function __construct(
        private readonly string $appEnv,
        private readonly string $shopwareVersion,
        private readonly CacheItemPoolInterface&CacheInterface $cache,
        private readonly HttpClientInterface $httpClient,
        private readonly LockFactory $lockFactory,
        private readonly LoggerInterface $logger,
        private readonly ClockInterface $clock,
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

    /**
     * Force verification of the shops APP_URL, ignoring any previous verification attempts.
     *
     * Note: for non-prod environments we skip the verification entirely
     *
     * @param bool $skipEnvCheck Normally verification should only run in production, use this to run in any environment
     */
    public function forceVerify(ShopId $shopId, bool $skipEnvCheck = false): bool
    {
        if ($skipEnvCheck === false && $this->appEnv !== 'prod') {
            return true;
        }

        $this->cache->deleteItem(self::VERIFICATION_RESULT_CACHE_KEY);

        return $this->doVerify($shopId, true);
    }

    /**
     * Attempt to verify the shops APP_URL, however, if we have a cache hit for a previous verification attempt,
     * and the result is a pass, we don't perform the verification.
     *
     * Note: for non-prod environments we skip the verification entirely
     */
    public function verify(ShopId $shopId): bool
    {
        if ($this->appEnv !== 'prod') {
            return true;
        }

        return $this->doVerify($shopId, false);
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

    private function doVerify(ShopId $shopId, bool $force): bool
    {
        $lockKey = $force ? 'app-url-verification-force' : 'app-url-verification';

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
        } catch (\Exception) {
            // we should not blow up here on any account
            return true;
        } finally {
            $lock->release();
        }
    }

    private function handleSoftFail(string $appUrl, VerificationState $previousState): bool
    {
        $wait = $this->backoffForTry($previousState->numTries);
        if ($previousState->isInBackoff($this->clock->now(), $wait)) {
            // still backing off, let communication continue
            return true;
        }

        $state = $this->performVerification($appUrl, $previousState->numTries + 1);

        return $state->isNotHardFail();
    }

    private function backoffForTry(int $numTries): int
    {
        $exponent = max(0, $numTries - 1);
        $wait = self::INITIAL_SOFT_FAIL_BACKOFF * (2 ** $exponent);

        return min(self::MAX_SOFT_FAIL_BACKOFF, $wait);
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

        if (!$state->is(VerificationStatus::PASS)) {
            $this->logger->info(\sprintf('App URL verification failed with status: "%s"', $state->status->name), [
                'info' => $state->info,
                'numTries' => $state->numTries,
            ]);
        }

        return $state;
    }

    private function persist(VerificationState $state): void
    {
        $item = $this->cache->getItem(self::VERIFICATION_RESULT_CACHE_KEY);
        $item->set($state);

        if ($state->is(VerificationStatus::HARD_FAIL)) {
            $item->expiresAt(null); // never expire
        } else {
            $item->expiresAfter(self::NON_HARD_FAIL_TTL);
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
            $response = $this->httpClient->request(
                'GET',
                $url,
                $this->buildRequestOptions($runId, $token)
            );

            // trigger exceptions for any 300-599 response (getContent() throws)
            $response->getContent();

            $status = $response->getStatusCode();

            if ($status === Response::HTTP_NO_CONTENT) {
                return [VerificationStatus::PASS, null];
            }

            // Treat any unexpected non-204 success as hard fail
            $info = $this->extractInfo($response);

            return [VerificationStatus::HARD_FAIL, $info];
        } catch (HttpExceptionInterface $e) {
            // 300-599
            $info = $this->extractInfo($e->getResponse());

            if (
                $e->getResponse()->getStatusCode() >= Response::HTTP_INTERNAL_SERVER_ERROR
                || $e->getResponse()->getStatusCode() === Response::HTTP_TOO_MANY_REQUESTS
            ) {
                // Server errors and rate-limiting are often transient, soft fail
                return [VerificationStatus::SOFT_FAIL, $info];
            }

            // 3xx and 4xx are treated as hard fails
            return [VerificationStatus::HARD_FAIL, $info];
        } catch (TransportExceptionInterface $e) {
            return [VerificationStatus::SOFT_FAIL, 'Failed to connect to APP_URL: ' . $e->getMessage()];
        } catch (\Throwable $e) {
            return [VerificationStatus::HARD_FAIL, $e->getMessage()];
        } finally {
            $this->cache->deleteItem($cacheKey);
        }
    }

    private function extractInfo(ResponseInterface $response): string
    {
        $status = $response->getStatusCode();
        $body = $response->getContent(false);
        $trimmedBody = \strlen($body) > 500 ? \substr($body, 0, 500) . ' ...' : $body;

        return \sprintf('Unexpected response from APP_URL verification endpoint: HTTP code: "%d" body: "%s"', $status, $trimmedBody);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRequestOptions(string $runId, string $token): array
    {
        return [
            'max_redirects' => 1,
            'timeout' => 2.0,
            'query' => ['runId' => $runId, 'token' => $token],
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

        $result = $this->cache->get($cacheKey, function (ItemInterface $item) {
            $item->expiresAfter(60 * 2); // 2 minutes

            return bin2hex(random_bytes(16));
        });

        return $result;
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
