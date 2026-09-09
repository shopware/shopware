<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth;

use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\GrantTypeInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\ShopwareGrantType;
use Shopware\Core\Framework\Sso\ShopwarePasswordGrantType;
use Shopware\Core\Framework\Sso\ShopwareRefreshTokenGrantType;
use Shopware\Core\Framework\Sso\TokenService\ExternalTokenService;
use Shopware\Core\Framework\Sso\UserService\UserService;

/**
 * Builds the grant types that are enabled on the Admin API authorization server for each request.
 *
 * @internal
 */
#[Package('framework')]
final class GrantTypeFactory
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly RefreshTokenRepositoryInterface $refreshTokenRepository,
        private readonly AuthCodeRepositoryInterface $authCodeRepository,
        private readonly UserService $userService,
        private readonly ExternalTokenService $tokenService,
        private readonly ClockInterface $clock,
        private readonly string $refreshTokenTtl = 'P1W',
        private readonly string $authCodeTtl = 'PT5M',
    ) {
    }

    /**
     * @return list<GrantTypeInterface>
     */
    public function createGrantTypes(): array
    {
        $refreshTokenInterval = new \DateInterval($this->refreshTokenTtl);

        $passwordGrant = new ShopwarePasswordGrantType($this->userRepository, $this->refreshTokenRepository, $this->userService);
        $passwordGrant->setRefreshTokenTTL($refreshTokenInterval);

        $refreshTokenGrant = new ShopwareRefreshTokenGrantType($this->refreshTokenRepository, $this->userService, $this->tokenService);
        $refreshTokenGrant->setRefreshTokenTTL($refreshTokenInterval);

        $shopwareGrant = new ShopwareGrantType($this->refreshTokenRepository, $this->userService, $this->tokenService, $this->clock);
        $shopwareGrant->setRefreshTokenTTL($refreshTokenInterval);

        $authCodeGrant = new ShopwareAuthCodeGrantType(
            $this->authCodeRepository,
            $this->refreshTokenRepository,
            new \DateInterval($this->authCodeTtl)
        );
        $authCodeGrant->setRefreshTokenTTL($refreshTokenInterval);

        return [
            $passwordGrant,
            $refreshTokenGrant,
            new ClientCredentialsGrant(),
            $shopwareGrant,
            $authCodeGrant,
        ];
    }
}
