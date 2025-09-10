<?php declare(strict_types=1);

namespace Shopware\Administration\Login;

use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;
use Shopware\Administration\Login\TokenService\ExternalTokenService;
use Shopware\Administration\Login\UserService\UserService;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class ShopwareRefreshTokenGrantType extends RefreshTokenGrant
{
    public function __construct(
        RefreshTokenRepositoryInterface $refreshTokenRepository,
        private readonly UserService $userService,
        private readonly ExternalTokenService $tokenService,
    ) {
        parent::__construct($refreshTokenRepository);
    }

    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        \DateInterval $accessTokenTTL
    ): ResponseTypeInterface {
        $client = $this->validateClient($request);
        $oldRefreshToken = $this->validateOldRefreshToken($request, $client->getIdentifier());

        $userId = $oldRefreshToken['user_id'];

        $ssoRefreshToken = $this->userService->getRefreshToken($userId);
        if (\is_string($ssoRefreshToken)) {
            $newSsoTokenResult = $this->tokenService->getUserTokenByRefreshToken($ssoRefreshToken);
            $this->userService->updateUserToken($userId, $newSsoTokenResult);

            // take the shorter token TTL to avoid that the external token gets invalid
            $accessTokenTTL = TokenTimeToLive::getLowerTTL($accessTokenTTL, new \DateInterval('PT' . $newSsoTokenResult->expiresIn . 'S'));
        }

        return parent::respondToAccessTokenRequest($request, $responseType, $accessTokenTTL);
    }
}
