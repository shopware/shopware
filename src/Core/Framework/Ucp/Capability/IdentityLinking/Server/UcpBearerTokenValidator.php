<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Server;

use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Exception as JwtException;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use League\OAuth2\Server\AuthorizationValidators\AuthorizationValidatorInterface;
use League\OAuth2\Server\AuthorizationValidators\BearerTokenValidator;
use League\OAuth2\Server\CryptKeyInterface;
use League\OAuth2\Server\CryptTrait;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Entity\UcpAccessTokenEntity;
use Shopware\Core\Framework\Ucp\UcpException;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Drop-in replacement for League's {@see BearerTokenValidator}
 * that validates access tokens with the configured ECDSA signer (ES256 / ES384)
 * instead of the library's hardcoded RSA Sha256.
 *
 * Behaviour is otherwise identical — claim parsing, revocation check, request
 * attribute injection — so existing League middleware (`ResourceServerMiddleware`)
 * keeps working without modification.
 *
 * @internal
 */
#[Package('framework')]
class UcpBearerTokenValidator implements AuthorizationValidatorInterface
{
    use CryptTrait;

    private CryptKeyInterface $publicKey;

    private Configuration $jwtConfiguration;

    public function __construct(
        private readonly AccessTokenRepositoryInterface $accessTokenRepository,
        private readonly string $algorithm = 'ES256',
        private readonly ?\DateInterval $jwtValidAtDateLeeway = null,
        private readonly ?string $expectedIssuer = null,
        private readonly ?string $expectedAudience = null,
    ) {
    }

    public function setPublicKey(CryptKeyInterface $key): void
    {
        $this->publicKey = $key;
        $this->initJwtConfiguration();
    }

    public function validateAuthorization(ServerRequestInterface $request): ServerRequestInterface
    {
        if (!$request->hasHeader('authorization')) {
            throw OAuthServerException::accessDenied('Missing "Authorization" header');
        }

        $header = $request->getHeader('authorization');
        $jwt = trim((string) preg_replace('/^\s*Bearer\s/', '', $header[0]));

        if ($jwt === '') {
            throw OAuthServerException::accessDenied('Missing "Bearer" token');
        }

        try {
            $token = $this->jwtConfiguration->parser()->parse($jwt);
        } catch (JwtException $exception) {
            throw OAuthServerException::accessDenied($exception->getMessage(), null, $exception);
        }

        try {
            $constraints = $this->jwtConfiguration->validationConstraints();
            $this->jwtConfiguration->validator()->assert($token, ...$constraints);
        } catch (RequiredConstraintsViolated $exception) {
            throw OAuthServerException::accessDenied('Access token could not be verified', null, $exception);
        }

        if (!$token instanceof UnencryptedToken) {
            throw OAuthServerException::accessDenied('Access token is not an instance of UnencryptedToken');
        }

        $claims = $token->claims();

        if ($this->accessTokenRepository->isAccessTokenRevoked($claims->get('jti'))) {
            throw OAuthServerException::accessDenied('Access token has been revoked');
        }

        $audience = $claims->get('aud');
        $clientId = \is_array($audience) ? ($audience[0] ?? '') : (string) $audience;

        return $request
            ->withAttribute('oauth_access_token_id', $claims->get('jti'))
            ->withAttribute('oauth_client_id', $clientId)
            ->withAttribute('oauth_user_id', $claims->get('sub'))
            ->withAttribute('oauth_scopes', $claims->get('scopes'));
    }

    private function initJwtConfiguration(): void
    {
        $signer = UcpAccessTokenEntity::resolveSigner($this->algorithm);

        // The signer is asymmetric — symmetric "dummy" Configuration is used
        // here only as the validator factory; the actual SignedWith constraint
        // below carries the real verifier key. This matches League's own
        // pattern exactly, just with the right signer class.
        $this->jwtConfiguration = Configuration::forSymmetricSigner(
            $signer,
            InMemory::plainText('empty', 'empty')
        );

        $publicKeyContents = $this->publicKey->getKeyContents();
        if ($publicKeyContents === '') {
            throw UcpException::oauthBearerTokenInvalid('public key is empty');
        }

        $clock = new SystemClock(new \DateTimeZone(date_default_timezone_get()));

        $constraints = [
            new LooseValidAt($clock, $this->jwtValidAtDateLeeway),
            new SignedWith(
                $signer,
                InMemory::plainText($publicKeyContents, $this->publicKey->getPassPhrase() ?? '')
            ),
        ];

        // Defense-in-depth: pin token iss and aud claims. Without these a
        // valid token issued by *another* Shopware UCP sales channel (same
        // crypto pipeline) could be replayed against ours.
        if ($this->expectedIssuer !== null && $this->expectedIssuer !== '') {
            $constraints[] = new IssuedBy($this->expectedIssuer);
        }
        if ($this->expectedAudience !== null && $this->expectedAudience !== '') {
            $constraints[] = new PermittedFor($this->expectedAudience);
        }

        $this->jwtConfiguration = $this->jwtConfiguration->withValidationConstraints(...$constraints);
    }
}
