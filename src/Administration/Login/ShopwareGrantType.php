<?php declare(strict_types=1);

namespace Shopware\Administration\Login;

use League\OAuth2\Server\Grant\AbstractGrant;
use League\OAuth2\Server\Grant\GrantTypeInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\RequestAccessTokenEvent;
use League\OAuth2\Server\RequestEvent;
use League\OAuth2\Server\RequestRefreshTokenEvent;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;
use Shopware\Administration\Login\Exception\LoginException;
use Shopware\Administration\Login\TokenService\ExternalTokenService;
use Shopware\Administration\Login\UserService\ExternalAuthUser;
use Shopware\Administration\Login\UserService\UserService;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class ShopwareGrantType extends AbstractGrant implements GrantTypeInterface
{
    private const TYPE = 'shopware_grant';

    public function __construct(
        RefreshTokenRepositoryInterface $refreshTokenRepository,
        private readonly UserService $userService,
        private readonly ExternalTokenService $tokenService
    ) {
        $this->refreshTokenRepository = $refreshTokenRepository;
    }

    public function getIdentifier()
    {
        return self::TYPE;
    }

    public function respondToAccessTokenRequest(ServerRequestInterface $request, ResponseTypeInterface $responseType, \DateInterval $accessTokenTTL): ResponseTypeInterface
    {
        $client = $this->getClientEntityOrFail('administration', $request);
        $scopes = $this->validateScopes($this->getRequestParameter('scope', $request, $this->defaultScope));
        $user = $this->validateUser($request);

        $userIdentifier = $user->getIdentifier();

        $finalizedScopes = $this->scopeRepository->finalizeScopes($scopes, $this->getIdentifier(), $client, $userIdentifier);

        $accessToken = $this->issueAccessToken($accessTokenTTL, $client, $userIdentifier, $finalizedScopes);
        $this->getEmitter()->emit(new RequestAccessTokenEvent(RequestEvent::ACCESS_TOKEN_ISSUED, $request, $accessToken));
        $responseType->setAccessToken($accessToken);

        $refreshToken = $this->issueRefreshToken($accessToken);

        if ($refreshToken !== null) {
            $this->getEmitter()->emit(new RequestRefreshTokenEvent(RequestEvent::REFRESH_TOKEN_ISSUED, $request, $refreshToken));
            $responseType->setRefreshToken($refreshToken);
        }

        return $responseType;
    }

    private function validateUser(ServerRequestInterface $request): ExternalAuthUser
    {
        $code = $this->getRequestParameter('code', $request);

        $token = $this->tokenService->getUserToken((string) $code);
        $user = $this->userService->getUser($token->idToken, $token->refreshToken);

        if (!$user instanceof ExternalAuthUser) {
            $this->getEmitter()->emit(new RequestEvent(RequestEvent::USER_AUTHENTICATION_FAILED, $request));
            // TODO: Ensure correct exception is thrown
            throw LoginException::userNotFound();
        }

        return $user;
    }
}
