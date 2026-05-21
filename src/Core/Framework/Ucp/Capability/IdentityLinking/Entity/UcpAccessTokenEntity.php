<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Entity;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Ecdsa\Sha256 as EcdsaSha256;
use Lcobucci\JWT\Signer\Ecdsa\Sha384 as EcdsaSha384;
use Lcobucci\JWT\Signer\Key\InMemory;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\UcpException;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * UCP access token entity.
 *
 * League/OAuth2-server's {@see AccessTokenTrait::initJwtConfiguration()}
 * is hardcoded to RSA (`Lcobucci\JWT\Signer\Rsa\Sha256`). UCP mandates
 * **asymmetric ECDSA** (ES256 / ES384), so we override that method here
 * and pick the matching `Lcobucci\JWT\Signer\Ecdsa\*` signer based on the
 * active UCP signing key's algorithm. The algorithm is propagated from
 * {@see UcpAuthorizationServerFactory::create()} via a static setter just
 * before each token is issued — the request is single-threaded by design,
 * so this is race-free.
 *
 * The same convention applies to the verifying side: see
 * {@see UcpResourceServerFactory} which constructs a `BearerTokenValidator`
 * that uses the matching ECDSA signer.
 *
 * @internal
 */
#[Package('framework')]
class UcpAccessTokenEntity implements AccessTokenEntityInterface
{
    use AccessTokenTrait {
        initJwtConfiguration as private leagueInitJwtConfiguration;
    }
    use EntityTrait;
    use TokenEntityTrait;

    /**
     * The currently-active signing algorithm for this request.
     *
     * One of `ES256`, `ES384`. Set by
     * {@see UcpAuthorizationServerFactory::create()} just before the JWT
     * is built. Defaults to ES256 (UCP-required minimum).
     */
    private static string $signingAlgorithm = 'ES256';

    public static function setSigningAlgorithm(string $algorithm): void
    {
        if (!\in_array($algorithm, ['ES256', 'ES384'], true)) {
            throw UcpException::unsupportedAlgorithm(
                $algorithm,
                'access-token signer',
                ['ES256', 'ES384']
            );
        }
        self::$signingAlgorithm = $algorithm;
    }

    public static function getSigningAlgorithm(): string
    {
        return self::$signingAlgorithm;
    }

    /**
     * Override League's RSA-only `initJwtConfiguration` with one that picks
     * the correct ECDSA signer for the configured algorithm.
     *
     * Mirrors League's implementation byte-for-byte except for the signer
     * choice — same Configuration::forAsymmetricSigner call, same dummy
     * verification key (the verifying side derives the public key from the
     * private one in our own ResourceServer).
     */
    public function initJwtConfiguration(): void
    {
        $privateKeyContents = $this->privateKey->getKeyContents();
        if ($privateKeyContents === '') {
            throw UcpException::tokenEntityInvalid('private key is empty');
        }

        $signer = self::resolveSigner(self::$signingAlgorithm);

        $this->jwtConfiguration = Configuration::forAsymmetricSigner(
            $signer,
            InMemory::plainText($privateKeyContents, $this->privateKey->getPassPhrase() ?? ''),
            // Verifier-side key — League constructs a builder but never
            // verifies on this path; the resource server has its own config.
            InMemory::plainText('empty', 'empty')
        );
    }

    /**
     * Resolves an Lcobucci/JWT ECDSA signer instance for the named JWS
     * algorithm. Public so the matching ResourceServer can use the same
     * resolver.
     */
    public static function resolveSigner(string $algorithm): Signer
    {
        return match ($algorithm) {
            'ES256' => new EcdsaSha256(),
            'ES384' => new EcdsaSha384(),
            default => throw UcpException::unsupportedAlgorithm(
                $algorithm,
                'jwt-signer',
                ['ES256', 'ES384']
            ),
        };
    }
}
