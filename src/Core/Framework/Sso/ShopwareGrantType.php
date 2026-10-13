<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Sso;

use League\OAuth2\Server\Grant\AbstractGrant;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\RequestAccessTokenEvent;
use League\OAuth2\Server\RequestEvent;
use League\OAuth2\Server\RequestRefreshTokenEvent;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ServerRequestInterface;
use Shopware\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
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

    /**
     * How old the identity provider authentication may be for the token to carry the `user-verified` scope.
     * The code exchange follows the login within seconds; the margin covers clock skew between both sides.
     */
    public const VERIFIED_LOGIN_MAX_AGE_SECONDS = 300;

    public function __construct(
        RefreshTokenRepositoryInterface $refreshTokenRepository,
        private readonly UserService $userService,
        private readonly ExternalTokenService $tokenService,
        private readonly ClockInterface $clock,
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

        // The scope repository reserves `user-verified` for the password grant. A login the identity
        // provider just performed is the SSO counterpart of re-entering the password.
        if ($this->isVerifiedLogin($user)) {
            $finalizedScopes[] = new UserVerifiedScope();
        }

        // take the shorter token TTL to avoid that the external token gets invalid
        $lowerTTL = TokenTimeToLive::getLowerTTL($accessTokenTTL, $this->clock->now()->diff($user->expiry));

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

    /**
     * An identity provider that does not report `auth_time` cannot be told apart from a fresh login,
     * so its sessions keep the verification they have today.
     */
    private function isVerifiedLogin(ExternalAuthUser $user): bool
    {
        if ($user->authenticatedAt === null) {
            return true;
        }

        return $this->clock->now()->getTimestamp() - $user->authenticatedAt->getTimestamp() <= self::VERIFIED_LOGIN_MAX_AGE_SECONDS;
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
