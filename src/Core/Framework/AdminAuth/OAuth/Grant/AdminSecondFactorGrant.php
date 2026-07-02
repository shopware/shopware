<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\OAuth\Grant;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\UnencryptedToken;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AbstractGrant;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\RequestAccessTokenEvent;
use League\OAuth2\Server\RequestEvent;
use League\OAuth2\Server\RequestRefreshTokenEvent;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ServerRequestInterface;
use Shopware\Core\Framework\AdminAuth\Mfa\MfaChallenge;
use Shopware\Core\Framework\AdminAuth\Mfa\MfaChallengeStore;
use Shopware\Core\Framework\AdminAuth\Mfa\MfaRateLimiter;
use Shopware\Core\Framework\AdminAuth\OAuth\Verifier\SecondFactorVerifierInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Integration\Core\Framework\AdminAuth\AdminSecondFactorGrantTest;

/**
 * Completes an MFA login: given the pending token (Authorization: Bearer) plus a second-factor proof,
 * it validates the bound challenge, runs the matching second-factor verifier and — on success — issues
 * the full access + refresh token.
 *
 * The pending token is parsed only to read its jti/sub; its (MFA-only) scopes are irrelevant here. The
 * challenge binding (jti), single-use consumption, expiry and attempt cap defend against replay.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see AdminSecondFactorGrantTest
 */
#[Package('framework')]
class AdminSecondFactorGrant extends AbstractGrant
{
    public const TYPE = 'admin_second_factor';
    private const MAX_ATTEMPTS = 5;

    /**
     * @param iterable<SecondFactorVerifierInterface> $verifiers
     */
    public function __construct(
        private readonly iterable $verifiers,
        RefreshTokenRepositoryInterface $refreshTokenRepository,
        private readonly MfaChallengeStore $challengeStore,
        private readonly Configuration $jwtConfiguration,
        private readonly MfaRateLimiter $rateLimiter,
        private readonly ClockInterface $clock,
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

        $this->rateLimiter->ensureAccepted($this->clientIp($request));

        [$jti, $userIdentifier] = $this->parsePendingToken($request);

        $challenge = $this->challengeStore->findByJti($jti);
        $now = $this->clock->now();

        if (
            $challenge === null
            || $challenge->consumed
            || $challenge->isExpired($now)
            || $challenge->userId !== $userIdentifier
        ) {
            throw OAuthServerException::accessDenied('Invalid or expired MFA challenge.');
        }

        if ($challenge->attempts >= self::MAX_ATTEMPTS) {
            $this->challengeStore->consume($challenge->id);

            throw OAuthServerException::accessDenied('Too many second-factor attempts.');
        }

        $this->verifySecondFactor($request, $challenge, $userIdentifier);

        // Success: burn the challenge and issue a full token (scopes identical to a primary login).
        $this->challengeStore->consume($challenge->id);

        $scopes = $this->scopeRepository->finalizeScopes(
            [],
            AdminPrimaryGrant::TYPE,
            $client,
            $userIdentifier
        );

        $accessToken = $this->issueAccessToken($accessTokenTTL, $client, $userIdentifier, $scopes);
        $this->getEmitter()->emit(new RequestAccessTokenEvent(RequestEvent::ACCESS_TOKEN_ISSUED, $request, $accessToken));
        $responseType->setAccessToken($accessToken);

        $refreshToken = $this->issueRefreshToken($accessToken);
        if ($refreshToken !== null) {
            $this->getEmitter()->emit(new RequestRefreshTokenEvent(RequestEvent::REFRESH_TOKEN_ISSUED, $request, $refreshToken));
            $responseType->setRefreshToken($refreshToken);
        }

        return $responseType;
    }

    private function verifySecondFactor(ServerRequestInterface $request, MfaChallenge $challenge, string $userIdentifier): void
    {
        $method = $this->getRequestParameter('method', $request, '') ?? '';

        if (!\in_array($method, $challenge->allowedMethods, true)) {
            throw OAuthServerException::invalidRequest('method', 'This factor is not allowed for the challenge.');
        }

        foreach ($this->verifiers as $verifier) {
            if (!$verifier->supports($method)) {
                continue;
            }

            try {
                $verifier->verifySecondFactor($userIdentifier, (array) $request->getParsedBody(), $challenge);

                return;
            } catch (OAuthServerException $exception) {
                $this->challengeStore->incrementAttempts($challenge->id);
                $this->getEmitter()->emit(new RequestEvent(RequestEvent::USER_AUTHENTICATION_FAILED, $request));

                throw $exception;
            }
        }

        throw OAuthServerException::invalidRequest('method', \sprintf('Unsupported second factor "%s".', $method));
    }

    private function clientIp(ServerRequestInterface $request): ?string
    {
        $server = $request->getServerParams();
        $ip = $server['REMOTE_ADDR'] ?? null;

        return \is_string($ip) ? $ip : null;
    }

    /**
     * @return array{0: string, 1: string} [jti, userId]
     */
    private function parsePendingToken(ServerRequestInterface $request): array
    {
        $header = $request->getHeaderLine('authorization');
        $jwt = trim((string) preg_replace('/^\s*Bearer\s/i', '', $header));

        if ($jwt === '') {
            throw OAuthServerException::accessDenied('Missing pending token.');
        }

        try {
            $token = $this->jwtConfiguration->parser()->parse($jwt);
            $constraints = $this->jwtConfiguration->validationConstraints();
            $this->jwtConfiguration->validator()->assert($token, ...$constraints);
        } catch (\Throwable $exception) {
            throw OAuthServerException::accessDenied('Invalid pending token.', null, $exception);
        }

        if (!$token instanceof UnencryptedToken) {
            throw OAuthServerException::accessDenied('Invalid pending token.');
        }

        $claims = $token->claims();
        $jti = (string) $claims->get('jti');
        $sub = (string) $claims->get('sub');

        if ($jti === '' || $sub === '') {
            throw OAuthServerException::accessDenied('Invalid pending token.');
        }

        return [$jti, $sub];
    }
}
