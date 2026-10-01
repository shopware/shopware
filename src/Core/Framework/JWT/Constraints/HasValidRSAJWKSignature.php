<?php declare(strict_types=1);

namespace Shopware\Core\Framework\JWT\Constraints;

use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Signer\Rsa\Sha384;
use Lcobucci\JWT\Signer\Rsa\Sha512;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\Validation\Constraint;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Math\BigInteger;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\JWT\JWTException;
use Shopware\Core\Framework\JWT\Struct\JWKCollection;
use Shopware\Core\Framework\JWT\Struct\JWKStruct;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
final readonly class HasValidRSAJWKSignature implements Constraint
{
    private const ALGORITHMS = ['RS256', 'RS384', 'RS512'];

    public function __construct(private JWKCollection $jwks)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function assert(Token $token): void
    {
        $this->validateAlgorithm($token);
        $key = $this->getValidKey($token);
        /** @var non-empty-string $pem */
        $pem = $this->convertToPem($key);

        $signer = $this->getSigner($token->headers()->get('alg'));

        if (Feature::isActive('v6.8.0.0')) {
            (new SignedWith($signer, InMemory::plainText($pem)))->assert($token);
        } else {
            (new Validator())->assert($token, new SignedWith($signer, InMemory::plainText($pem)));
        }
    }

    private function validateAlgorithm(Token $token): void
    {
        $alg = $token->headers()->get('alg');
        if (!\in_array($alg, self::ALGORITHMS, true)) {
            throw JWTException::invalidJwt(\sprintf('Invalid algorithm (alg) in JWT header: "%s"', $alg));
        }
    }

    private function getValidKey(Token $token): JWKStruct
    {
        $kid = $token->headers()->get('kid');
        if (!$kid) {
            throw JWTException::invalidJwt('Key ID (kid) missing from JWT header');
        }

        foreach ($this->jwks->getElements() as $key) {
            if ($key->kid === $kid) {
                return $key;
            }
        }

        throw JWTException::invalidJwt('Key ID (kid) could not be found');
    }

    private function convertToPem(JWKStruct $key): string
    {
        if ($key->kty !== 'RSA') {
            throw JWTException::invalidJwt(\sprintf('Invalid key type: "%s"', $key->kty));
        }

        return (string) PublicKeyLoader::load([
            'e' => new BigInteger($this->base64UrlDecode($key->e), 256),
            'n' => new BigInteger($this->base64UrlDecode($key->n), 256),
        ]);
    }

    private function base64UrlDecode(string $data): string
    {
        $urlSafeData = strtr($data, '-_', '+/');
        $paddedData = str_pad($urlSafeData, \strlen($urlSafeData) % 4, '=');

        $decoded = base64_decode($paddedData, true);

        if (!\is_string($decoded)) {
            throw JWTException::invalidJwk('Invalid base64 characters detected');
        }

        return $decoded;
    }

    private function getSigner(string $alg): Rsa
    {
        return match ($alg) {
            'RS256' => new Sha256(),
            'RS384' => new Sha384(),
            'RS512' => new Sha512(),
            default => throw JWTException::invalidJwt(\sprintf('Unsupported algorithm: "%s"', $alg)),
        };
    }
}
