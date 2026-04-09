<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Sso;

use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\TokenService\ExternalTokenService;
use Shopware\Core\Framework\Sso\UserService\ExternalAuthUser;
use Shopware\Core\Framework\Sso\UserService\Token;
use Shopware\Core\Framework\Sso\UserService\UserService;

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

        $oAuthUser = $this->userService->searchOAuthUserByUserId($userId);
        if ($oAuthUser instanceof ExternalAuthUser && $oAuthUser->token instanceof Token) {
            $newSsoTokenResult = $this->tokenService->getUserTokenByRefreshToken($oAuthUser->token->refreshToken);
            $oAuthUser = $this->userService->updateOAuthUserWithNewToken($oAuthUser, $newSsoTokenResult);
            $this->userService->saveOAuthUser($oAuthUser);

            // take the shorter token TTL to avoid that the external token gets invalid
            $accessTokenTTL = TokenTimeToLive::getLowerTTL($accessTokenTTL, new \DateInterval('PT' . $newSsoTokenResult->expiresIn . 'S'));
        }

        return parent::respondToAccessTokenRequest($request, $responseType, $accessTokenTTL);
    }
}
