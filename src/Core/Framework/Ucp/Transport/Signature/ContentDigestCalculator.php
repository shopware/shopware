<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Signature;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\UcpException;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * RFC 9530 Content-Digest calculation. UCP requires `sha-256` (lower-case),
 * and the body is hashed in its raw on-wire form (no JSON normalisation).
 *
 * Format: `Content-Digest: sha-256=:<base64>:`
 *
 * @internal
 */
#[Package('framework')]
class ContentDigestCalculator
{
    public const ALGO_SHA256 = 'sha-256';
    public const ALGO_SHA512 = 'sha-512';

    public function calculate(string $body, string $algorithm = self::ALGO_SHA256): string
    {
        $hash = match ($algorithm) {
            // sha256/sha512 (not Hasher::hash) intentional: RFC 9530 Content-Digest
            // is defined exactly over these algorithms — the on-wire format is part
            // of the HTTP message signature contract and not a project convention.
            // @phpstan-ignore-next-line shopware.hasher
            self::ALGO_SHA256 => hash('sha256', $body, true),
            // @phpstan-ignore-next-line shopware.hasher
            self::ALGO_SHA512 => hash('sha512', $body, true),
            default => throw UcpException::signatureAlgorithmUnsupported($algorithm),
        };

        return $algorithm . '=:' . base64_encode($hash) . ':';
    }

    public function verify(string $body, string $headerValue): bool
    {
        $headerValue = trim($headerValue);
        if ($headerValue === '') {
            return false;
        }

        // RFC 9530: multiple algorithms can be present, comma-separated; we verify the first one we support.
        foreach (explode(',', $headerValue) as $entry) {
            $entry = trim($entry);
            if (!preg_match('/^(sha-256|sha-512)=:([A-Za-z0-9+\/=]+):$/i', $entry, $match)) {
                continue;
            }

            $algorithm = strtolower($match[1]);
            $expected = $this->calculate($body, $algorithm);

            if (hash_equals($expected, $algorithm . '=:' . $match[2] . ':')) {
                return true;
            }
        }

        return false;
    }
}
