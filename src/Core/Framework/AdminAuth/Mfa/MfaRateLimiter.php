<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\Mfa;

use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Integration\Core\Framework\AdminAuth\AdminSecondFactorGrantTest;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * IP-keyed rate limiter for the second-factor and WebAuthn ceremonies.
 *
 * The per-challenge attempt counter ({@see MfaChallengeStore}) already caps tries against a single
 * challenge; this adds a broader sliding-window cap per client IP so an attacker cannot churn through
 * fresh challenges to brute-force codes. Throws an OAuth access-denied error when the limit is hit.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see AdminSecondFactorGrantTest
 */
#[Package('framework')]
class MfaRateLimiter
{
    private readonly RateLimiterFactory $factory;

    public function __construct(CacheItemPoolInterface $cache)
    {
        $this->factory = new RateLimiterFactory(
            [
                'id' => 'admin_auth_mfa',
                'policy' => 'sliding_window',
                'limit' => 10,
                'interval' => '5 minutes',
            ],
            new CacheStorage($cache)
        );
    }

    public function ensureAccepted(?string $clientIp): void
    {
        $limit = $this->factory->create($clientIp ?? 'unknown')->consume();

        if (!$limit->isAccepted()) {
            throw OAuthServerException::accessDenied('Too many authentication attempts. Try again later.');
        }
    }
}
