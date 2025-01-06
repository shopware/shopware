<?php declare(strict_types=1);

namespace Shopware\Core\Framework\JWT\Constraints;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Signer\Rsa\Sha384;
use Lcobucci\JWT\Signer\Rsa\Sha512;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\Validation\Constraint;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Shopware\Core\Framework\JWT\JWTException;
use Shopware\Core\Framework\JWT\Struct\JWKCollection;
use Shopware\Core\Framework\JWT\Struct\JWKStruct;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
final class HasValidRSAJWKSignature implements Constraint
{
    private const ALGORITHMS = ['RS256', 'RS384', 'RS512'];

    private JWKCollection $jwks;

    public function __construct(JWKCollection $jwks)
    {
        $this->jwks = $jwks;
    }

    public function assert(Token $token): void
    {
        $this->validateAlgorithm($token);
        $key = $this->getValidKey($token);
        $pem = $this->convertToPem($key);

        $signer = $this->getSigner($token->headers()->get('alg'));

        $configuration = Configuration::forSymmetricSigner(
            $signer,
            InMemory::plainText($pem)
        );

        $configuration->setValidationConstraints(
            new SignedWith($configuration->signer(), $configuration->signingKey())
        );
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

    /**
     * @return non-empty-string
     */
    private function convertToPem(JWKStruct $key): string
    {
        if ($key->kty !== 'RSA') {
            throw JWTException::invalidJwt(\sprintf('Invalid key type: "%s"', $key->kty));
        }

        $modulus = $this->base64UrlDecode($key->n);
        $exponent = $this->base64UrlDecode($key->e);

        $modulus = pack('Ca*a*', 2, $this->getLength($modulus), $modulus);
        $exponent = pack('Ca*a*', 2, $this->getLength($exponent), $exponent);

        $rsaPublicKey = pack('Ca*a*a*', 48, $this->getLength($modulus . $exponent), $modulus, $exponent);
        $rsaPublicKey = base64_encode($rsaPublicKey);
        $rsaPublicKey = chunk_split($rsaPublicKey, 64);

        return "-----BEGIN RSA PUBLIC KEY-----\n" . $rsaPublicKey . "-----END RSA PUBLIC KEY-----\n";
    }

    private function base64UrlDecode(string $data): string
    {
        $urlSafeData = strtr($data, '-_', '+/');
        $paddedData = str_pad($urlSafeData, \strlen($urlSafeData) % 4, '=');

        return (string) base64_decode($paddedData, true);
    }

    private function getLength(string $data): string
    {
        $length = \strlen($data);
        if ($length < 128) {
            return \chr($length);
        }

        $lengthBytes = '';
        while ($length > 0) {
            $lengthBytes = \chr($length & 0xFF) . $lengthBytes;
            $length >>= 8;
        }

        return \chr(0x80 | \strlen($lengthBytes)) . $lengthBytes;
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
