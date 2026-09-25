<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\DeviceBoundSession;

use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Exception as JwtException;
use Lcobucci\JWT\Signer\Ecdsa\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\RegisteredClaims;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;
use Shopware\Core\Framework\Log\Package;

/**
 * Verifies the `dbsc+jwt` proofs a browser sends with `Secure-Session-Response`.
 *
 * Only ES256 is supported, which every shipping DBSC implementation offers.
 *
 * @internal
 */
#[Package('framework')]
class DeviceBoundSessionProofVerifier
{
    public const ALGORITHM = 'ES256';

    /**
     * DER prefix of a SubjectPublicKeyInfo for an uncompressed P-256 point
     */
    private const P256_SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    /**
     * Verifies a registration proof, which carries its own public key.
     *
     * @return array{challenge: string, publicKey: array<string, mixed>}|null
     */
    public function verifyRegistration(string $proof): ?array
    {
        $token = $this->parse($proof);
        if ($token === null) {
            return null;
        }

        $publicKey = $token->headers()->get('jwk');
        if (!\is_array($publicKey)) {
            return null;
        }

        $challenge = $this->verify($token, $publicKey);
        if ($challenge === null) {
            return null;
        }

        return [
            'challenge' => $challenge,
            'publicKey' => array_intersect_key($publicKey, array_flip(['kty', 'crv', 'x', 'y'])),
        ];
    }

    /**
     * Verifies a refresh proof against the key stored at registration.
     *
     * @param array<string, mixed> $publicKey
     *
     * @return string|null the signed challenge
     */
    public function verifyRefresh(string $proof, array $publicKey): ?string
    {
        $token = $this->parse($proof);
        if ($token === null || $token->headers()->has('jwk')) {
            return null;
        }

        return $this->verify($token, $publicKey);
    }

    private function parse(string $proof): ?UnencryptedToken
    {
        if ($proof === '') {
            return null;
        }

        try {
            $token = (new Parser(new JoseEncoder()))->parse($proof);
        } catch (JwtException) {
            return null;
        }

        if (!$token instanceof UnencryptedToken) {
            return null;
        }

        if ($token->headers()->get('typ') !== 'dbsc+jwt' || $token->headers()->get('alg') !== self::ALGORITHM) {
            return null;
        }

        return $token;
    }

    /**
     * @param array<mixed> $publicKey
     */
    private function verify(UnencryptedToken $token, array $publicKey): ?string
    {
        $pem = $this->toPem($publicKey);
        if ($pem === null) {
            return null;
        }

        try {
            if (!(new Validator())->validate($token, new SignedWith(new Sha256(), InMemory::plainText($pem)))) {
                return null;
            }
        } catch (JwtException) {
            return null;
        }

        $challenge = $token->claims()->get(RegisteredClaims::ID);

        return \is_string($challenge) && $challenge !== '' ? $challenge : null;
    }

    /**
     * @param array<mixed> $jwk
     *
     * @return non-empty-string|null
     */
    private function toPem(array $jwk): ?string
    {
        if (($jwk['kty'] ?? null) !== 'EC' || ($jwk['crv'] ?? null) !== 'P-256') {
            return null;
        }

        $x = $this->decodeCoordinate($jwk['x'] ?? null);
        $y = $this->decodeCoordinate($jwk['y'] ?? null);
        if ($x === null || $y === null) {
            return null;
        }

        $der = hex2bin(self::P256_SPKI_PREFIX) . "\x04" . $x . $y;

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private function decodeCoordinate(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded !== false && \strlen($decoded) === 32 ? $decoded : null;
    }
}
