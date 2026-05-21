<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Signature;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Jwt\EcKeyGenerator;
use Shopware\Core\Framework\Ucp\UcpException;
use Symfony\Component\HttpFoundation\Request;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * RFC 9421 HTTP Message Signature verifier.
 *
 * Performs (in order):
 *   1. Parse Signature-Input + Signature headers
 *   2. Resolve the public JWK by `keyid` from the platform's profile JWKS
 *   3. Validate `created`/`expires` window
 *   4. Verify Content-Digest against the request body
 *   5. Reconstruct the signature base and verify signature
 *
 * @internal
 */
#[Package('framework')]
class Rfc9421SignatureVerifier
{
    public const CLOCK_SKEW_TOLERANCE_SECONDS = 60;

    /**
     * Maximum signature validity window. RFC 9421 doesn't fix one; UCP
     * signatures.md recommends short-lived signatures (≤300s) so a captured
     * request can't be replayed indefinitely.
     */
    public const MAX_SIGNATURE_LIFETIME_SECONDS = 300;

    public function __construct(
        private readonly SignatureInputParser $parser = new SignatureInputParser(),
        private readonly SignatureBase $signatureBase = new SignatureBase(),
        private readonly ContentDigestCalculator $digest = new ContentDigestCalculator(),
        private readonly ?SignatureReplayGuard $replayGuard = null,
    ) {
    }

    /**
     * Verify an inbound Symfony request.
     *
     * @param array<int, array<string, mixed>> $jwks list of JWK objects from the platform's `signing_keys`
     * @param string|null $salesChannelId if provided, the verified signature is registered with
     *                                    the replay guard so the same signature cannot be replayed
     */
    public function verifyRequest(Request $request, array $jwks, ?string $salesChannelId = null): SignatureComponents
    {
        $signatureInputHeader = $request->headers->get('signature-input');
        $signatureHeader = $request->headers->get('signature');

        if ($signatureInputHeader === null || $signatureHeader === null) {
            throw UcpException::signatureMissing();
        }

        $body = (string) $request->getContent();
        $contentDigest = $request->headers->get('content-digest');

        // Per RFC 9530 §3 and UCP signatures.md, Content-Digest is REQUIRED
        // whenever a request body is present. Empty-body requests (typically
        // GET/HEAD/DELETE) MAY omit the digest header.
        if ($body !== '') {
            if ($contentDigest === null) {
                throw UcpException::signatureMissing();
            }
            if (!$this->digest->verify($body, $contentDigest)) {
                throw UcpException::digestMismatch();
            }
        } elseif ($contentDigest !== null && !$this->digest->verify($body, $contentDigest)) {
            // Header WAS sent on an empty body — must still match `sha-256=:<empty digest>:`
            throw UcpException::digestMismatch();
        }

        $parsed = $this->parser->parse($signatureInputHeader);
        if ($parsed === []) {
            throw UcpException::signatureInvalid('No signature labels parsed from Signature-Input');
        }

        // We honour the first signature label (UCP only emits a single one).
        $label = array_key_first($parsed);
        $entry = $parsed[$label];
        $components = $entry['components'];

        $this->validateTime($components);

        $kid = $components->getKeyId();
        if ($kid === null) {
            throw UcpException::signatureInvalid('Missing keyid parameter');
        }

        $jwk = $this->findJwk($jwks, $kid);
        if ($jwk === null) {
            throw UcpException::signatureKeyNotFound($kid);
        }

        $signatureValueRaw = $this->extractSignatureValue($signatureHeader, $label);

        $signatureBase = $this->signatureBase->buildFromSymfonyRequest(
            $request,
            $components,
            $entry['value']
        );

        if (!$this->verifySignature($signatureBase, $signatureValueRaw, $jwk)) {
            throw UcpException::signatureInvalid('Signature does not verify against public key');
        }

        // Replay-protect the verified signature. Registration MUST happen after
        // crypto verification so that we don't burn nonces on unverified inputs.
        if ($this->replayGuard !== null && $salesChannelId !== null) {
            $this->replayGuard->rememberOrThrow(
                $salesChannelId,
                $kid,
                $signatureValueRaw,
                $components->getCreated()
            );
        }

        return $components;
    }

    /**
     * @param array<int, array<string, mixed>> $jwks
     *
     * @return array<string, mixed>|null
     */
    private function findJwk(array $jwks, string $kid): ?array
    {
        $matched = null;
        foreach ($jwks as $jwk) {
            if (\is_array($jwk) && ($jwk['kid'] ?? null) === $kid) {
                if ($matched !== null) {
                    // Duplicate kid in JWKS — refuse to pick ambiguously and
                    // fail the verification. A malicious / misconfigured
                    // platform could otherwise smuggle a second key with the
                    // same id and have us pick the wrong one.
                    throw UcpException::signatureInvalid('Duplicate kid "' . $kid . '" in platform JWKS');
                }
                $matched = $jwk;
            }
        }

        return $matched;
    }

    private function validateTime(SignatureComponents $components): void
    {
        $now = time();
        $created = $components->getCreated();
        $expires = $components->getExpires();

        if ($created === null) {
            throw UcpException::signatureInvalid('Signature is missing the required "created" parameter');
        }
        if ($expires === null) {
            throw UcpException::signatureInvalid('Signature is missing the required "expires" parameter (UCP REQUIRES short-lived signatures)');
        }

        if ($created > $now + self::CLOCK_SKEW_TOLERANCE_SECONDS) {
            throw UcpException::signatureInvalid('Signature created in the future (clock skew exceeded)');
        }
        if ($created < $now - self::CLOCK_SKEW_TOLERANCE_SECONDS - self::MAX_SIGNATURE_LIFETIME_SECONDS) {
            throw UcpException::signatureInvalid('Signature creation timestamp is older than the maximum acceptable signature lifetime');
        }
        if ($expires < $now - self::CLOCK_SKEW_TOLERANCE_SECONDS) {
            throw UcpException::signatureInvalid('Signature expired');
        }
        if (($expires - $created) > self::MAX_SIGNATURE_LIFETIME_SECONDS) {
            throw UcpException::signatureInvalid(\sprintf(
                'Signature validity window (%ds) exceeds the maximum lifetime of %ds',
                $expires - $created,
                self::MAX_SIGNATURE_LIFETIME_SECONDS
            ));
        }
    }

    private function extractSignatureValue(string $signatureHeader, string $label): string
    {
        // Format: label=:base64:
        $pattern = '/' . preg_quote($label, '/') . '=:([A-Za-z0-9+\/=]+):/';
        if (preg_match($pattern, $signatureHeader, $match) !== 1) {
            throw UcpException::signatureInvalid('Signature header missing label "' . $label . '"');
        }

        $decoded = base64_decode($match[1], true);
        if ($decoded === false) {
            throw UcpException::signatureInvalid('Signature value is not valid base64');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $jwk
     */
    private function verifySignature(string $signatureBase, string $signatureRaw, array $jwk): bool
    {
        $kty = $jwk['kty'] ?? null;
        if ($kty !== 'EC') {
            return false;
        }

        $crv = $jwk['crv'] ?? null;
        $algorithm = match ($crv) {
            'P-256' => 'ES256',
            'P-384' => 'ES384',
            default => null,
        };

        if ($algorithm === null) {
            return false;
        }

        $hashAlgo = $algorithm === 'ES384' ? \OPENSSL_ALGO_SHA384 : \OPENSSL_ALGO_SHA256;

        $publicKeyPem = EcKeyGenerator::jwkToPem($jwk);
        if ($publicKeyPem === null) {
            return false;
        }

        $der = Rfc9421SignatureBuilder::rawToDer($signatureRaw, $algorithm);
        $result = openssl_verify($signatureBase, $der, $publicKeyPem, $hashAlgo);

        if ($result === -1) {
            // OpenSSL internal error — drain the error stack so it doesn't
            // leak into the next call, then treat as a hard verification
            // failure (NOT a silent false-return that would hide bugs).
            while (($err = openssl_error_string()) !== false) {
                // Throw on the first error so the caller sees the actual
                // OpenSSL reason in logs.
                throw UcpException::signatureInvalid('OpenSSL verify error: ' . $err);
            }
            throw UcpException::signatureInvalid('OpenSSL returned -1 from openssl_verify');
        }

        return $result === 1;
    }
}
