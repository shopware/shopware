<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Jwt;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSigningKeyEntity;
use Shopware\Core\Framework\Ucp\Security\PrivateKeyEncryptor;
use Shopware\Core\Framework\Ucp\UcpException;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Generates EC keypairs (P-256 / P-384) and serialises the public part as a JWK
 * per RFC 7517. The PEM-encoded private key is returned alongside, ready for
 * encryption via {@see PrivateKeyEncryptor}.
 *
 * @internal
 */
#[Package('framework')]
class EcKeyGenerator
{
    private const SUPPORTED_ALGORITHMS = [
        UcpSigningKeyEntity::ALGORITHM_ES256 => ['curve' => 'prime256v1', 'jwk_crv' => 'P-256'],
        UcpSigningKeyEntity::ALGORITHM_ES384 => ['curve' => 'secp384r1',  'jwk_crv' => 'P-384'],
    ];

    /**
     * @return array{kid: string, algorithm: string, public_jwk: array<string, mixed>, private_key_pem: string}
     */
    public function generate(string $algorithm = UcpSigningKeyEntity::ALGORITHM_ES256): array
    {
        if (!isset(self::SUPPORTED_ALGORITHMS[$algorithm])) {
            throw UcpException::signatureAlgorithmUnsupported($algorithm);
        }

        $config = self::SUPPORTED_ALGORITHMS[$algorithm];

        $resource = openssl_pkey_new([
            'private_key_type' => \OPENSSL_KEYTYPE_EC,
            'curve_name' => $config['curve'],
        ]);

        if ($resource === false) {
            throw UcpException::keyGenerationFailed('openssl_pkey_new returned false: ' . (openssl_error_string() ?: 'unknown'));
        }

        if (!openssl_pkey_export($resource, $privatePem)) {
            throw UcpException::keyGenerationFailed('openssl_pkey_export failed: ' . (openssl_error_string() ?: 'unknown'));
        }

        $details = openssl_pkey_get_details($resource);
        if ($details === false || !isset($details['ec']) || !\is_array($details['ec'])) {
            throw UcpException::keyGenerationFailed('openssl_pkey_get_details returned no EC details');
        }

        $kid = $this->deriveKid($algorithm, $details['ec']);

        $jwk = [
            'kty' => 'EC',
            'crv' => $config['jwk_crv'],
            'x' => self::base64UrlEncode($details['ec']['x'] ?? ''),
            'y' => self::base64UrlEncode($details['ec']['y'] ?? ''),
            'use' => 'sig',
            'alg' => $algorithm,
            'kid' => $kid,
        ];

        return [
            'kid' => $kid,
            'algorithm' => $algorithm,
            'public_jwk' => $jwk,
            'private_key_pem' => $privatePem,
        ];
    }

    public static function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $value): string
    {
        $pad = 4 - (\strlen($value) % 4);
        if ($pad < 4) {
            $value .= str_repeat('=', $pad);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw UcpException::signatureInvalid('Invalid base64url-encoded value');
        }

        return $decoded;
    }

    /**
     * Translate a JWK EC public key into a PEM-encoded SubjectPublicKeyInfo
     * structure that openssl_verify() accepts.
     *
     * @param array<string, mixed> $jwk
     *
     * @return string|null PEM-encoded SPKI, or null if the JWK is malformed /
     *                     uses an unsupported curve.
     */
    public static function jwkToPem(array $jwk): ?string
    {
        $x = $jwk['x'] ?? null;
        $y = $jwk['y'] ?? null;
        $crv = $jwk['crv'] ?? null;

        if (!\is_string($x) || !\is_string($y) || !\is_string($crv)) {
            return null;
        }

        $oid = match ($crv) {
            'P-256' => "\x06\x08\x2A\x86\x48\xCE\x3D\x03\x01\x07", // secp256r1
            'P-384' => "\x06\x05\x2B\x81\x04\x00\x22",             // secp384r1
            default => null,
        };
        if ($oid === null) {
            return null;
        }

        $xBytes = self::base64UrlDecode($x);
        $yBytes = self::base64UrlDecode($y);
        $byteLength = $crv === 'P-384' ? 48 : 32;
        $xBytes = str_pad($xBytes, $byteLength, "\x00", \STR_PAD_LEFT);
        $yBytes = str_pad($yBytes, $byteLength, "\x00", \STR_PAD_LEFT);

        $point = "\x04" . $xBytes . $yBytes;
        $bitString = "\x03" . self::asnLength(\strlen($point) + 1) . "\x00" . $point;

        $algorithmId = "\x06\x07\x2A\x86\x48\xCE\x3D\x02\x01" . $oid;
        $algorithmIdSeq = "\x30" . self::asnLength(\strlen($algorithmId)) . $algorithmId;

        $spki = $algorithmIdSeq . $bitString;
        $spkiSequence = "\x30" . self::asnLength(\strlen($spki)) . $spki;

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($spkiSequence), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    /**
     * Convert a JWS raw-format ECDSA signature (concatenated R||S) into the
     * ASN.1 DER format that {@see openssl_verify()} expects.
     *
     * Per RFC 7518 §3.4, JWS ECDSA signatures are fixed-length raw R||S.
     * OpenSSL on the other hand consumes DER-encoded SEQUENCE-of-INTEGER.
     */
    public static function jwsSignatureToDer(string $rawSignature, string $algorithm): string
    {
        $componentLength = $algorithm === 'ES384' ? 48 : 32;
        if (\strlen($rawSignature) !== 2 * $componentLength) {
            throw UcpException::signatureInvalid(
                'Raw JWS signature length mismatch: expected '
                . (2 * $componentLength) . ' bytes for ' . $algorithm
                . ', got ' . \strlen($rawSignature)
            );
        }

        $r = self::asnInteger(substr($rawSignature, 0, $componentLength));
        $s = self::asnInteger(substr($rawSignature, $componentLength));
        $body = $r . $s;

        return "\x30" . self::asnLength(\strlen($body)) . $body;
    }

    private static function asnInteger(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '' || (\ord($bytes[0]) & 0x80) !== 0) {
            $bytes = "\x00" . $bytes;
        }

        return "\x02" . self::asnLength(\strlen($bytes)) . $bytes;
    }

    private static function asnLength(int $length): string
    {
        if ($length < 0x80) {
            return \chr($length);
        }

        $bytes = '';
        while ($length > 0) {
            $bytes = \chr($length & 0xFF) . $bytes;
            $length >>= 8;
        }

        return \chr(0x80 | \strlen($bytes)) . $bytes;
    }

    /**
     * @param array<string, mixed> $ecDetails
     */
    private function deriveKid(string $algorithm, array $ecDetails): string
    {
        // Stable, collision-resistant kid: short hash of the public point + algorithm + timestamp prefix.
        // sha256 (not Hasher::hash) intentional: the kid identifies a specific
        // EC keypair; we need cryptographic collision resistance so two distinct
        // keys can never share an identifier.
        $material = ($ecDetails['x'] ?? '') . ($ecDetails['y'] ?? '') . $algorithm;
        // @phpstan-ignore-next-line shopware.hasher
        $hash = substr(hash('sha256', $material), 0, 16);

        return 'ucp_' . date('Y') . '_' . $hash;
    }
}
