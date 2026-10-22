<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Log\Package;

/**
 * Authorization code grant that only accepts the `S256` PKCE code challenge method.
 * The `plain` method is registered unconditionally by the library, but is forbidden by RFC 9700.
 *
 * It also binds tokens to the RFC 8707 resource of the authorization request: the resource is stored
 * next to the authorization code, must match a resource named in the token request, and ends up in the
 * access token and its refresh token.
 *
 * @internal
 */
#[Package('framework')]
class ShopwareAuthCodeGrantType extends AuthCodeGrant
{
    public const TYPE = 'authorization_code';

    public const CODE_CHALLENGE_METHOD = 'S256';

    private ?string $resource = null;

    public function validateAuthorizationRequest(ServerRequestInterface $request): AuthorizationRequestInterface
    {
        $codeChallengeMethod = $request->getQueryParams()['code_challenge_method'] ?? null;

        if ($codeChallengeMethod !== self::CODE_CHALLENGE_METHOD) {
            throw OAuthServerException::invalidRequest(
                'code_challenge_method',
                'Code challenge method must be `' . self::CODE_CHALLENGE_METHOD . '`.'
            );
        }

        return parent::validateAuthorizationRequest($request);
    }

    public function completeAuthorizationRequest(AuthorizationRequestInterface $authorizationRequest): ResponseTypeInterface
    {
        $this->resource = $authorizationRequest instanceof ShopwareAuthorizationRequest ? $authorizationRequest->getResource() : null;

        try {
            return parent::completeAuthorizationRequest($authorizationRequest);
        } finally {
            $this->resource = null;
        }
    }

    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        \DateInterval $accessTokenTTL
    ): ResponseTypeInterface {
        $this->resource = $this->getGrantedResource($request);

        $requested = $this->getRequestParameter(OAuthResource::PARAMETER, $request);
        if (\is_string($requested) && ($this->resource === null || OAuthResource::normalize($requested) !== $this->resource)) {
            $this->resource = null;

            throw ApiException::oauthInvalidTarget('The resource does not match the authorization request.');
        }

        try {
            return parent::respondToAccessTokenRequest($request, $responseType, $accessTokenTTL);
        } finally {
            $this->resource = null;
        }
    }

    protected function createAuthorizationRequest(): AuthorizationRequestInterface
    {
        return new ShopwareAuthorizationRequest();
    }

    protected function issueAuthCode(
        \DateInterval $authCodeTTL,
        ClientEntityInterface $client,
        string $userIdentifier,
        ?string $redirectUri,
        array $scopes = []
    ): AuthCodeEntityInterface {
        $authCode = parent::issueAuthCode($authCodeTTL, $client, $userIdentifier, $redirectUri, $scopes);

        if ($this->resource !== null) {
            $this->getShopwareAuthCodeRepository()->bindResource($authCode->getIdentifier(), $this->resource);
        }

        return $authCode;
    }

    protected function issueAccessToken(
        \DateInterval $accessTokenTTL,
        ClientEntityInterface $client,
        ?string $userIdentifier,
        array $scopes = []
    ): AccessTokenEntityInterface {
        $accessToken = parent::issueAccessToken($accessTokenTTL, $client, $userIdentifier, $scopes);

        if ($this->resource !== null && $accessToken instanceof AccessToken) {
            $accessToken->setResource($this->resource);
        }

        return $accessToken;
    }

    protected function validateRedirectUri(string $redirectUri, ClientEntityInterface $client, ServerRequestInterface $request): void
    {
        try {
            parent::validateRedirectUri($redirectUri, $client, $request);
        } catch (OAuthServerException $exception) {
            // The library reports an invalid callback as a generic client authentication failure.
            throw ApiException::invalidOAuthRedirectUri($exception);
        }
    }

    /**
     * Reads the resource stored for the authorization code. The code itself is validated by the library afterwards.
     */
    private function getGrantedResource(ServerRequestInterface $request): ?string
    {
        $encryptedAuthCode = $this->getRequestParameter('code', $request);
        if (!\is_string($encryptedAuthCode) || !$this->authCodeRepository instanceof AuthCodeRepository) {
            return null;
        }

        try {
            $payload = json_decode($this->decrypt($encryptedAuthCode), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        $authCodeId = \is_array($payload) ? ($payload['auth_code_id'] ?? null) : null;

        return \is_string($authCodeId) ? $this->authCodeRepository->getResource($authCodeId) : null;
    }

    private function getShopwareAuthCodeRepository(): AuthCodeRepository
    {
        if (!$this->authCodeRepository instanceof AuthCodeRepository) {
            throw OAuthServerException::serverError('The authorization code repository cannot store a resource.');
        }

        return $this->authCodeRepository;
    }
}
