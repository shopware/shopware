<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth;

use Lcobucci\JWT\Configuration;
use League\OAuth2\Server\Grant\GrantTypeInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\AdminAuth\Mfa\MfaChallengeStore;
use Shopware\Core\Framework\AdminAuth\Mfa\MfaPolicyService;
use Shopware\Core\Framework\AdminAuth\Mfa\MfaRateLimiter;
use Shopware\Core\Framework\AdminAuth\OAuth\Grant\AdminPrimaryGrant;
use Shopware\Core\Framework\AdminAuth\OAuth\Grant\AdminSecondFactorGrant;
use Shopware\Core\Framework\AdminAuth\OAuth\Verifier\PrimaryVerifierInterface;
use Shopware\Core\Framework\AdminAuth\OAuth\Verifier\SecondFactorVerifierInterface;
use Shopware\Core\Framework\Api\EventListener\Authentication\ApiAuthenticationListener;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Integration\Core\Framework\AdminAuth\AdminPrimaryGrantTest;

/**
 * Builds the admin-auth OAuth2 grants with all their dependencies so the
 * {@see ApiAuthenticationListener} only has
 * to register them on the authorization server.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see AdminPrimaryGrantTest
 */
#[Package('framework')]
class AdminAuthGrantFactory
{
    /**
     * @param iterable<PrimaryVerifierInterface> $primaryVerifiers
     * @param iterable<SecondFactorVerifierInterface> $secondFactorVerifiers
     */
    public function __construct(
        private readonly iterable $primaryVerifiers,
        private readonly iterable $secondFactorVerifiers,
        private readonly RefreshTokenRepositoryInterface $refreshTokenRepository,
        private readonly MfaPolicyService $mfaPolicy,
        private readonly MfaChallengeStore $challengeStore,
        private readonly Configuration $jwtConfiguration,
        private readonly MfaRateLimiter $rateLimiter,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return list<GrantTypeInterface>
     */
    public function createGrants(\DateInterval $refreshTokenTtl): array
    {
        $primaryGrant = new AdminPrimaryGrant(
            $this->primaryVerifiers,
            $this->refreshTokenRepository,
            $this->mfaPolicy,
            $this->challengeStore
        );
        $primaryGrant->setRefreshTokenTTL($refreshTokenTtl);

        $secondFactorGrant = new AdminSecondFactorGrant(
            $this->secondFactorVerifiers,
            $this->refreshTokenRepository,
            $this->challengeStore,
            $this->jwtConfiguration,
            $this->rateLimiter,
            $this->clock
        );
        $secondFactorGrant->setRefreshTokenTTL($refreshTokenTtl);

        return [$primaryGrant, $secondFactorGrant];
    }
}
