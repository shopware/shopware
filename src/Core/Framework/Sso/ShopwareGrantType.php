<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Sso;

use League\OAuth2\Server\Grant\AbstractGrant;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\RequestAccessTokenEvent;
use League\OAuth2\Server\RequestEvent;
use League\OAuth2\Server\RequestRefreshTokenEvent;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\TokenService\ExternalTokenService;
use Shopware\Core\Framework\Sso\UserService\ExternalAuthUser;
use Shopware\Core\Framework\Sso\UserService\UserService;

/**
 * @internal
 */
#[Package('framework')]
class ShopwareGrantType extends AbstractGrant
{
    public const TYPE = 'shopware_grant';

    public function __construct(
        RefreshTokenRepositoryInterface $refreshTokenRepository,
        private readonly UserService $userService,
        private readonly ExternalTokenService $tokenService,
    ) {
        $this->refreshTokenRepository = $refreshTokenRepository;
    }

    public function getIdentifier(): string
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

        // take the shorter token TTL to avoid that the external token gets invalid
        $lowerTTL = TokenTimeToLive::getLowerTTL($accessTokenTTL, (new \DateTimeImmutable())->diff($user->expiry));

        $accessToken = $this->issueAccessToken($lowerTTL, $client, $userIdentifier, $finalizedScopes);
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
        if ($code === null) {
            throw SsoException::noCodeProvided();
        }

        try {
            $token = $this->tokenService->getUserToken($code);
            $user = $this->userService->getAndUpdateUserByExternalToken($token);
        } catch (\Throwable $exception) {
            $this->getEmitter()->emit(new RequestEvent(RequestEvent::USER_AUTHENTICATION_FAILED, $request));

            throw $exception;
        }

        return $user;
    }
}
