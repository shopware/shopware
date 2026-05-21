<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Signature;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSigningKeyEntity;
use Shopware\Core\Framework\Ucp\UcpException;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Builds an RFC 9421 HTTP Message Signature for outbound requests and webhooks.
 *
 * @internal
 */
#[Package('framework')]
class Rfc9421SignatureBuilder
{
    public const SIGNATURE_LABEL = 'sig1';

    public function __construct(
        private readonly SignatureBase $signatureBase = new SignatureBase(),
        private readonly ContentDigestCalculator $digest = new ContentDigestCalculator(),
    ) {
    }

    /**
     * Build all signature-related headers for an outbound HTTP request.
     *
     * @param array<string, string> $headers existing headers, will be augmented with `Content-Digest`
     * @param list<string>|null $components signed component identifiers (defaults to outbound-webhook profile)
     *
     * @return array{
     *     headers: array<string, string>,
     *     signature_input: string,
     *     signature: string,
     *     content_digest: string,
     * }
     */
    public function signRequest(
        string $method,
        string $targetUri,
        string $body,
        array $headers,
        UcpSigningKeyEntity $key,
        string $privateKeyPem,
        ?int $createdAt = null,
        int $expiresInSeconds = 300,
        ?array $components = null
    ): array {
        $components ??= SignatureComponents::forOutboundWebhook();
        $createdAt ??= time();

        // Always include Content-Digest in headers
        $contentDigest = $this->digest->calculate($body);
        $headers = SignatureBase::normalizeHeaders($headers);
        $headers['content-digest'] = $contentDigest;

        $signatureParamsValue = $this->buildSignatureParams($components, $key->getKid(), $createdAt, $createdAt + $expiresInSeconds);

        $signatureBase = $this->signatureBase->buildForRequest($method, $targetUri, $headers, new SignatureComponents($components, []), $signatureParamsValue);

        $signature = $this->sign($signatureBase, $key, $privateKeyPem);

        $signatureInputHeader = self::SIGNATURE_LABEL . '=' . $signatureParamsValue;
        $signatureHeader = self::SIGNATURE_LABEL . '=:' . base64_encode($signature) . ':';

        return [
            'headers' => array_merge($headers, [
                'content-digest' => $contentDigest,
                'signature-input' => $signatureInputHeader,
                'signature' => $signatureHeader,
            ]),
            'signature_input' => $signatureInputHeader,
            'signature' => $signatureHeader,
            'content_digest' => $contentDigest,
        ];
    }

    /**
     * DER (`SEQUENCE { INTEGER r, INTEGER s }`) -> R || S concatenation.
     */
    public static function derToRaw(string $der, string $algorithm): string
    {
        $byteLength = $algorithm === UcpSigningKeyEntity::ALGORITHM_ES384 ? 48 : 32;

        $offset = 2;
        if (\ord($der[1]) > 0x80) {
            $offset += \ord($der[1]) - 0x80;
        }

        // First INTEGER
        ++$offset; // tag
        $rLen = \ord($der[$offset]);
        ++$offset;
        $r = substr($der, $offset, $rLen);
        $offset += $rLen;

        // Second INTEGER
        ++$offset; // tag
        $sLen = \ord($der[$offset]);
        ++$offset;
        $s = substr($der, $offset, $sLen);

        $r = self::leftPad(ltrim($r, "\x00"), $byteLength);
        $s = self::leftPad(ltrim($s, "\x00"), $byteLength);

        return $r . $s;
    }

    public static function rawToDer(string $raw, string $algorithm): string
    {
        $byteLength = $algorithm === UcpSigningKeyEntity::ALGORITHM_ES384 ? 48 : 32;
        if (\strlen($raw) !== $byteLength * 2) {
            throw UcpException::signatureInvalid('Raw ECDSA signature length mismatch');
        }

        $r = self::encodeInteger(substr($raw, 0, $byteLength));
        $s = self::encodeInteger(substr($raw, $byteLength));
        $sequence = $r . $s;

        return "\x30" . self::encodeLength(\strlen($sequence)) . $sequence;
    }

    /**
     * @param list<string> $components
     */
    private function buildSignatureParams(array $components, string $kid, int $created, int $expires): string
    {
        $list = '(' . implode(' ', array_map(static fn (string $c) => '"' . $c . '"', $components)) . ')';
        $list .= ';created=' . $created;
        $list .= ';expires=' . $expires;
        $list .= ';keyid="' . $kid . '"';
        $list .= ';tag="ucp"';

        return $list;
    }

    private function sign(string $signatureBase, UcpSigningKeyEntity $key, string $privateKeyPem): string
    {
        $resource = openssl_pkey_get_private($privateKeyPem);
        if ($resource === false) {
            throw UcpException::keyDecryptionFailed();
        }

        $hashAlgo = match ($key->getAlgorithm()) {
            UcpSigningKeyEntity::ALGORITHM_ES256 => \OPENSSL_ALGO_SHA256,
            UcpSigningKeyEntity::ALGORITHM_ES384 => \OPENSSL_ALGO_SHA384,
            default => throw UcpException::signatureAlgorithmUnsupported($key->getAlgorithm()),
        };

        $derSignature = '';
        if (!openssl_sign($signatureBase, $derSignature, $resource, $hashAlgo)) {
            throw UcpException::signatureInvalid('openssl_sign failed: ' . (openssl_error_string() ?: 'unknown'));
        }

        // Convert DER -> RFC 9421 raw (concatenated R||S) format
        return self::derToRaw($derSignature, $key->getAlgorithm());
    }

    private static function leftPad(string $bytes, int $length): string
    {
        return str_pad($bytes, $length, "\x00", \STR_PAD_LEFT);
    }

    private static function encodeInteger(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '' || \ord($bytes[0]) > 0x7F) {
            $bytes = "\x00" . $bytes;
        }

        return "\x02" . self::encodeLength(\strlen($bytes)) . $bytes;
    }

    private static function encodeLength(int $length): string
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
}
