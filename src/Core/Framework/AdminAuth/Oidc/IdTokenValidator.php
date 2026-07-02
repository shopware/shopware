<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\Oidc;

use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Ecdsa\Sha256 as EcdsaSha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256 as RsaSha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;
use phpseclib3\Crypt\PublicKeyLoader;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\AdminAuth\AdminAuthException;
use Shopware\Core\Framework\AdminAuth\Provider\AdminAuthProvider;
use Shopware\Core\Framework\Log\Package;

/**
 * Validates a provider-issued id_token: parses the JWT, verifies its signature against the
 * provider JWKS (RS256 / ES256) and checks the standard OIDC claims plus the per-login nonce.
 *
 * Uses lcobucci/jwt as the JWT primitive and phpseclib for the JWK -> PEM conversion.
 *
 * @internal
 */
#[Package('framework')]
class IdTokenValidator
{
    public function __construct(
        private readonly OidcClient $oidcClient,
        private readonly OidcDiscoveryService $discoveryService,
        private readonly ClockInterface $clock,
    ) {
    }

    public function validate(AdminAuthProvider $provider, string $idToken, string $expectedNonce): OidcClaims
    {
        if ($idToken === '') {
            throw AdminAuthException::invalidIdToken('the token could not be parsed');
        }

        try {
            $token = (new Parser(new JoseEncoder()))->parse($idToken);
        } catch (\Throwable) {
            throw AdminAuthException::invalidIdToken('the token could not be parsed');
        }

        if (!$token instanceof Plain) {
            throw AdminAuthException::invalidIdToken('the token is not a valid JWS');
        }

        $this->verifySignature($provider, $token);
        $this->verifyClaims($provider, $token, $expectedNonce);

        $claims = $token->claims();

        return new OidcClaims(
            sub: (string) $claims->get('sub'),
            email: $claims->has('email') ? (string) $claims->get('email') : null,
            emailVerified: (bool) $claims->get('email_verified', false),
            name: $claims->has('name') ? (string) $claims->get('name') : null,
            preferredUsername: $claims->has('preferred_username') ? (string) $claims->get('preferred_username') : null,
            claims: $claims->all(),
        );
    }

    private function verifySignature(AdminAuthProvider $provider, Plain $token): void
    {
        $kid = $token->headers()->get('kid');
        $alg = (string) $token->headers()->get('alg');
        $jwks = $this->oidcClient->getJwks($provider);

        $signer = $this->signerForAlg($alg);
        if ($signer === null) {
            throw AdminAuthException::invalidIdToken(\sprintf('unsupported signature algorithm "%s"', $alg));
        }

        $validator = new Validator();

        foreach ($jwks as $jwk) {
            if (!\is_array($jwk)) {
                continue;
            }

            // Match the key by kid when the token carries one; otherwise try every candidate key.
            if ($kid !== null && isset($jwk['kid']) && $jwk['kid'] !== $kid) {
                continue;
            }

            $pem = $this->jwkToPem($jwk);
            if ($pem === null || $pem === '') {
                continue;
            }

            try {
                $validator->assert($token, new SignedWith($signer, InMemory::plainText($pem)));

                return;
            } catch (\Throwable) {
                // try the next key
            }
        }

        throw AdminAuthException::invalidIdToken('the signature could not be verified against the provider JWKS');
    }

    private function verifyClaims(AdminAuthProvider $provider, Plain $token, string $expectedNonce): void
    {
        $claims = $token->claims();

        if ($token->isExpired($this->clock->now())) {
            throw AdminAuthException::invalidIdToken('the token is expired');
        }

        // Audience must contain our client id.
        $aud = $claims->get('aud');
        $audiences = \is_array($aud) ? $aud : [$aud];
        if (!\in_array($provider->clientId, $audiences, true)) {
            throw AdminAuthException::invalidIdToken('the audience does not match the configured client id');
        }

        // Issuer (when known via configuration or discovery) must match.
        $issuer = $this->discoveryService->resolveEndpoints($provider)['issuer'];
        if ($issuer !== null && (string) $claims->get('iss') !== $issuer) {
            throw AdminAuthException::invalidIdToken('issuer mismatch');
        }

        // Nonce binds the token to this login attempt (replay protection).
        if (!hash_equals($expectedNonce, (string) $claims->get('nonce'))) {
            throw AdminAuthException::invalidIdToken('nonce mismatch');
        }

        if (!$claims->has('sub') || (string) $claims->get('sub') === '') {
            throw AdminAuthException::invalidIdToken('the token has no subject');
        }
    }

    /**
     * @param array<string, mixed> $jwk
     */
    private function jwkToPem(array $jwk): ?string
    {
        try {
            $pem = PublicKeyLoader::load(json_encode($jwk, \JSON_THROW_ON_ERROR))->toString('PKCS8');

            return \is_string($pem) ? $pem : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function signerForAlg(string $alg): ?Signer
    {
        return match ($alg) {
            'RS256' => new RsaSha256(),
            'ES256' => new EcdsaSha256(),
            default => null,
        };
    }
}
