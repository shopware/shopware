<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Profile;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Collection\UcpPlatformProfileCacheCollection;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpPlatformProfileCacheEntity;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Fetches platform profiles with the constraints required by the UCP spec:
 *   - HTTPS only, no redirects
 *   - Connect/read timeouts
 *   - LRU-bounded DB-backed cache, TTL >= 60s
 *   - Stale-while-revalidate semantics
 *   - Discovery-budget enforcement
 *   - Validation pipeline
 *
 * @internal
 */
#[Package('framework')]
class PlatformProfileFetcher
{
    public const DEFAULT_TTL_SECONDS = 300;
    public const MIN_TTL_SECONDS = 60;
    public const CONNECT_TIMEOUT_SECONDS = 5;
    public const RESPONSE_TIMEOUT_SECONDS = 10;
    public const MAX_RESPONSE_BYTES = 256 * 1024;

    /**
     * @param EntityRepository<UcpPlatformProfileCacheCollection> $platformProfileCacheRepository
     */
    public function __construct(
        private readonly EntityRepository $platformProfileCacheRepository,
        private readonly PlatformProfileValidator $validator,
        private readonly DiscoveryBudgetEnforcer $budgetEnforcer,
        private readonly UrlSafetyValidator $urlSafety,
        private readonly string $environment = 'prod',
    ) {
    }

    /**
     * @param array<string>|null $allowlist null = permissionless
     *
     * @return array<string, mixed>
     */
    public function fetch(string $profileUri, Context $context, ?array $allowlist = null): array
    {
        $resolved = $this->urlSafety->validateAndResolve($profileUri, $allowlist, $this->environment);

        $cached = $this->findCacheEntry($profileUri, $context);
        if ($cached !== null && !$cached->isExpired()) {
            return $cached->getProfileJson();
        }

        $this->budgetEnforcer->assertBudget($profileUri);

        try {
            $response = $this->requestPinnedWithLocalhostFallback($profileUri, $resolved['host'], $resolved['resolved_ip']);
            $statusCode = $response['status_code'];
            if ($statusCode >= 300) {
                $this->budgetEnforcer->recordFailure($profileUri);
                throw UcpException::profileUnreachable($profileUri, 'HTTP ' . $statusCode);
            }

            $rawContent = $response['body'];
            if (\strlen($rawContent) > self::MAX_RESPONSE_BYTES) {
                throw UcpException::profileMalformed($profileUri, 'Response exceeds maximum size');
            }

            $decoded = json_decode($rawContent, true);
            if (!\is_array($decoded)) {
                throw UcpException::profileMalformed($profileUri, 'Body is not a JSON object');
            }

            $this->validator->validate($profileUri, $decoded);
            $this->budgetEnforcer->recordSuccess($profileUri);

            $ttl = $this->extractTtl($response['headers']);
            $etag = $response['headers']['etag'][0] ?? null;

            $this->upsertCache($cached, $profileUri, $decoded, $etag, $ttl, $context);

            return $decoded;
        } catch (\Throwable $e) {
            $this->budgetEnforcer->recordFailure($profileUri);
            // Stale fallback if available
            if ($cached !== null) {
                return $cached->getProfileJson();
            }
            throw UcpException::profileUnreachable($profileUri, $e->getMessage());
        }
    }

    /**
     * Perform the HTTP fetch while pinning DNS resolution to the already
     * validated IP address. This closes the DNS-rebinding gap where validation
     * resolved a public IP, but the actual HTTP client resolved the same host
     * again to a private/internal IP later.
     *
     * @return array{status_code: int, headers: array<string, list<string>>, body: string}
     */
    private function requestPinned(string $url, string $host, string $resolvedIp): array
    {
        if (!\function_exists('curl_init')) {
            throw UcpException::profileUnreachable($url, 'PHP cURL extension is required for DNS-pinned UCP profile fetching');
        }

        $parts = parse_url($url);
        if (!\is_array($parts) || !isset($parts['scheme'])) {
            throw UcpException::invalidProfileUrl($url);
        }

        $scheme = strtolower((string) $parts['scheme']);
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $ipForCurl = str_contains($resolvedIp, ':') ? '[' . $resolvedIp . ']' : $resolvedIp;
        $resolveEntry = $host . ':' . $port . ':' . $ipForCurl;

        $headers = [];
        $ch = curl_init($url);
        if ($ch === false) {
            throw UcpException::profileUnreachable($url, 'curl_init failed');
        }

        curl_setopt_array($ch, [
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_FOLLOWLOCATION => false,
            \CURLOPT_MAXREDIRS => 0,
            \CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
            \CURLOPT_TIMEOUT => self::RESPONSE_TIMEOUT_SECONDS,
            \CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: Shopware-UCP/1.0',
            ],
            \CURLOPT_RESOLVE => [$resolveEntry],
            \CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$headers): int {
                $length = \strlen($line);
                $trimmed = trim($line);
                if ($trimmed === '' || !str_contains($trimmed, ':')) {
                    return $length;
                }
                [$name, $value] = explode(':', $trimmed, 2);
                $headers[strtolower(trim($name))][] = trim($value);

                return $length;
            },
        ]);

        if ($scheme === 'https') {
            curl_setopt($ch, \CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, \CURLOPT_SSL_VERIFYHOST, 2);
        }

        $body = curl_exec($ch);
        if (!\is_string($body)) {
            $err = curl_error($ch) ?: 'curl_exec returned false';
            curl_close($ch);
            throw UcpException::profileUnreachable($url, $err);
        }

        $statusCode = (int) curl_getinfo($ch, \CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return [
            'status_code' => $statusCode,
            'headers' => $headers,
            'body' => $body,
        ];
    }

    /**
     * Local Docker conformance runs start the mock platform profile on the host
     * as `http://localhost:<port>`, but Shopware resolves localhost inside the
     * container. In dev/test only, retry through Docker's host bridge.
     *
     * @return array{status_code: int, headers: array<string, list<string>>, body: string}
     */
    private function requestPinnedWithLocalhostFallback(string $url, string $host, string $resolvedIp): array
    {
        try {
            return $this->requestPinned($url, $host, $resolvedIp);
        } catch (\Throwable $e) {
            if (!$this->isConformanceMode() || !\in_array($host, ['localhost', '127.0.0.1'], true)) {
                throw $e;
            }

            $fallbackUrl = preg_replace('@://(?:localhost|127\.0\.0\.1)(?=[:/])@', '://host.docker.internal', $url, 1);
            if (!\is_string($fallbackUrl) || $fallbackUrl === $url) {
                throw $e;
            }

            return $this->requestPinned($fallbackUrl, 'host.docker.internal', gethostbyname('host.docker.internal'));
        }
    }

    private function isConformanceMode(): bool
    {
        if ($this->environment === 'prod') {
            return false;
        }

        return filter_var(getenv('UCP_CONFORMANCE_MODE') ?: ($_SERVER['UCP_CONFORMANCE_MODE'] ?? $_ENV['UCP_CONFORMANCE_MODE'] ?? false), \FILTER_VALIDATE_BOOL);
    }

    private function findCacheEntry(string $profileUri, Context $context): ?UcpPlatformProfileCacheEntity
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('profileUriHash', $this->hash($profileUri)))
            ->setLimit(1);

        $entity = $this->platformProfileCacheRepository->search($criteria, $context)->first();

        return $entity instanceof UcpPlatformProfileCacheEntity ? $entity : null;
    }

    /**
     * @param array<string, mixed> $profileJson
     */
    private function upsertCache(
        ?UcpPlatformProfileCacheEntity $existing,
        string $profileUri,
        array $profileJson,
        ?string $etag,
        int $ttlSeconds,
        Context $context
    ): void {
        $now = new \DateTimeImmutable();
        $expiresAt = $now->modify('+' . max(self::MIN_TTL_SECONDS, $ttlSeconds) . ' seconds');

        $this->platformProfileCacheRepository->upsert([[
            'id' => $existing?->getId() ?? Uuid::randomHex(),
            'profileUri' => $profileUri,
            'profileUriHash' => $this->hash($profileUri),
            'profileJson' => $profileJson,
            'etag' => $etag,
            'fetchedAt' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'expiresAt' => $expiresAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'verificationStatus' => UcpPlatformProfileCacheEntity::STATUS_VALID,
            'failureCount' => 0,
        ]], $context);
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private function extractTtl(array $headers): int
    {
        $cacheControl = $headers['cache-control'][0] ?? '';
        if (preg_match('/max-age=(\d+)/i', $cacheControl, $match) === 1) {
            return max(self::MIN_TTL_SECONDS, (int) $match[1]);
        }

        return self::DEFAULT_TTL_SECONDS;
    }

    private function hash(string $profileUri): string
    {
        return Hasher::hash($profileUri);
    }
}
