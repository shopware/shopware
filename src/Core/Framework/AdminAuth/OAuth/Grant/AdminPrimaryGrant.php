<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\OAuth\Grant;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AbstractGrant;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\RequestAccessTokenEvent;
use League\OAuth2\Server\RequestEvent;
use League\OAuth2\Server\RequestRefreshTokenEvent;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;
use Shopware\Core\Framework\AdminAuth\Mfa\MfaChallengeStore;
use Shopware\Core\Framework\AdminAuth\Mfa\MfaPolicyService;
use Shopware\Core\Framework\AdminAuth\OAuth\Scope\MfaPendingScope;
use Shopware\Core\Framework\AdminAuth\OAuth\Verifier\PrimaryVerifierInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Integration\Core\Framework\AdminAuth\AdminPrimaryGrantTest;

/**
 * Custom OAuth2 grant that drives the admin login through pluggable primary verifiers and the MFA
 * policy.
 *
 * If the verified user needs no second factor, it issues a full access token identical to the core
 * password grant. If a second factor is required, it issues a *powerless* pending token that carries
 * only MFA scopes (the {@see MfaPendingScope} plus an `admin-mfa-challenge:<id>` and
 * `admin-mfa-methods:<csv>` marker), and records a single-use challenge bound to the token's jti. The
 * login is then completed via {@see AdminSecondFactorGrant}.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see AdminPrimaryGrantTest
 */
#[Package('framework')]
class AdminPrimaryGrant extends AbstractGrant
{
    public const TYPE = 'admin_primary';
    public const CHALLENGE_SCOPE_PREFIX = 'admin-mfa-challenge:';
    public const METHODS_SCOPE_PREFIX = 'admin-mfa-methods:';

    /**
     * @param iterable<PrimaryVerifierInterface> $primaryVerifiers
     */
    public function __construct(
        private readonly iterable $primaryVerifiers,
        RefreshTokenRepositoryInterface $refreshTokenRepository,
        private readonly MfaPolicyService $mfaPolicy,
        private readonly MfaChallengeStore $challengeStore,
    ) {
        $this->refreshTokenRepository = $refreshTokenRepository;
    }

    public function getIdentifier(): string
    {
        return self::TYPE;
    }

    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        \DateInterval $accessTokenTTL
    ): ResponseTypeInterface {
        $client = $this->getClientEntityOrFail('administration', $request);
        $scopes = $this->validateScopes($this->getRequestParameter('scope', $request, $this->defaultScope));

        $userIdentifier = $this->verifyPrimaryFactor($request);

        $availableFactors = $this->mfaPolicy->availableFactors($userIdentifier);

        if ($availableFactors !== []) {
            return $this->issuePendingToken($request, $responseType, $accessTokenTTL, $client, $userIdentifier, $availableFactors);
        }

        // No second factor required: behave exactly like the core password grant. The scope repository
        // maps our grant identifier to the password grant so the user gets write/user-verified.
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

    /**
     * @param list<string> $availableFactors
     */
    private function issuePendingToken(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        \DateInterval $accessTokenTTL,
        ClientEntityInterface $client,
        string $userIdentifier,
        array $availableFactors
    ): ResponseTypeInterface {
        // Create the challenge first (random id), build a pending token carrying only MFA scopes
        // (so it is powerless against the real API), then bind the challenge to the token's jti.
        $challengeId = $this->challengeStore->create(
            $userIdentifier,
            $availableFactors,
            $this->mfaPolicy->challengeTtl()
        );

        $scopes = [
            new MfaPendingScope(),
            new MfaPendingScope(self::CHALLENGE_SCOPE_PREFIX . $challengeId),
            new MfaPendingScope(self::METHODS_SCOPE_PREFIX . implode(',', $availableFactors)),
        ];

        $accessToken = $this->issueAccessToken($accessTokenTTL, $client, $userIdentifier, $scopes);
        $this->challengeStore->bindJti($challengeId, $accessToken->getIdentifier());

        $this->getEmitter()->emit(new RequestAccessTokenEvent(RequestEvent::ACCESS_TOKEN_ISSUED, $request, $accessToken));
        $responseType->setAccessToken($accessToken);

        // A pending login deliberately gets no refresh token.
        return $responseType;
    }

    /**
     * @return string the verified user's id as a hex string
     */
    private function verifyPrimaryFactor(ServerRequestInterface $request): string
    {
        $method = $this->getRequestParameter('method', $request, 'password') ?? 'password';

        foreach ($this->primaryVerifiers as $verifier) {
            if ($verifier->supports($method)) {
                try {
                    return $verifier->verifyPrimary((array) $request->getParsedBody());
                } catch (OAuthServerException $exception) {
                    $this->getEmitter()->emit(new RequestEvent(RequestEvent::USER_AUTHENTICATION_FAILED, $request));

                    throw $exception;
                }
            }
        }

        throw OAuthServerException::invalidRequest('method', \sprintf('Unsupported login method "%s".', $method));
    }
}
